<?php

namespace App\Services;

use App\Models\Conversation;
use Closure;
use Illuminate\Support\Facades\Log;

class ChatResponseContinuationService
{
    public function __construct(
        private readonly ChatCompletionService $chat,
    ) {}

    /**
     * Continue ordinary chat replies until the provider finishes or continuation fails
     * after an output-token cutoff. Tool calls are never resumed as prose or executed
     * from a response that the provider marked incomplete.
     *
     * @param  array<string, mixed>  $completion
     * @param  array<int, array<string, mixed>>  $history
     * @return array<string, mixed>
     */
    public function continueIfTruncated(
        array $completion,
        Conversation $conversation,
        string $model,
        array $history,
        ?string $systemPrompt,
        string $safetyIdentifier,
        ?Closure $onDelta = null,
        ?int $timeoutSeconds = null,
    ): array {
        $completion['content'] = is_string($completion['content'] ?? null) ? $completion['content'] : '';
        $completion['prompt_tokens'] = max(0, (int) ($completion['prompt_tokens'] ?? 0));
        $completion['completion_tokens'] = max(0, (int) ($completion['completion_tokens'] ?? 0));

        if (($completion['finish_reason'] ?? null) !== 'length') {
            return $completion;
        }

        if (($completion['tool_calls'] ?? []) !== []) {
            $completion['tool_calls'] = [];

            return $this->appendIncompleteNotice($completion, $onDelta, blockOutputs: false);
        }

        set_time_limit(0);
        while (($completion['finish_reason'] ?? null) === 'length') {
            $messages = [
                ...$history,
                ['role' => 'assistant', 'content' => $completion['content']],
                [
                    'role' => 'user',
                    'content' => 'Continue the answer from the exact point where the previous response stopped. Do not repeat any text already provided. Preserve its format, including table columns. Complete any unfinished sentence, list item, table row, or section, then provide the remaining requested content. Do not summarize or claim completion until the requested answer is finished.',
                ],
            ];

            try {
                $continuation = $onDelta === null
                    ? $this->chat->complete($messages, $systemPrompt, $safetyIdentifier, timeoutSeconds: $timeoutSeconds, model: $model)
                    : $this->chat->streamComplete($messages, $systemPrompt, $onDelta, $safetyIdentifier, timeoutSeconds: $timeoutSeconds, model: $model);
            } catch (\Throwable $e) {
                Log::warning('Truncated chat response continuation failed', [
                    'conversation_id' => $conversation->id,
                    'exception' => get_class($e),
                ]);

                return $this->appendIncompleteNotice($completion, $onDelta, true);
            }

            $continuationContent = $continuation['content'];
            $completion['content'] .= $continuationContent;
            $completion['prompt_tokens'] += $continuation['prompt_tokens'];
            $completion['completion_tokens'] += $continuation['completion_tokens'];
            $completion['finish_reason'] = $continuation['finish_reason'] ?? null;

            if (($continuation['tool_calls'] ?? []) !== []) {
                $completion['blocked_by_incomplete_result'] = true;

                return $this->appendIncompleteNotice($completion, $onDelta);
            }

            if ($continuationContent === '') {
                return $this->appendIncompleteNotice($completion, $onDelta);
            }
        }

        return $completion;
    }

    /**
     * @param  array<string, mixed>  $completion
     * @return array<string, mixed>
     */
    private function appendIncompleteNotice(array $completion, ?Closure $onDelta, bool $continuationFailed = false, bool $blockOutputs = true): array
    {
        $notice = $continuationFailed
            ? '⚠️ **Phản hồi có thể chưa đầy đủ:** AI+ không thể tiếp tục câu trả lời. Hãy yêu cầu tiếp tục từ phần cuối cùng.'
            : '⚠️ **Phản hồi có thể chưa đầy đủ:** OpenAI đã kết thúc phản hồi trước khi hoàn tất yêu cầu. Hãy yêu cầu tiếp tục từ phần cuối cùng.';
        $content = (string) ($completion['content'] ?? '');
        $completion['content'] = ($content !== '' ? rtrim($content)."\n\n" : '').$notice;
        if ($blockOutputs) {
            $completion['blocked_by_incomplete_result'] = true;
        }
        $onDelta?->__invoke($notice);

        return $completion;
    }
}

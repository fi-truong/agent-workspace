<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Log;

class ChatResponseContinuationService
{
    private const MIN_CONTINUATION_TOKENS = 256;

    public function __construct(
        private readonly ChatCompletionService $chat,
        private readonly TokenQuotaService $quota,
    ) {}

    /**
     * Continue ordinary chat replies once when the provider explicitly reports
     * an output-token cutoff. Tool calls are never resumed as prose or executed
     * from a response that the provider marked incomplete.
     *
     * @param  array<string, mixed>  $completion
     * @param  array<int, array<string, mixed>>  $history
     * @return array<string, mixed>
     */
    public function continueIfTruncated(
        array $completion,
        Conversation $conversation,
        User $user,
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
            // Discard the cutoff tool call. The existing output flow may still
            // run its independent confirmation and saved-draft recovery path.
            $completion['tool_calls'] = [];

            return $this->appendIncompleteNotice($completion, $onDelta, blockOutputs: false);
        }

        $initialPromptTokens = max(0, (int) $completion['prompt_tokens']);
        $initialCompletionTokens = max(0, (int) $completion['completion_tokens']);
        $initialUsage = $initialPromptTokens + $initialCompletionTokens;
        $remainingTokens = $this->quota->remainingTokens($user, $model);

        // The original usage is not persisted until the end of this request.
        // Reserve it here, then reserve a similarly sized continuation prompt
        // (the chat history plus the partial answer) before spending on output.
        $unrecordedInitialUsage = isset($completion['recorded_prompt_tokens']) ? 0 : $initialUsage;
        $availableContinuationTokens = $remainingTokens - $unrecordedInitialUsage - $initialUsage;
        $maxContinuationTokens = min(
            max(1, (int) config('openai.max_tokens', 8192)),
            $availableContinuationTokens,
        );

        if ($maxContinuationTokens < self::MIN_CONTINUATION_TOKENS) {
            return $this->appendIncompleteNotice($completion, $onDelta);
        }

        set_time_limit(($timeoutSeconds ?? (int) config('openai.timeout', 120)) + 30);

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
                ? $this->chat->complete(
                    $messages,
                    $systemPrompt,
                    $safetyIdentifier,
                    maxCompletionTokens: $maxContinuationTokens,
                    timeoutSeconds: $timeoutSeconds,
                    model: $model,
                )
                : $this->chat->streamComplete(
                    $messages,
                    $systemPrompt,
                    $onDelta,
                    $safetyIdentifier,
                    maxCompletionTokens: $maxContinuationTokens,
                    timeoutSeconds: $timeoutSeconds,
                    model: $model,
                );
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

        if (($continuation['tool_calls'] ?? []) !== []) {
            $completion['blocked_by_incomplete_result'] = true;
        }

        if ($continuationContent === '' || ($continuation['finish_reason'] ?? null) !== 'stop' || ($continuation['tool_calls'] ?? []) !== []) {
            return $this->appendIncompleteNotice($completion, $onDelta);
        }

        $completion['finish_reason'] = 'stop';

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
            : '⚠️ **Phản hồi có thể chưa đầy đủ:** AI+ đã chạm giới hạn đầu ra hoặc quota trước khi hoàn tất. Hãy yêu cầu tiếp tục từ phần cuối cùng.';
        $content = (string) ($completion['content'] ?? '');
        $completion['content'] = ($content !== '' ? rtrim($content)."\n\n" : '').$notice;
        if ($blockOutputs) {
            $completion['blocked_by_incomplete_result'] = true;
        }
        $onDelta?->__invoke($notice);

        return $completion;
    }
}

<?php

namespace App\Services;

use App\Services\Guardrail\RegexPiiFilter;

class ChatCompletionService
{
    public function __construct(
        private readonly OpenAIClient $client,
        private readonly RegexPiiFilter $piiFilter,
    ) {}

    /**
     * @param  array<int, array{role: string, content: mixed}>  $messages
     * @return array{content: string, prompt_tokens: int, completion_tokens: int}
     *
     * @throws \RuntimeException
     */
    public function complete(array $messages, ?string $systemPrompt = null): array
    {
        [$payload, $filtered] = $this->buildPayload($messages, $systemPrompt);

        if (! config('openai.api_key')) {
            return $this->mockCompletion($filtered);
        }

        try {
            return $this->client->chat($payload);
        } catch (\Throwable $e) {
            throw new \RuntimeException(OpenAIErrorMapper::message($e), 0, $e);
        }
    }

    /**
     * Bản streaming của complete(): gọi $onDelta(string $chunk) mỗi khi có thêm nội dung mới.
     *
     * @param  array<int, array{role: string, content: mixed}>  $messages
     * @param  callable(string): void  $onDelta
     * @return array{content: string, prompt_tokens: int, completion_tokens: int}
     *
     * @throws \RuntimeException
     */
    public function streamComplete(array $messages, ?string $systemPrompt, callable $onDelta): array
    {
        [$payload, $filtered] = $this->buildPayload($messages, $systemPrompt);

        if (! config('openai.api_key')) {
            return $this->mockStreamCompletion($filtered, $onDelta);
        }

        try {
            return $this->client->streamChat($payload, $onDelta);
        } catch (\Throwable $e) {
            throw new \RuntimeException(OpenAIErrorMapper::message($e), 0, $e);
        }
    }

    /**
     * @return array{0: array, 1: array} [$payload, $filteredMessages]
     */
    private function buildPayload(array $messages, ?string $systemPrompt): array
    {
        $filtered = array_map(
            fn (array $message): array => $message['role'] === 'user'
                ? ['role' => 'user', 'content' => $this->filterContentPii($message['content'])]
                : $message,
            $messages,
        );

        $systemPromptFiltered = $systemPrompt ? $this->piiFilter->filter($systemPrompt)['filtered'] : null;

        $payload = [];

        if ($systemPromptFiltered) {
            $payload[] = ['role' => 'system', 'content' => $systemPromptFiltered];
        }

        $payload = array_merge($payload, $filtered);

        return [$payload, $filtered];
    }

    private function filterContentPii(mixed $content): mixed
    {
        if (is_string($content)) {
            return $this->piiFilter->filter($content)['filtered'];
        }

        if (is_array($content)) {
            return array_map(function (array $part): array {
                if (($part['type'] ?? '') === 'text') {
                    $part['text'] = $this->piiFilter->filter((string) ($part['text'] ?? ''))['filtered'];
                }

                return $part;
            }, $content);
        }

        return $content;
    }

    /**
     * @param  array<int, array{role: string, content: mixed}>  $messages
     * @return array{content: string, prompt_tokens: int, completion_tokens: int}
     */
    private function mockCompletion(array $messages): array
    {
        $lastUser = $this->extractLastUserText($messages);

        $reply = 'Đây là phản hồi giả lập (chưa kết nối OpenAI API thật). '
            ."Bạn vừa hỏi: \"{$lastUser}\". "
            .'Khi có API key, phần này sẽ được thay bằng câu trả lời thật từ GPT-5.6 Luna.';

        return [
            'content' => $reply,
            'prompt_tokens' => (int) (str_word_count($lastUser) * 1.3),
            'completion_tokens' => (int) (str_word_count($reply) * 1.3),
        ];
    }

    /**
     * Bản streaming của mock: chia câu trả lời giả lập thành từng từ,
     * gọi $onDelta cho từng từ kèm delay nhỏ để giả lập hiệu ứng gõ chữ khi test local (chưa có API key).
     */
    private function mockStreamCompletion(array $messages, callable $onDelta): array
    {
        $result = $this->mockCompletion($messages);

        $pieces = preg_split('/(\s+)/u', $result['content'], -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$result['content']];

        foreach ($pieces as $piece) {
            if ($piece === '') {
                continue;
            }

            $onDelta($piece);
            usleep(30000); // 30ms/từ — chỉ để test giao diện streaming, không tốn phí thật
        }

        return $result;
    }

    private function extractLastUserText(array $messages): string
    {
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (($messages[$i]['role'] ?? '') !== 'user') {
                continue;
            }

            $content = $messages[$i]['content'];

            if (is_array($content)) {
                foreach ($content as $part) {
                    if (($part['type'] ?? '') === 'text') {
                        return (string) ($part['text'] ?? '');
                    }
                }

                return '';
            }

            return (string) $content;
        }

        return '';
    }
}

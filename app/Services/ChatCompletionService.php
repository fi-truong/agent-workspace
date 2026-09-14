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
     * Nhận messages (OpenAI format) + optional system prompt,
     * filter PII mỗi content role=user, gọi OpenAI thật nếu có key,
     * ngược lại fallback mock.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{content: string, prompt_tokens: int, completion_tokens: int}
     *
     * @throws \RuntimeException Khi OPENAI_API_KEY có nhưng gọi thất bại (sau khi map lỗi).
     */
    public function complete(array $messages, ?string $systemPrompt = null): array
    {
        $filtered = array_map(
            fn (array $message): array => $message['role'] === 'user'
                ? ['role' => 'user', 'content' => $this->piiFilter->filter((string) $message['content'])['filtered']]
                : $message,
            $messages,
        );

        $systemPromptFiltered = $systemPrompt ? $this->piiFilter->filter($systemPrompt)['filtered'] : null;

        if (! config('openai.api_key')) {
            return $this->mockCompletion($filtered);
        }

        try {
            $payload = [];

            if ($systemPromptFiltered) {
                $payload[] = ['role' => 'system', 'content' => $systemPromptFiltered];
            }

            $payload = array_merge($payload, $filtered);

            return $this->client->chat($payload);
        } catch (\Throwable $e) {
            throw new \RuntimeException(OpenAIErrorMapper::message($e), 0, $e);
        }
    }

    /**
     * Mock giữ nguyên contract. Lấy message user cuối cùng để trả lời.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{content: string, prompt_tokens: int, completion_tokens: int}
     */
    private function mockCompletion(array $messages): array
    {
        $lastUser = '';

        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (($messages[$i]['role'] ?? '') === 'user') {
                $lastUser = (string) $messages[$i]['content'];

                break;
            }
        }

        $reply = 'Đây là phản hồi giả lập (chưa kết nối OpenAI API thật). '
            ."Bạn vừa hỏi: \"{$lastUser}\". "
            .'Khi có API key, phần này sẽ được thay bằng câu trả lời thật từ GPT-5.6 Luna.';

        return [
            'content' => $reply,
            'prompt_tokens' => (int) (str_word_count($lastUser) * 1.3),
            'completion_tokens' => (int) (str_word_count($reply) * 1.3),
        ];
    }
}

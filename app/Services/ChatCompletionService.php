<?php

namespace App\Services;

class ChatCompletionService
{
    /**
     * MOCK — chưa kết nối OpenAI API thật (chưa có API key).
     * Khi có key, thay TOÀN BỘ nội dung bên trong hàm complete() bằng lệnh gọi
     * OpenAI Chat Completions API thật (model: GPT-5.6 Luna theo cost model đã chốt).
     * KHÔNG đổi tên hàm/chữ ký (signature) để Controller không cần sửa gì.
     *
     * @return array{content: string, prompt_tokens: int, completion_tokens: int}
     */
    public function complete(string $userMessage): array
    {
        $reply = 'Đây là phản hồi giả lập (chưa kết nối OpenAI API thật). '
            ."Bạn vừa hỏi: \"{$userMessage}\". "
            .'Khi có API key, phần này sẽ được thay bằng câu trả lời thật từ GPT-5.6 Luna.';

        return [
            'content' => $reply,
            'prompt_tokens' => (int) (str_word_count($userMessage) * 1.3),
            'completion_tokens' => (int) (str_word_count($reply) * 1.3),
        ];
    }
}

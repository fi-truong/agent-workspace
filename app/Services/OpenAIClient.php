<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class OpenAIClient
{
    /**
     * Gọi OpenAI Chat Completions API.
     *
     * @param  array<int, array{role: string, content: mixed}>  $messages  content có thể là string hoặc array multimodal
     * @return array{content: string, prompt_tokens: int, completion_tokens: int, model: string}
     *
     * @throws \Throwable Ném lại exception sau khi đã map lỗi, để caller xử lý theo kiểu LLM error.
     */
    public function chat(array $messages): array
    {
        $apiKey = config('openai.api_key');

        if (! $apiKey) {
            throw new \RuntimeException(
                'OPENAI_API_KEY chưa được cấu hình. Thêm vào .env trước khi gọi OpenAI.',
            );
        }

        $payload = [
            'model' => config('openai.model'),
            'messages' => $messages,
            // Model GPT-5+ không chấp nhận "max_tokens"; phải dùng "max_completion_tokens".
            'max_completion_tokens' => config('openai.max_tokens'),
            // Model GPT-5+ không hỗ trợ "temperature" tùy chỉnh (chỉ dùng giá trị mặc định).
        ];

        $response = Http::retry(
            config('openai.retry_times'),
            config('openai.retry_delay_ms'),
            function (\Throwable $exception): bool {
                return $this->shouldRetry($exception);
            },
        )
            ->baseUrl(config('openai.base_url'))
            ->timeout(config('openai.timeout'))
            ->connectTimeout(5)
            ->withToken($apiKey)
            ->asJson()
            ->post('/chat/completions', $payload);

        if (! $response->successful()) {
            $response->throw();
        }

        $data = $response->json();

        $content = $data['choices'][0]['message']['content']
            ?? throw new \RuntimeException('OpenAI trả về phản hồi thiếu nội dung.');

        $usage = $data['usage'] ?? [];

        return [
            'content' => $content,
            'prompt_tokens' => (int) ($usage['prompt_tokens'] ?? 0),
            'completion_tokens' => (int) ($usage['completion_tokens'] ?? 0),
            'model' => $data['model'] ?? config('openai.model'),
        ];
    }

    /**
     * Chỉ retry lỗi tạm thời: mất kết nối / timeout, hoặc 5xx từ upstream.
     */
    private function shouldRetry(\Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if ($exception instanceof RequestException && $exception->response !== null) {
            return $exception->response->serverError();
        }

        return false;
    }

    /**
     * Verify API key bằng endpoint /models (không tính phí token).
     *
     * @return array{ok: bool, error?: string, count?: int, models?: array<int, string>}
     */
    public function verifyKey(): array
    {
        $apiKey = config('openai.api_key');

        if (! $apiKey) {
            return ['ok' => false, 'error' => 'OPENAI_API_KEY chưa được cấu hình trong .env'];
        }

        try {
            $response = Http::retry(
                config('openai.retry_times'),
                config('openai.retry_delay_ms'),
                fn (\Throwable $exception): bool => $this->shouldRetry($exception),
            )
                ->baseUrl(config('openai.base_url'))
                ->timeout(config('openai.timeout'))
                ->connectTimeout(5)
                ->withToken($apiKey)
                ->acceptJson()
                ->get('/models');

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'error' => 'Không thể xác thực API key (HTTP '.$response->status().'). '.$response->body(),
                ];
            }

            $models = $response->json('data');

            /** @var array<int, string> $names */
            $names = [];
            if (is_array($models)) {
                $names = array_values(array_filter(array_map(
                    static fn ($m) => $m['id'] ?? null,
                    $models,
                )));
            }

            return [
                'ok' => true,
                'count' => count($names),
                'models' => $names,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

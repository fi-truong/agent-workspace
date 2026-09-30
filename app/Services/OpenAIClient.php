<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIClient
{
    /**
     * Gọi OpenAI Chat Completions API (không streaming — giữ nguyên cho các chỗ khác đang dùng).
     *
     * @param  array<int, array{role: string, content: mixed}>  $messages
     * @return array{content: string, prompt_tokens: int, completion_tokens: int, model: string}
     *
     * @throws \Throwable
     */
    public function chat(array $messages, ?string $safetyIdentifier = null, ?int $maxCompletionTokens = null): array
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
            'max_completion_tokens' => $maxCompletionTokens ?? config('openai.max_tokens'),
            'reasoning_effort' => config('openai.reasoning_effort'),
        ];
        if ($safetyIdentifier !== null) {
            $payload['safety_identifier'] = $safetyIdentifier;
        }

        $response = Http::retry(
            $this->retryAttempts(),
            $this->retryDelayMilliseconds(),
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

        $finishReason = $data['choices'][0]['finish_reason'] ?? null;

        if ($content === '' && $finishReason === 'length') {
            throw new \RuntimeException(
                'Câu trả lời bị cắt vì vượt giới hạn token (thường do prompt/Knowledge quá dài). '
                .'Hãy thử rút ngắn tin nhắn, hoặc liên hệ CIEC để tăng giới hạn.'
            );
        }

        $usage = $data['usage'] ?? [];

        return [
            'content' => $content,
            'prompt_tokens' => (int) ($usage['prompt_tokens'] ?? 0),
            'completion_tokens' => (int) ($usage['completion_tokens'] ?? 0),
            'model' => $data['model'] ?? config('openai.model'),
        ];
    }

    /**
     * Gọi OpenAI Chat Completions API dạng STREAMING (Server-Sent Events từ OpenAI).
     * Mỗi khi nhận được 1 đoạn nội dung mới, gọi $onDelta($textChunk) ngay lập tức
     * để caller (controller) đẩy tiếp ra trình duyệt theo thời gian thực.
     *
     * @param  array<int, array{role: string, content: mixed}>  $messages
     * @param  callable(string): void  $onDelta
     * @return array{content: string, prompt_tokens: int, completion_tokens: int, model: string}
     *
     * @throws \Throwable
     */
    public function streamChat(array $messages, callable $onDelta, ?string $safetyIdentifier = null, ?int $maxCompletionTokens = null): array
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
            'max_completion_tokens' => $maxCompletionTokens ?? config('openai.max_tokens'),
            'reasoning_effort' => config('openai.reasoning_effort'),
            'stream' => true,
            'stream_options' => ['include_usage' => true],
        ];
        if ($safetyIdentifier !== null) {
            $payload['safety_identifier'] = $safetyIdentifier;
        }

        $response = Http::retry(
            $this->retryAttempts(),
            $this->retryDelayMilliseconds(),
            function (\Throwable $exception): bool {
                return $this->shouldRetry($exception);
            },
        )
            ->withOptions(['stream' => true])
            ->baseUrl(config('openai.base_url'))
            ->timeout(config('openai.timeout'))
            ->connectTimeout(5)
            ->withToken($apiKey)
            ->asJson()
            ->post('/chat/completions', $payload);

        if (! $response->successful()) {
            $response->throw();
        }

        $body = $response->toPsrResponse()->getBody();

        $buffer = '';
        $fullContent = '';
        $finishReason = null;
        $usage = [];
        $model = config('openai.model');
        $streamDone = false;

        while (! $body->eof() && ! $streamDone) {
            $chunk = $body->read(1024);

            if ($chunk === '') {
                continue;
            }

            $buffer .= $chunk;

            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $rawEvent = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                foreach (explode("\n", $rawEvent) as $line) {
                    $line = trim($line);

                    if ($line === '' || ! str_starts_with($line, 'data:')) {
                        continue;
                    }

                    $data = trim(substr($line, 5));

                    if ($data === '[DONE]') {
                        $streamDone = true;
                        break 2;
                    }

                    $json = json_decode($data, true);

                    if (! is_array($json)) {
                        continue;
                    }

                    $delta = $json['choices'][0]['delta']['content'] ?? null;

                    if (is_string($delta) && $delta !== '') {
                        $fullContent .= $delta;
                        $onDelta($delta);
                    }

                    $finishReason = $json['choices'][0]['finish_reason'] ?? $finishReason;
                    $model = $json['model'] ?? $model;

                    if (isset($json['usage'])) {
                        $usage = $json['usage'];
                    }
                }
            }
        }

        Log::info('OpenAI stream completion decoded', [
            'content_len' => mb_strlen($fullContent),
            'finish_reason' => $finishReason,
            // This value is supplied by OpenAI in the stream itself, so it is
            // authoritative metadata rather than the model's self-description.
            'model' => $model,
        ]);

        if ($fullContent === '' && $finishReason === 'length') {
            throw new \RuntimeException(
                'Câu trả lời bị cắt vì vượt giới hạn token (thường do prompt/Knowledge quá dài). '
                .'Hãy thử rút ngắn tin nhắn, hoặc liên hệ CIEC để tăng giới hạn.'
            );
        }

        return [
            'content' => $fullContent,
            'prompt_tokens' => (int) ($usage['prompt_tokens'] ?? 0),
            'completion_tokens' => (int) ($usage['completion_tokens'] ?? 0),
            'model' => $model,
        ];
    }

    private function shouldRetry(\Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if ($exception instanceof RequestException && $exception->response !== null) {
            $response = $exception->response;

            if ($response->serverError()) {
                return true;
            }

            return $response->status() === 403
                && $response->json('error.code') === 'model_not_found'
                && str_contains((string) $response->json('error.message'), 'does not have access to model');
        }

        return false;
    }

    private function retryAttempts(): int
    {
        return max(
            (int) config('openai.retry_times'),
            (int) config('openai.model_access_retry_times'),
        );
    }

    private function retryDelayMilliseconds(): int
    {
        return max(
            (int) config('openai.retry_delay_ms'),
            (int) config('openai.model_access_retry_delay_ms'),
        );
    }

    /**
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

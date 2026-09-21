<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class EmbeddingService
{
    /**
     * @return array<int, float>
     */
    public function embed(string $text): array
    {
        return $this->embedBatch([$text])[0] ?? [];
    }

    /**
     * Embed nhiều đoạn trong 1 request (rẻ + nhanh hơn gọi lẻ từng đoạn khi index Agent).
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     */
    public function embedBatch(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        if (! config('openai.rag_embeddings_enabled') || ! config('openai.api_key')) {
            return [];
        }

        try {
            $response = Http::retry(
                (int) config('openai.retry_times'),
                (int) config('openai.retry_delay_ms'),
                fn (Throwable $exception): bool => $exception instanceof ConnectionException,
            )
                ->baseUrl(config('openai.base_url'))
                ->timeout(60)
                ->connectTimeout(5)
                ->withToken(config('openai.api_key'))
                ->asJson()
                ->post('/embeddings', [
                    'model' => config('openai.embedding_model'),
                    'input' => array_values($texts),
                ]);
        } catch (Throwable $exception) {
            Log::warning('Embedding API connection failed; using keyword RAG.', [
                'exception' => $exception::class,
            ]);

            return [];
        }

        if (! $response->successful()) {
            Log::warning('Embedding API request failed; using keyword RAG.', [
                'status' => $response->status(),
            ]);

            return [];
        }

        $data = $response->json('data') ?? [];
        usort($data, fn ($a, $b) => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));

        return array_map(fn ($item) => $item['embedding'] ?? [], $data);
    }

    /**
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    public function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $len = min(count($a), count($b));

        for ($i = 0; $i < $len; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] ** 2;
            $normB += $b[$i] ** 2;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    /**
     * @return array<int, float>
     */
    public function embed(string $text): array
    {
        $apiKey = config('openai.api_key');

        if (! $apiKey) {
            return $this->mockEmbedding($text);
        }

        $response = Http::baseUrl(config('openai.base_url'))
            ->timeout(30)
            ->connectTimeout(5)
            ->withToken($apiKey)
            ->asJson()
            ->post('/embeddings', [
                'model' => config('openai.embedding_model'),
                'input' => $text,
            ]);

        if (! $response->successful()) {
            Log::warning('Embedding API failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        }

        return $response->json('data.0.embedding') ?? [];
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

        $apiKey = config('openai.api_key');

        if (! $apiKey) {
            return array_map(fn (string $t) => $this->mockEmbedding($t), $texts);
        }

        $response = Http::baseUrl(config('openai.base_url'))
            ->timeout(60)
            ->connectTimeout(5)
            ->withToken($apiKey)
            ->asJson()
            ->post('/embeddings', [
                'model' => config('openai.embedding_model'),
                'input' => array_values($texts),
            ]);

        if (! $response->successful()) {
            Log::warning('Embedding API batch failed', [
                'status' => $response->status(),
                'body' => $response->body(),
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

    /**
     * Vector giả — CHỈ dùng khi chưa cấu hình API key (mock/dev).
     *
     * @return array<int, float>
     */
    private function mockEmbedding(string $text): array
    {
        $hash = md5($text);
        $vector = [];

        for ($i = 0; $i < 32; $i++) {
            $pair = substr($hash, ($i * 2) % 32, 2);
            $vector[] = (hexdec($pair) / 255.0) - 0.5;
        }

        return $vector;
    }
}

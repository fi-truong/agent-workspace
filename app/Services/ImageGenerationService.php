<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ImageGenerationService
{
    public const MODEL_FLARE = 'gpt-image-2.5-flare';

    public const MODEL_SUNBURST = 'gpt-image-2.5-sunburst';

    /** @return array<string, array{label: string, description: string, supports_edits: bool}> */
    public static function modelOptions(): array
    {
        return [
            self::MODEL_FLARE => [
                'label' => 'Flare',
                'description' => 'Fast, high-quality image generation',
                'supports_edits' => false,
            ],
            self::MODEL_SUNBURST => [
                'label' => 'Sunburst',
                'description' => 'Best for precise image generation and editing',
                'supports_edits' => true,
            ],
        ];
    }

    /**
     * @param  array<int, array{bytes: string, name: string, mime: string}>  $referenceImages
     * @return array{image: string, prompt_tokens: int, completion_tokens: int}
     */
    public function generate(string $prompt, array $referenceImages = [], ?string $model = null): array
    {
        $apiKey = config('openai.api_key');
        if (! $apiKey) {
            throw new \RuntimeException('Image generation is not configured.');
        }

        $request = Http::baseUrl(config('openai.base_url'))
            ->timeout(config('openai.timeout'))
            ->connectTimeout(5)
            ->withToken($apiKey);

        $model ??= config('openai.image_model');

        if ($referenceImages === []) {
            $response = $request->asJson()->post('/images/generations', [
                'model' => $model,
                'prompt' => $prompt,
                'size' => config('openai.image_size'),
                'quality' => config('openai.image_quality'),
                'output_format' => 'png',
            ]);
        } else {
            foreach ($referenceImages as $image) {
                $request = $request->attach('image[]', $image['bytes'], $image['name'], [
                    'Content-Type' => $image['mime'],
                ]);
            }
            $response = $request->post('/images/edits', [
                'model' => $model,
                'prompt' => $prompt,
                'size' => config('openai.image_size'),
                'quality' => config('openai.image_quality'),
                'output_format' => 'png',
            ]);
        }

        if (! $response->successful()) {
            $response->throw();
        }

        $image = $response->json('data.0.b64_json');
        if (! is_string($image) || $image === '') {
            throw new \RuntimeException('The image API returned no image data.');
        }

        $usage = $response->json('usage') ?? [];

        return [
            'image' => $image,
            'prompt_tokens' => (int) ($usage['input_tokens'] ?? 0),
            'completion_tokens' => (int) ($usage['output_tokens'] ?? 0),
        ];
    }
}

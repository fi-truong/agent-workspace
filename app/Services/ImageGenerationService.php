<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ImageGenerationService
{
    public const MODEL_FLARE = 'gpt-image-2.5-flare';

    public const MODEL_SUNBURST = 'gpt-image-2.5-sunburst';

    private const MIN_IMAGE_PIXELS = 655360;

    private const MAX_IMAGE_PIXELS = 8294400;

    private const MAX_IMAGE_EDGE = 3840;

    private const IMAGE_DIMENSION_STEP = 16;

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
                'size' => $this->sizeForReferences($referenceImages),
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
                'size' => $this->sizeForReferences($referenceImages),
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

    /**
     * Use the first reference as the canvas for an edit while keeping output
     * dimensions close to the configured pixel budget.
     *
     * @param  array<int, array{bytes: string, name: string, mime: string}>  $referenceImages
     */
    private function sizeForReferences(array $referenceImages): string
    {
        $configuredSize = (string) config('openai.image_size', '1024x1024');
        if ($referenceImages === []) {
            return 'auto';
        }

        $imageInfo = @getimagesizefromstring($referenceImages[0]['bytes']);
        $sourceWidth = (int) ($imageInfo[0] ?? 0);
        $sourceHeight = (int) ($imageInfo[1] ?? 0);
        if ($sourceWidth < 1 || $sourceHeight < 1) {
            return $configuredSize;
        }

        $ratio = min(3.0, max(1 / 3, $sourceWidth / $sourceHeight));
        $targetPixels = $this->configuredPixelBudget($configuredSize);
        $targetPixels = min(self::MAX_IMAGE_PIXELS, max(self::MIN_IMAGE_PIXELS, $targetPixels));
        $targetPixels = min($targetPixels, (self::MAX_IMAGE_EDGE ** 2) / max($ratio, 1 / $ratio));

        $width = $this->nearestImageDimension(sqrt($targetPixels * $ratio));
        $height = $this->nearestImageDimension(sqrt($targetPixels / $ratio));

        return $width.'x'.$height;
    }

    private function configuredPixelBudget(string $configuredSize): int
    {
        if (preg_match('/^(\d+)x(\d+)$/', $configuredSize, $matches) !== 1) {
            return 1024 * 1024;
        }

        return (int) $matches[1] * (int) $matches[2];
    }

    private function nearestImageDimension(float $dimension): int
    {
        return max(
            self::IMAGE_DIMENSION_STEP,
            (int) round($dimension / self::IMAGE_DIMENSION_STEP) * self::IMAGE_DIMENSION_STEP,
        );
    }
}

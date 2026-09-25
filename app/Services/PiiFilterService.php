<?php

namespace App\Services;

use App\Services\Guardrail\RegexPiiFilter;

class PiiFilterService
{
    public function __construct(
        private readonly RegexPiiFilter $regexFilter = new RegexPiiFilter,
    ) {}

    /**
     * Scan content before it is stored in forms that do not pass through the
     * chat middleware (Support, comments, and image prompts). This deliberately
     * delegates to the same filter as chat so the policy cannot drift by route.
     *
     * @return array{flagged: bool, matches: array<string, array<int, string>>}
     */
    public function scan(string $text): array
    {
        $result = $this->regexFilter->filter($text, ['detect_only' => true]);
        $matches = [];

        foreach ($result['detected'] as $detected) {
            $matches[$detected['type']][] = $detected['original'];
        }

        return [
            'flagged' => $result['has_pii'],
            'matches' => $matches,
        ];
    }

    /**
     * Return a safe preview using the same replacement labels used in chat.
     */
    public function redact(string $text): string
    {
        return $this->regexFilter->filter($text)['filtered'];
    }
}

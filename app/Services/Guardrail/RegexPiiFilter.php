<?php

namespace App\Services\Guardrail;

class RegexPiiFilter
{
    /**
     * Patterns for Vietnamese PII detection.
     * Each pattern has: regex, replacement label, and description for logging.
     */
    private const PATTERNS = [
        'phone_vn' => [
            'pattern' => '/(?:\+84|84|0)(?:3[2-9]|5[689]|7[06-9]|8[1-9]|9[0-9])[0-9]{7}\b/',
            'replacement' => '[SĐT]',
            'description' => 'Vietnamese phone number',
        ],
        'email' => [
            'pattern' => '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/',
            'replacement' => '[EMAIL]',
            'description' => 'Email address',
        ],
        'student_id' => [
            'pattern' => '/\b(?:HS|SV|ST)[0-9]{6,8}\b/i',
            'replacement' => '[MÃ_HS]',
            'description' => 'Student ID (HS/SV/ST + 6-8 digits)',
        ],
        'cccd_12' => [
            'pattern' => '/\b[0-9]{12}\b/',
            'replacement' => '[CCCD]',
            'description' => '12-digit CCCD (Citizen ID)',
        ],
        'cmnd_9' => [
            'pattern' => '/\b[0-9]{9}\b/',
            'replacement' => '[CMND]',
            'description' => '9-digit CMND (old ID card)',
        ],
        'address_specific' => [
            'pattern' => '/\b(?:số|ngõ|ngách|ngõ|khu|khối|tổ|lô|khu phố|khu dân cư|khu tái định cư|phố|đường|ngã|ngõ|hẻm)\s+[0-9A-Za-zÀ-ỹ\s\-\/]{5,}\b/iu',
            'replacement' => '[ĐỊA_CHỈ]',
            'description' => 'Specific Vietnamese address pattern',
        ],
        'bank_account' => [
            'pattern' => '/\b[0-9]{10,19}\b/',
            'replacement' => '[SỐ_TK]',
            'description' => 'Bank account number (10-19 digits)',
        ],
        'passport' => [
            'pattern' => '/\b[A-Z]{1,2}[0-9]{7,8}\b/',
            'replacement' => '[HỘ_CHIẾU]',
            'description' => 'Vietnamese passport format',
        ],
        'license_plate' => [
            'pattern' => '/\b[0-9]{2}[A-Z]?\-[0-9]{4,5}\b/',
            'replacement' => '[BIỂN_SỐ]',
            'description' => 'Vietnamese license plate',
        ],
    ];

    /**
     * Filter PII from text using regex patterns.
     *
     * @param string $text Input text to filter
     * @param array $options Options: 'replace' (bool), 'detect_only' (bool)
     * @return array ['filtered' => string, 'detected' => array, 'has_pii' => bool]
     */
    public function filter(string $text, array $options = []): array
    {
        $replace = $options['replace'] ?? true;
        $detectOnly = $options['detect_only'] ?? false;

        $filtered = $text;
        $detected = [];

        foreach (self::PATTERNS as $key => $config) {
            $matches = [];
            $matchCount = preg_match_all($config['pattern'], $text, $matches);

            if ($matchCount > 0) {
                $uniqueMatches = array_unique($matches[0]);
                foreach ($uniqueMatches as $match) {
                    $detected[] = [
                        'type' => $key,
                        'description' => $config['description'],
                        'original' => $match,
                        'replacement' => $config['replacement'],
                    ];
                }

                if ($replace && !$detectOnly) {
                    $filtered = preg_replace($config['pattern'], $config['replacement'], $filtered);
                }
            }
        }

        return [
            'filtered' => $filtered,
            'detected' => $detected,
            'has_pii' => !empty($detected),
        ];
    }

    /**
     * Quick check if text contains any PII (for early exit).
     */
    public function hasPii(string $text): bool
    {
        foreach (self::PATTERNS as $config) {
            if (preg_match($config['pattern'], $text)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get all pattern configs (for testing/inspection).
     */
    public static function getPatterns(): array
    {
        return self::PATTERNS;
    }
}
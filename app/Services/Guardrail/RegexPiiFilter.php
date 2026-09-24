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

            'pattern' => '/(?<![A-Za-z0-9À-ỹ])[0-9]{12}(?![0-9])/iu',

            'replacement' => '[CCCD]',

            'description' => '12-digit CCCD (Citizen ID)',

        ],

        'cmnd_9' => [

            'pattern' => '/(?<![A-Za-z0-9À-ỹ])[0-9]{9}(?![0-9])/iu',

            'replacement' => '[CMND]',

            'description' => '9-digit CMND (old ID card)',

        ],

        'address_specific' => [

            'pattern' => '/\b(?:số|ngõ|ngách|khu|khối|tổ|lô|khu phố|khu dân cư|khu tái định cư|phố|đường|ngã|hẻm)\s+[0-9A-Za-zÀ-ỹ\s\-\/]{5,}\b/iu',

            'replacement' => '[ĐỊA_CHỈ]',

            'description' => 'Specific Vietnamese address pattern',

        ],

        'bank_account' => [

            'pattern' => '/(?<![A-Za-z0-9À-ỹ])[0-9]{12,19}(?![0-9])/iu',

            'replacement' => '[SỐ_TK]',

            'description' => 'Bank account number (12-19 digits)',

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
 
    /** @var array<int, string> domain đã lowercase, không dấu @ */

    private readonly array $emailDomainWhitelist;
 
    public function __construct(?array $emailDomainWhitelist = null)

    {

        if ($emailDomainWhitelist !== null) {

            $this->emailDomainWhitelist = array_map('strtolower', $emailDomainWhitelist);
 
            return;

        }
 
        $raw = (string) config('pii.email_domain_whitelist', 'lsts.edu.vn');
 
        $this->emailDomainWhitelist = array_values(array_filter(array_map(

            fn (string $d) => strtolower(trim($d)),

            explode(',', $raw),

        )));

    }
 
    /**

     * Filter PII from text using regex patterns.

     *

     * @param  string  $text  Input text to filter

     * @param  array  $options  Options: 'replace' (bool), 'detect_only' (bool)

     * @return array ['filtered' => string, 'detected' => array, 'has_pii' => bool]

     */

    public function filter(string $text, array $options = []): array

    {

        $replace = $options['replace'] ?? true;

        $detectOnly = $options['detect_only'] ?? false;
 
        $filtered = $text;

        $detected = [];

        $seenOrigins = [];
 
        foreach (self::PATTERNS as $key => $config) {

            $matches = [];

            $matchCount = preg_match_all($config['pattern'], $text, $matches);
 
            if ($matchCount === 0) {

                continue;

            }
 
            $uniqueMatches = array_unique($matches[0]);
 
            foreach ($uniqueMatches as $match) {

                if (isset($seenOrigins[$match])) {

                    continue;

                }
 
                // Email nội bộ trường đặt theo chức vụ (vd hr@lsts.edu.vn) → không tính là PII.

                if ($key === 'email' && $this->isWhitelistedEmail($match)) {

                    continue;

                }
 
                $seenOrigins[$match] = true;

                $detected[] = [

                    'type' => $key,

                    'description' => $config['description'],

                    'original' => $match,

                    'replacement' => $config['replacement'],

                ];

            }
 
            if (! $replace || $detectOnly) {

                continue;

            }
 
            if ($key === 'email') {

                $filtered = preg_replace_callback(

                    $config['pattern'],

                    fn (array $m) => $this->isWhitelistedEmail($m[0]) ? $m[0] : $config['replacement'],

                    $filtered,

                );
 
                continue;

            }
 
            $filtered = preg_replace($config['pattern'], $config['replacement'], $filtered);

        }

        $studentIdentifierRisks = $this->studentIdentifierRisks($text);
        foreach ($studentIdentifierRisks as $type => $identifiers) {
            $detected[] = [
                'type' => $type,
                'description' => $type === 'student_id_batch'
                    ? 'Batch of ten or more seven-digit student identifiers'
                    : 'Seven-digit student identifier in student-record context',
                'original' => implode(', ', array_slice($identifiers, 0, 10)),
                'replacement' => '[MÃ_HS]',
            ];
        }

        if ($replace && ! $detectOnly && $studentIdentifierRisks !== []) {
            $filtered = preg_replace('/(?<!\d)\d{7}(?!\d)/', '[MÃ_HS]', $filtered);
        }
 
        return [

            'filtered' => $filtered,

            'detected' => $detected,

            'has_pii' => ! empty($detected),

        ];

    }
 
    /**

     * Quick check if text contains any PII (for early exit).

     */

    public function hasPii(string $text): bool

    {

        foreach (self::PATTERNS as $key => $config) {

            if (! preg_match_all($config['pattern'], $text, $matches)) {

                continue;

            }
 
            if ($key !== 'email') {

                return true;

            }
 
            foreach ($matches[0] as $match) {

                if (! $this->isWhitelistedEmail($match)) {

                    return true;

                }

            }

        }

        if ($this->studentIdentifierRisks($text) !== []) {
            return true;
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
 
    private function isWhitelistedEmail(string $email): bool

    {

        $at = strrchr($email, '@');
 
        if ($at === false) {

            return false;

        }
 
        $domain = strtolower(substr($at, 1));
 
        return in_array($domain, $this->emailDomainWhitelist, true);

    }

    /** @return array<string, array<int, string>> */
    private function studentIdentifierRisks(string $text): array
    {
        preg_match_all('/(?<!\d)\d{7}(?!\d)/', $text, $found);
        $identifiers = array_values(array_unique($found[0] ?? []));

        if ($identifiers === []) {
            return [];
        }

        $risks = [];
        if (count($identifiers) >= 10) {
            $risks['student_id_batch'] = $identifiers;
        }

        $studentRecordLabels = '/(?:\bMSSV\b|\bMSHS\b|\bstudent\s*id\b|họ\s*tên|ho\s*ten|\blớp\b|\blop\b|\bđiểm\b|\bdiem\b)/iu';
        if (preg_match($studentRecordLabels, $text) === 1) {
            $risks['student_record_context'] = $identifiers;
        }

        return $risks;
    }

}

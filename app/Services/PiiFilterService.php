<?php

namespace App\Services;

class PiiFilterService
{
    /** @var array<int, string> */
    private array $emailDomainWhitelist;

    /**
     * Các mẫu PII cơ bản cần chặn — Lớp 1 (regex, chạy phía server, không tốn API call).
     * Đây là lớp lọc nhanh đầu tiên; Lớp 2 (OpenAI Moderation API) sẽ bổ sung sau
     * khi có API key, xử lý các trường hợp regex không bắt được (vd tên riêng, ngữ cảnh nhạy cảm).
     */
    protected array $patterns = [
        'email' => '/\b[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}\b/i',
        'phone_vn' => '/(?:\+84|0)(?:\d[\s.-]?){9,10}/',
        'student_id' => '/\b(?:HS|SV|ID)[-_]?\d{4,10}\b/i',
        // Số CCCD/CMND Việt Nam (9 hoặc 12 chữ số liên tiếp, không cách quãng)
        'national_id' => '/\b\d{9}\b|\b\d{12}\b/',
    ];

    public function __construct()
    {
        $domains = (string) config('pii.email_domain_whitelist', 'lsts.edu.vn');

        $this->emailDomainWhitelist = array_values(array_filter(array_map(
            fn (string $domain) => strtolower(trim($domain)),
            explode(',', $domains),
        )));
    }

    /**
     * Kiểm tra văn bản có chứa PII theo các mẫu trên không.
     *
     * @return array{flagged: bool, matches: array<string, array<int, string>>}
     */
    public function scan(string $text): array
    {
        $matches = [];

        foreach ($this->patterns as $type => $pattern) {
            if (preg_match_all($pattern, $text, $found)) {
                $matchesForType = $type === 'email'
                    ? array_values(array_filter($found[0], fn (string $email) => ! $this->isWhitelistedEmail($email)))
                    : $found[0];

                if ($matchesForType !== []) {
                    $matches[$type] = $matchesForType;
                }
            }
        }

        foreach ($this->studentIdentifierRisks($text) as $type => $identifiers) {
            $matches[$type] = $identifiers;
        }

        return [
            'flagged' => count($matches) > 0,
            'matches' => $matches,
        ];
    }

    /**
     * Trả về văn bản đã được che (redact) các đoạn PII phát hiện được,
     * dùng khi muốn hiển thị cảnh báo kèm bản xem trước đã ẩn thông tin.
     */
    public function redact(string $text): string
    {
        $redacted = $text;

        foreach ($this->patterns as $type => $pattern) {
            if ($type === 'email') {
                $redacted = preg_replace_callback(
                    $pattern,
                    fn (array $match) => $this->isWhitelistedEmail($match[0]) ? $match[0] : "[$type đã bị ẩn]",
                    $redacted,
                );

                continue;
            }

            $redacted = preg_replace($pattern, "[$type đã bị ẩn]", $redacted);
        }

        if ($this->studentIdentifierRisks($text) !== []) {
            $redacted = preg_replace('/(?<!\d)\d{7}(?!\d)/', '[mã số học sinh đã bị ẩn]', $redacted);
        }

        return $redacted;
    }

    /**
     * LSTS student numbers contain exactly seven digits. A lone seven-digit
     * number is allowed because it can be a harmless reference number. It
     * becomes sensitive in a large list or in student-record context.
     *
     * @return array<string, array<int, string>>
     */
    private function studentIdentifierRisks(string $text): array
    {
        preg_match_all('/(?<!\d)\d{7}(?!\d)/', $text, $found);
        $identifiers = array_values(array_unique($found[0] ?? []));

        if ($identifiers === []) {
            return [];
        }

        $risks = [];
        if (count($identifiers) >= 10) {
            $risks['student_id_batch'] = array_slice($identifiers, 0, 10);
        }

        $studentRecordLabels = '/(?:\bMSSV\b|\bMSHS\b|\bstudent\s*id\b|họ\s*tên|ho\s*ten|\blớp\b|\blop\b|\bđiểm\b|\bdiem\b)/iu';
        if (preg_match($studentRecordLabels, $text) === 1) {
            $risks['student_record_context'] = array_slice($identifiers, 0, 10);
        }

        return $risks;
    }

    private function isWhitelistedEmail(string $email): bool
    {
        $domain = strrchr($email, '@');

        return $domain !== false
            && in_array(strtolower(substr($domain, 1)), $this->emailDomainWhitelist, true);
    }
}

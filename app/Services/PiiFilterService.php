<?php

namespace App\Services;

class PiiFilterService
{
    /**
     * Các mẫu PII cơ bản cần chặn — Lớp 1 (regex, chạy phía server, không tốn API call).
     * Đây là lớp lọc nhanh đầu tiên; Lớp 2 (OpenAI Moderation API) sẽ bổ sung sau
     * khi có API key, xử lý các trường hợp regex không bắt được (vd tên riêng, ngữ cảnh nhạy cảm).
     */
    protected array $patterns = [
        'email' => '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
        'phone_vn' => '/(?:\+84|0)(?:\d[\s.-]?){9,10}/',
        'student_id' => '/\b(?:HS|SV|ID)[-_]?\d{4,10}\b/i',
        // Số CCCD/CMND Việt Nam (9 hoặc 12 chữ số liên tiếp, không cách quãng)
        'national_id' => '/\b\d{9}\b|\b\d{12}\b/',
    ];

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
                $matches[$type] = $found[0];
            }
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
            $redacted = preg_replace($pattern, "[$type đã bị ẩn]", $redacted);
        }

        return $redacted;
    }
}

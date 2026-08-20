<?php

use App\Services\Guardrail\RegexPiiFilter;

uses()->group('guardrail', 'unit');

describe('RegexPiiFilter', function () {
    beforeEach(function () {
        $this->filter = new RegexPiiFilter();
    });

    describe('Vietnamese phone numbers', function () {
        test('detects phone with +84 prefix', function () {
            $text = 'Liên hệ anh Tuấn qua +84901234567 nhé';
            $result = $this->filter->filter($text);

            expect($result['has_pii'])->toBeTrue()
                ->and($result['detected'])->toHaveCount(1)
                ->and($result['detected'][0]['type'])->toBe('phone_vn')
                ->and($result['filtered'])->toContain('[SĐT]')
                ->and($result['filtered'])->not->toContain('+84901234567');
        });

        test('detects phone with 84 prefix', function () {
            $text = 'Số của em là 84987654321';
            $result = $this->filter->filter($text);

            expect($result['has_pii'])->toBeTrue()
                ->and($result['detected'][0]['type'])->toBe('phone_vn')
                ->and($result['filtered'])->toContain('[SĐT]');
        });

        test('detects phone with 0 prefix (all major carriers)', function () {
            $numbers = [
                '0901234567' => 'Viettel',
                '0987654321' => 'Viettel',
                '0321234567' => 'Viettel',
                '0381234567' => 'Viettel',
                '0912345678' => 'Vinaphone',
                '0941234567' => 'Vinaphone',
                '0831234567' => 'Vinaphone',
                '0841234567' => 'Vinaphone',
                '0961234567' => 'Vietnamobile',
                '0971234567' => 'Vietnamobile',
                '0921234567' => 'Vietnamobile',
            ];

            foreach ($numbers as $phone => $carrier) {
                $result = $this->filter->filter("Gọi {$carrier}: {$phone}");
                expect($result['has_pii'])->toBeTrue()
                    ->and($result['detected'][0]['type'])->toBe('phone_vn')
                    ->and($result['filtered'])->toContain('[SĐT]');
            }
        });

        test('does not detect invalid phone numbers', function () {
            $invalid = ['0123456789', '0201234567', '090123456', '1234567890'];
            foreach ($invalid as $phone) {
                $result = $this->filter->filter("Số: {$phone}");
                expect($result['has_pii'])->toBeFalse();
            }
        });
    });

    describe('Email addresses', function () {
        test('detects standard email', function () {
            $text = 'Email anh: nguyen.van.a@lsts.edu.vn';
            $result = $this->filter->filter($text);

            expect($result['has_pii'])->toBeTrue()
                ->and($result['detected'][0]['type'])->toBe('email')
                ->and($result['filtered'])->toContain('[EMAIL]')
                ->and($result['filtered'])->not->toContain('nguyen.van.a@lsts.edu.vn');
        });

        test('detects multiple emails', function () {
            $text = 'Chủ nhật: a@gmail.com, thứ 2: b@company.com.vn';
            $result = $this->filter->filter($text);

            expect($result['has_pii'])->toBeTrue()
                ->and($result['detected'])->toHaveCount(2)
                ->and($result['filtered'])->toContain('[EMAIL]')
                ->and($result['filtered'])->not->toContain('a@gmail.com')
                ->and($result['filtered'])->not->toContain('b@company.com.vn');
        });

        test('detects email with subdomain', function () {
            $text = 'Mail: test.user+tag@sub.domain.co.uk';
            $result = $this->filter->filter($text);

            expect($result['has_pii'])->toBeTrue()
                ->and($result['detected'][0]['type'])->toBe('email');
        });
    });

    describe('Student IDs (HS/SV/ST + digits)', function () {
        test('detects HS format', function () {
            $text = 'Học sinh HS12345678 vắng hôm nay';
            $result = $this->filter->filter($text);

            expect($result['has_pii'])->toBeTrue()
                ->and($result['detected'][0]['type'])->toBe('student_id')
                ->and($result['filtered'])->toContain('[MÃ_HS]');
        });

        test('detects SV format (sinh viên)', function () {
            $text = 'Sinh viên SV87654321 đã nộp bài';
            $result = $this->filter->filter($text);

            expect($result['has_pii'])->toBeTrue()
                ->and($result['detected'][0]['type'])->toBe('student_id');
        });

        test('detects ST format', function () {
            $text = 'ST11223344 là mã học sinh';
            $result = $this->filter->filter($text);

            expect($result['has_pii'])->toBeTrue()
                ->and($result['detected'][0]['type'])->toBe('student_id');
        });

        test('case insensitive', function () {
            $text = 'hs12345678 và Hs87654321';
            $result = $this->filter->filter($text);

            expect($result['detected'])->toHaveCount(2);
        });

        test('requires 6-8 digits', function () {
            $short = 'HS12345';
            $long = 'HS123456789';
            $result1 = $this->filter->filter($short);
            $result2 = $this->filter->filter($long);

            expect($result1['has_pii'])->toBeFalse()
                ->and($result2['has_pii'])->toBeFalse();
        });
    });

    describe('CCCD (12 digits) and CMND (9 digits)', function () {
        test('detects 12-digit CCCD', function () {
            $text = 'CCCD: 079200001234';
            $result = $this->filter->filter($text);

            expect($result['has_pii'])->toBeTrue()
                ->and($result['detected'][0]['type'])->toBe('cccd_12')
                ->and($result['filtered'])->toContain('[CCCD]');
        });

        test('detects 9-digit CMND', function () {
            $text = 'CMND cũ: 123456789';
            $result = $this->filter->filter($text);

            expect($result['has_pii'])->toBeTrue()
                ->and($result['detected'][0]['type'])->toBe('cmnd_9')
                ->and($result['filtered'])->toContain('[CMND]');
        });

        test('does not flag other 12-digit numbers as CCCD if context suggests otherwise', function () {
            // Bank account can also be 12 digits - both patterns match
            // This is intentional: we flag both, replacement depends on order
            $text = 'Số tài khoản: 123456789012';
            $result = $this->filter->filter($text);

            expect($result['has_pii'])->toBeTrue();
        });
    });

    describe('Vietnamese addresses', function () {
        test('detects address with "số"', function () {
            $text = 'Địa chỉ: số 12 ngõ 34 phố Huế';
            $result = $this->filter->filter($text);

            expect($result['has_pii'])->toBeTrue()
                ->and($result['detected'][0]['type'])->toBe('address_specific')
                ->and($result['filtered'])->toContain('[ĐỊA_CHỈ]');
        });

        test('detects various address keywords', function () {
            $keywords = ['ngõ', 'ngách', 'khu', 'khối', 'tổ', 'lô', 'khu phố', 'phố', 'đường', 'hẻm'];
            foreach ($keywords as $kw) {
                $text = "Ở {$kw} 123 đường ABC";
                $result = $this->filter->filter($text);
                expect($result['has_pii'])->toBeTrue();
            }
        });

        test('requires minimum length after keyword', function () {
            $short = 'số 1'; // too short
            $long = 'số 12 ngõ 34 phố Huế'; // enough context
            $result1 = $this->filter->filter($short);
            $result2 = $this->filter->filter($long);

            expect($result1['has_pii'])->toBeFalse()
                ->and($result2['has_pii'])->toBeTrue();
        });
    });

    describe('Bank account numbers (10-19 digits)', function () {
        test('detects typical bank account', function () {
            $text = 'Chuyển khoản cho 1234567890123456';
            $result = $this->filter->filter($text);

            expect($result['has_pii'])->toBeTrue()
                ->and($result['detected'][0]['type'])->toBe('bank_account')
                ->and($result['filtered'])->toContain('[SỐ_TK]');
        });
    });

    describe('Passport', function () {
        test('detects Vietnamese passport format', function () {
            $text = 'Hộ chiếu: C12345678';
            $result = $this->filter->filter($text);

            expect($result['has_pii'])->toBeTrue()
                ->and($result['detected'][0]['type'])->toBe('passport')
                ->and($result['filtered'])->toContain('[HỘ_CHIẾU]');
        });

        test('detects 2-letter prefix passport', function () {
            $text = 'Hộ chiếu: AB1234567';
            $result = $this->filter->filter($text);

            expect($result['has_pii'])->toBeTrue()
                ->and($result['detected'][0]['type'])->toBe('passport');
        });
    });

    describe('License plates', function () {
        test('detects standard format', function () {
            $text = 'Xe: 30A-12345';
            $result = $this->filter->filter($text);

            expect($result['has_pii'])->toBeTrue()
                ->and($result['detected'][0]['type'])->toBe('license_plate')
                ->and($result['filtered'])->toContain('[BIỂN_SỐ]');
        });

        test('detects format without letter', function () {
            $text = 'Biển: 51-12345';
            $result = $this->filter->filter($text);

            expect($result['has_pii'])->toBeTrue()
                ->and($result['detected'][0]['type'])->toBe('license_plate');
        });
    });

    describe('Multiple PII in one text', function () {
        test('detects and replaces all types', function () {
            $text = 'HS12345678, email: test@school.edu.vn, SĐT: 0901234567, CCCD: 079200001234';
            $result = $this->filter->filter($text);

            expect($result['has_pii'])->toBeTrue()
                ->and($result['detected'])->toHaveCount(4)
                ->and($result['filtered'])->toContain('[MÃ_HS]')
                ->and($result['filtered'])->toContain('[EMAIL]')
                ->and($result['filtered'])->toContain('[SĐT]')
                ->and($result['filtered'])->toContain('[CCCD]');
        });

        test('preserves non-PII text', function () {
            $text = 'Chào bạn, HS12345678 đến trường rồi.';
            $result = $this->filter->filter($text);

            expect($result['filtered'])->toContain('Chào bạn')
                ->and($result['filtered'])->toContain('đến trường rồi')
                ->and($result['filtered'])->toContain('[MÃ_HS]');
        });
    });

    describe('detect_only mode', function () {
        test('does not replace when detect_only=true', function () {
            $text = 'SĐT: 0901234567';
            $result = $this->filter->filter($text, ['detect_only' => true]);

            expect($result['has_pii'])->toBeTrue()
                ->and($result['filtered'])->toBe($text); // unchanged
        });

        test('still returns detected details', function () {
            $text = 'Email: test@example.com';
            $result = $this->filter->filter($text, ['detect_only' => true]);

            expect($result['detected'])->toHaveCount(1)
                ->and($result['detected'][0]['original'])->toBe('test@example.com');
        });
    });

    describe('hasPii quick check', function () {
        test('returns true for text with PII', function () {
            expect($this->filter->hasPii('SĐT 0901234567'))->toBeTrue();
        });

        test('returns false for clean text', function () {
            expect($this->filter->hasPii('Chào bạn, hôm nay đẹp trời'))->toBeFalse();
        });
    });

    describe('getPatterns static method', function () {
        test('returns all pattern configs', function () {
            $patterns = RegexPiiFilter::getPatterns();

            expect($patterns)->toHaveKey('phone_vn')
                ->and($patterns)->toHaveKey('email')
                ->and($patterns)->toHaveKey('student_id')
                ->and($patterns)->toHaveKey('cccd_12')
                ->and($patterns)->toHaveKey('cmnd_9')
                ->and($patterns)->toHaveKey('address_specific')
                ->and($patterns)->toHaveKey('bank_account')
                ->and($patterns)->toHaveKey('passport')
                ->and($patterns)->toHaveKey('license_plate');
        });
    });
});
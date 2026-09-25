<?php

use App\Services\Guardrail\RegexPiiFilter;

uses()->group('guardrail', 'unit');

describe('RegexPiiFilter', function () {
    beforeEach(function () {
        $this->filter = new RegexPiiFilter;
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
            // 10 chữ số không thuộc prefix nhà mạng VN (không phải SĐT, không phải CMND/CCCD)
            $invalid = ['0123456789', '0201234567', '1234567890'];
            foreach ($invalid as $phone) {
                $result = $this->filter->filter("Số: {$phone}");
                expect($result['has_pii'])->toBeFalse();
            }
        });
    });

    describe('Email addresses', function () {
        test('allows an internal LSTS email', function () {
            $text = 'Email anh: nguyen.van.a@lsts.edu.vn';
            $result = $this->filter->filter($text);

            expect($result['has_pii'])->toBeFalse()
                ->and($result['filtered'])->toBe($text);
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

    describe('Student IDs (HS/SV + digits)', function () {
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

        test('does not treat a generic ST-prefixed work code as a student ID', function () {
            $text = 'Sự kiện ST20260925 sẽ diễn ra vào tháng sau';
            $result = $this->filter->filter($text);

            expect($result['has_pii'])->toBeFalse()
                ->and($result['filtered'])->toBe($text);
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

        test('does not flag a generic 9- or 12-digit work reference', function () {
            $text = 'Mã hồ sơ 123456789, mã tham chiếu 202609241234';
            $result = $this->filter->filter($text);

            expect($result['has_pii'])->toBeFalse()
                ->and($result['filtered'])->toBe($text);
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
            $addresses = [
                'Ở ngõ 123 phố Huế',
                'Ở ngách 123, đường Nguyễn Văn Cừ',
                'Ở khu 123, phường An Phú',
                'Ở khối 123, xã Bình Minh',
                'Ở tổ 3, quận 1',
                'Ở lô 123, đường ABC',
                'Ở khu phố 3, phường Linh Trung',
                'Ở hẻm 123, đường ABC',
                'Ở khu dân cư An Phú, phường Bình An',
            ];
            foreach ($addresses as $text) {
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

        test('does not flag ordinary phrases containing address-area words', function () {
            $text = 'Hai năm gần đây, bạn tiếp tục tham gia vào hội đồng học sinh và tổ chức tất cả sự kiện mang tính chất toàn trường ở khu vực chung.';
            $result = $this->filter->filter($text);

            expect($result['has_pii'])->toBeFalse()
                ->and($result['filtered'])->toBe($text);
        });

        test('does not flag a capitalized place name or document title as an address', function () {
            $text = 'Soạn bài giới thiệu Phố Cổ Hội An và phân tích Đường Lối Giáo dục.';
            $result = $this->filter->filter($text);

            expect($result['has_pii'])->toBeFalse()
                ->and($result['filtered'])->toBe($text);
        });

        test('does not flag ordinary numbered school or work references as an address', function () {
            $text = 'Câu số 12 dành cho khối 12; Tổ 3 chuẩn bị phần thuyết trình.';
            $result = $this->filter->filter($text);

            expect($result['has_pii'])->toBeFalse()
                ->and($result['filtered'])->toBe($text);
        });

        test('does not flag a generic residential-area description as a specific address', function () {
            $text = 'Khu dân cư mới gần trường cần được khảo sát thêm.';
            $result = $this->filter->filter($text);

            expect($result['has_pii'])->toBeFalse()
                ->and($result['filtered'])->toBe($text);
        });
    });

    describe('Bank account numbers (10-19 digits)', function () {
        test('detects typical bank account', function () {
            $text = 'Số tài khoản: 1234567890123456';
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

        test('detects a batch of ten seven-digit student identifiers', function () {
            $text = implode(', ', range(1_000_001, 1_000_010));
            $result = $this->filter->filter($text);

            expect($result['has_pii'])->toBeTrue()
                ->and(array_column($result['detected'], 'type'))->toContain('student_id_batch')
                ->and($result['filtered'])->toContain('[MÃ_HS]')
                ->and($this->filter->hasPii($text))->toBeTrue();
        });

        test('detects a seven-digit student identifier in student-record context', function () {
            $result = $this->filter->filter('Họ tên: Test Student; MSSV: 1234567');

            expect($result['has_pii'])->toBeTrue()
                ->and(array_column($result['detected'], 'type'))->toContain('student_record_context');
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

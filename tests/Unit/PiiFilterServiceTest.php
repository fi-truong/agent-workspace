<?php

use App\Services\PiiFilterService;

uses()->group('guardrail', 'unit');

beforeEach(function () {
    $this->filter = new PiiFilterService;
});

it('flags text containing an external email', function () {
    $result = $this->filter->scan('Liên hệ phụ huynh qua email nguyenvana@gmail.com nhé.');

    expect($result['flagged'])->toBeTrue();
    expect($result['matches'])->toHaveKey('email');
});

it('flags text containing a vietnamese phone number', function () {
    $result = $this->filter->scan('Số điện thoại phụ huynh là 0912345678.');

    expect($result['flagged'])->toBeTrue();
    expect($result['matches'])->toHaveKey('phone_vn');
});

it('flags text containing a student id', function () {
    $result = $this->filter->scan('Em học sinh mã HS-002345 bị điểm kém.');

    expect($result['flagged'])->toBeTrue();
    expect($result['matches'])->toHaveKey('student_id');
});

it('does not flag clean text', function () {
    $result = $this->filter->scan('Hãy giúp tôi soạn kế hoạch bài giảng môn Toán lớp 7.');

    expect($result['flagged'])->toBeFalse();
    expect($result['matches'])->toBeEmpty();
});

it('allows an internal LSTS email', function () {
    $text = 'Email công việc của tôi là abc@lsts.edu.vn';

    expect($this->filter->scan($text)['flagged'])->toBeFalse()
        ->and($this->filter->redact($text))->toBe($text);
});

it('allows a single numeric LSTS student email without student-record context', function () {
    $text = 'Gửi thông báo đến 1234567@lsts.edu.vn.';

    expect($this->filter->scan($text)['flagged'])->toBeFalse();
});

it('flags a batch of ten seven-digit LSTS student identifiers', function () {
    $identifiers = implode(', ', range(1_000_001, 1_000_010));
    $result = $this->filter->scan('Danh sách liên hệ: '.$identifiers);

    expect($result['flagged'])->toBeTrue()
        ->and($result['matches'])->toHaveKey('student_id_batch')
        ->and($this->filter->redact('Danh sách liên hệ: '.$identifiers))->not->toContain('1000001');
});

it('flags a seven-digit identifier in student-record context', function () {
    $result = $this->filter->scan('MSSV: 1234567; Điểm: 9.0');

    expect($result['flagged'])->toBeTrue()
        ->and($result['matches'])->toHaveKey('student_record_context');
});

it('redacts an external email', function () {
    $redacted = $this->filter->redact('Email của tôi là abc@gmail.com');

    expect($redacted)->not->toContain('abc@gmail.com');
    expect($redacted)->toContain('[EMAIL]');
});

it('uses the same complete rules as chat', function () {
    $result = $this->filter->scan('Hộ chiếu: C12345678; Số tài khoản: 1234567890123456');

    expect($result['flagged'])->toBeTrue()
        ->and($result['matches'])->toHaveKeys(['passport', 'bank_account']);
});

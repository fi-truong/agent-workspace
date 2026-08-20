<?php

use App\Services\PiiFilterService;

beforeEach(function () {
    $this->filter = new PiiFilterService();
});

it('flags text containing an email', function () {
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

it('redacts detected pii from text', function () {
    $redacted = $this->filter->redact('Email của tôi là abc@lsts.edu.vn');

    expect($redacted)->not->toContain('abc@lsts.edu.vn');
    expect($redacted)->toContain('[email đã bị ẩn]');
});

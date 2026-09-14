<?php

use App\Services\ChatCompletionService;
use App\Services\Guardrail\RegexPiiFilter;
use App\Services\OpenAIClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses()->group('openai', 'unit');

// preventStrayRequests được gọi sau Http::fake() trong từng test (không gọi trước khi fake).

it('calls OpenAI chat completions with messages when no PII', function () {
    config(['openai.api_key' => 'sk-test']);

    Http::fake([
        'https://api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'Xin chào!']]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
            'model' => 'gpt-5.6-luna',
        ]),
    ]);
    Http::preventStrayRequests();

    $service = new ChatCompletionService(new OpenAIClient, new RegexPiiFilter);
    $result = $service->complete([
        ['role' => 'user', 'content' => 'Chào bạn'],
    ]);

    expect($result['content'])->toBe('Xin chào!')
        ->and($result['prompt_tokens'])->toBe(10)
        ->and($result['completion_tokens'])->toBe(5);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.openai.com/v1/chat/completions'
        && $request->hasHeader('Authorization', 'Bearer sk-test'));
});

it('sends system prompt when provided', function () {
    config(['openai.api_key' => 'sk-test']);

    Http::fake([
        'https://api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'OK']]],
            'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
            'model' => 'gpt-5.6-luna',
        ]),
    ]);
    Http::preventStrayRequests();

    $service = new ChatCompletionService(new OpenAIClient, new RegexPiiFilter);
    $service->complete(
        [['role' => 'user', 'content' => 'Hi']],
        'Bạn là giáo viên toán.',
    );

    Http::assertSent(function (Request $request) {
        $payload = $request->data();
        $system = $payload['messages'][0] ?? null;

        return ($system['role'] ?? '') === 'system'
            && ($system['content'] ?? '') === 'Bạn là giáo viên toán.';
    });
});

it('filters PII before sending to OpenAI', function () {
    config(['openai.api_key' => 'sk-test']);

    Http::fake([
        'https://api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'OK']]],
            'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
            'model' => 'gpt-5.6-luna',
        ]),
    ]);
    Http::preventStrayRequests();

    $service = new ChatCompletionService(new OpenAIClient, new RegexPiiFilter);
    $service->complete([['role' => 'user', 'content' => 'Liên hệ qua email test@school.edu.vn']]);

    Http::assertSent(function (Request $request) {
        $payload = $request->data();
        $content = $payload['messages'][0]['content'] ?? '';

        return ! str_contains($content, 'test@school.edu.vn')
            && str_contains($content, '[EMAIL]');
    });
});

it('uses fallback mock when no API key configured', function () {
    config(['openai.api_key' => null]);

    $service = new ChatCompletionService(new OpenAIClient, new RegexPiiFilter);
    $result = $service->complete([
        ['role' => 'user', 'content' => 'Chào'],
        ['role' => 'assistant', 'content' => 'Xin chào!'],
        ['role' => 'user', 'content' => 'Hôm nay thế nào?'],
    ]);

    expect($result['content'])->toContain('giả lập')
        ->and($result['content'])->toContain('Hôm nay thế nào?');
});

it('throws mapped user-friendly message when API call fails', function () {
    config(['openai.api_key' => 'sk-test']);

    Http::fake([
        'https://api.openai.com/*' => Http::response([
            'error' => ['code' => 'insufficient_quota', 'message' => 'You exceeded your current quota.'],
        ], 429),
    ]);
    Http::preventStrayRequests();

    $service = new ChatCompletionService(new OpenAIClient, new RegexPiiFilter);

    try {
        $service->complete([['role' => 'user', 'content' => 'Chào']]);
        $this->fail('Expected RuntimeException');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain('credit');
    }
});

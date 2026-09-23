<?php

use App\Services\OpenAIClient;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

uses()->group('openai', 'unit');

it('sends correct payload to chat completions', function () {
    config(['openai.api_key' => 'sk-test', 'openai.model' => 'gpt-5.6-luna']);

    Http::fake([
        'https://api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'Hello']]],
            'usage' => ['prompt_tokens' => 2, 'completion_tokens' => 3],
            'model' => 'gpt-5.6-luna',
        ]),
    ]);
    Http::preventStrayRequests();

    $result = (new OpenAIClient)->chat([
        ['role' => 'user', 'content' => 'Hi'],
    ]);

    expect($result['content'])->toBe('Hello')
        ->and($result['prompt_tokens'])->toBe(2)
        ->and($result['completion_tokens'])->toBe(3);

    Http::assertSent(function (Request $request) {
        $payload = $request->data();

        return $request->url() === 'https://api.openai.com/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer sk-test')
            && $request->hasHeader('Content-Type', 'application/json')
            && ($payload['model'] ?? null) === 'gpt-5.6-luna'
            && ($payload['messages'][0]['content'] ?? null) === 'Hi'
            && isset($payload['max_completion_tokens'])
            && ! isset($payload['temperature']);
    });
});

it('sends a privacy-preserving safety identifier when supplied', function () {
    config(['openai.api_key' => 'sk-test']);
    Http::fake(['https://api.openai.com/*' => Http::response([
        'choices' => [['message' => ['content' => 'Hello']]],
        'usage' => [],
    ])]);

    (new OpenAIClient)->chat([['role' => 'user', 'content' => 'Hi']], 'hashed-user-id');

    Http::assertSent(fn (Request $request): bool => $request->data()['safety_identifier'] === 'hashed-user-id');
});

it('retries only on server errors', function () {
    config(['openai.api_key' => 'sk-test']);

    Http::fake([
        'https://api.openai.com/*' => Http::sequence()
            ->push(['error' => 'server'], 503)
            ->push([
                'choices' => [['message' => ['content' => 'retried']]],
                'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
            ], 200),
    ]);
    Http::preventStrayRequests();

    $result = (new OpenAIClient)->chat([['role' => 'user', 'content' => 'Hi']]);
    expect($result['content'])->toBe('retried');

    Http::assertSentCount(2);
});

it('throws a request exception on upstream API error', function () {
    config(['openai.api_key' => 'sk-test']);

    Http::fake([
        'https://api.openai.com/*' => Http::response([
            'error' => [
                'code' => 'insufficient_quota',
                'message' => 'You exceeded your current quota.',
            ],
        ], 429),
    ]);
    Http::preventStrayRequests();

    $client = new OpenAIClient;

    expect(fn () => $client->chat([['role' => 'user', 'content' => 'Hi']]))
        ->toThrow(RequestException::class);
});

it('throws when api key missing', function () {
    config(['openai.api_key' => null]);

    $client = new OpenAIClient;

    expect(fn () => $client->chat([['role' => 'user', 'content' => 'Hi']]))
        ->toThrow(RuntimeException::class, 'OPENAI_API_KEY');
});

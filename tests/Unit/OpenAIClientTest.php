<?php

use App\Services\OpenAIClient;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses()->group('openai', 'unit');

it('sends correct payload to chat completions', function () {
    config(['openai.api_key' => 'sk-test', 'openai.model' => 'gpt-6-luna']);

    Http::fake([
        'https://api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'Hello']]],
            'usage' => ['prompt_tokens' => 2, 'completion_tokens' => 3],
            'model' => 'gpt-6-luna',
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
            && ($payload['model'] ?? null) === 'gpt-6-luna'
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

it('decodes streamed function-call arguments without exposing them as text deltas', function () {
    config(['openai.api_key' => 'sk-test', 'openai.model' => 'gpt-6-luna']);
    $toolArguments = json_encode([
        'summary' => 'Updated the greeting.',
        'edits' => [['find' => "print('before')", 'replace' => "print('after')"]],
    ]);
    $events = [
        ['choices' => [['delta' => ['tool_calls' => [[
            'index' => 0,
            'id' => 'call-code-edit',
            'type' => 'function',
            'function' => ['name' => 'apply_code_patch', 'arguments' => $toolArguments],
        ]]], 'finish_reason' => null]], 'model' => 'gpt-6-luna'],
        ['choices' => [['delta' => [], 'finish_reason' => 'tool_calls']], 'usage' => ['prompt_tokens' => 30, 'completion_tokens' => 10], 'model' => 'gpt-6-luna'],
    ];
    $body = collect($events)
        ->map(fn (array $event): string => 'data: '.json_encode($event)."\n\n")
        ->push("data: [DONE]\n\n")
        ->implode('');
    Http::fake(['https://api.openai.com/*' => Http::response($body, 200, ['Content-Type' => 'text/event-stream'])]);
    Http::preventStrayRequests();
    $deltas = [];
    Log::spy();

    $result = (new OpenAIClient)->streamChat(
        [['role' => 'user', 'content' => 'Please update the attached source.']],
        function (string $delta) use (&$deltas): void {
            $deltas[] = $delta;
        },
        maxCompletionTokens: 2048,
        tools: [[
            'type' => 'function',
            'function' => ['name' => 'apply_code_patch'],
        ]],
    );

    expect($result['content'])->toBe('')
        ->and($result['tool_calls'][0]['id'])->toBe('call-code-edit')
        ->and($result['tool_calls'][0]['name'])->toBe('apply_code_patch')
        ->and($result['tool_calls'][0]['arguments'])->toBe($toolArguments)
        ->and($result['prompt_tokens'])->toBe(30)
        ->and($deltas)->toBe([]);

    Http::assertSent(fn (Request $request): bool => $request->data()['reasoning_effort'] === 'none'
        && $request->data()['tool_choice'] === 'auto'
        && $request->data()['parallel_tool_calls'] === false);

    Log::shouldHaveReceived('info')
        ->once()
        ->with('OpenAI stream completion decoded', Mockery::on(fn (array $context): bool => isset($context['response_headers_ms'], $context['stream_duration_ms'])
            && $context['delta_count'] === 0
            && $context['time_to_first_delta_ms'] === null
            && $context['average_delta_gap_ms'] === null
            && ! array_key_exists('content', $context)));
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

it('retries only the transient GPT model-access response among 403 errors', function () {
    config([
        'openai.api_key' => 'sk-test',
        'openai.model_access_retry_times' => 2,
        'openai.model_access_retry_delay_ms' => 0,
    ]);

    Http::fake([
        'https://api.openai.com/*' => Http::sequence()
            ->push([
                'error' => [
                    'code' => 'model_not_found',
                    'message' => 'Project does not have access to model gpt-6-luna',
                ],
            ], 403)
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

it('does not retry an unrelated 403 response', function () {
    config([
        'openai.api_key' => 'sk-test',
        'openai.model_access_retry_times' => 6,
        'openai.model_access_retry_delay_ms' => 0,
    ]);

    Http::fake([
        'https://api.openai.com/*' => Http::response([
            'error' => ['code' => 'insufficient_permissions', 'message' => 'Restricted API key.'],
        ], 403),
    ]);
    Http::preventStrayRequests();

    expect(fn () => (new OpenAIClient)->chat([['role' => 'user', 'content' => 'Hi']]))
        ->toThrow(RequestException::class);
    Http::assertSentCount(1);
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

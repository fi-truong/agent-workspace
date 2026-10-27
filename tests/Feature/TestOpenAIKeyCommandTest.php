<?php

use App\Models\Conversation;
use App\Models\UsageLog;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['openai.api_key' => 'sk-test', 'openai.model' => 'gpt-6-luna', 'openai.sol_enabled' => true]);
    Http::preventStrayRequests();
});

it('does not perform billed probes unless live is explicitly selected', function () {
    Http::fake(['https://api.openai.com/v1/models' => Http::response(['data' => [['id' => 'gpt-6-sol']]])]);
    $this->artisan('openai:test-key')->expectsOutputToContain('Key hợp lệ')->assertSuccessful();
    Http::assertSentCount(1);
    Http::assertNotSent(fn (Request $request): bool => $request->method() !== 'GET');
});

it('requires an explicit live flag and enabled model', function () {
    $this->artisan('openai:test-key', ['--model' => 'gpt-6-sol'])->assertFailed();
    $this->artisan('openai:test-key', ['--model' => 'gpt-6.1-sol', '--live' => true])->assertFailed();
    config(['openai.sol_enabled' => false]);
    $this->artisan('openai:test-key', ['--model' => 'gpt-6-sol', '--live' => true])->assertFailed();
    Http::assertNothingSent();
});

it('tests actual chat streaming and function calling paths without saving user data', function () {
    $models = [];
    Http::fake(['https://api.openai.com/*' => function (Request $request) use (&$models) {
        $models[] = $request['model'];
        expect($request['max_completion_tokens'])->toBe(256);
        $usage = ['prompt_tokens' => 10, 'completion_tokens' => 5];
        if ($request['stream'] ?? false) {
            return Http::response('data: '.json_encode(['model' => 'gpt-6-sol', 'choices' => [['delta' => ['content' => 'AI_PLUS_LIVE_OK']]]])."\n\n"
                .'data: '.json_encode(['choices' => [['delta' => [], 'finish_reason' => 'stop']], 'usage' => $usage])."\n\n"
                ."data: [DONE]\n\n", 200, ['Content-Type' => 'text/event-stream']);
        }
        $message = ['content' => 'AI_PLUS_LIVE_OK'];
        if (isset($request['tools'])) {
            expect($request['reasoning_effort'])->toBe('none');
            $message = ['content' => null, 'tool_calls' => [[
                'id' => 'probe', 'type' => 'function', 'function' => ['name' => 'ai_plus_probe', 'arguments' => '{"marker":"AI_PLUS_LIVE_OK"}'],
            ]]];
        }

        return Http::response(['model' => 'gpt-6-sol', 'choices' => [['message' => $message]], 'usage' => $usage]);
    }]);
    $this->artisan('openai:test-key', ['--live' => true, '--model' => 'gpt-6-sol'])
        ->expectsOutputToContain('PASS chat')->expectsOutputToContain('PASS streaming')
        ->expectsOutputToContain('PASS function calling')->expectsOutputToContain('equivalent AI+ Credits: 0.9')
        ->assertSuccessful();
    expect($models)->toBe(['gpt-6-sol', 'gpt-6-sol', 'gpt-6-sol'])
        ->and(UsageLog::count())->toBe(0)->and(Conversation::count())->toBe(0);
    expect(config('openai.model'))->toBe('gpt-6-luna');
});

it('fails the live check if the provider returns another model', function () {
    Http::fake(['https://api.openai.com/*' => Http::response([
        'model' => 'gpt-6-luna', 'choices' => [['message' => ['content' => 'AI_PLUS_LIVE_OK']]],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
    ])]);
    $this->artisan('openai:test-key', ['--live' => true, '--model' => 'gpt-6-sol'])
        ->expectsOutputToContain('FAIL chat')->assertFailed();
    Http::assertSentCount(1);
});

it('does not expose secrets from provider errors', function () {
    Http::fake(['https://api.openai.com/*' => Http::response(['error' => ['message' => 'PRIVATE_PROVIDER_DETAILS']], 403)]);
    $this->artisan('openai:test-key', ['--live' => true, '--model' => 'gpt-6-sol'])
        ->expectsOutputToContain('API request failed')->doesntExpectOutputToContain('PRIVATE_PROVIDER_DETAILS')->assertFailed();
});

it('fails rather than accepting mock answers when no API key is configured', function () {
    config(['openai.api_key' => null]);
    $this->artisan('openai:test-key', ['--live' => true, '--model' => 'gpt-6-sol'])->assertFailed();
    Http::assertNothingSent();
});

<?php

use App\Models\UsageLog;
use App\Models\User;
use App\Services\TokenQuotaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

test('usage summary resets on the first day of each month and honours a later phase start without a cap', function () {
    Carbon::setTestNow('2026-09-18 10:00:00');
    $user = User::factory()->create();
    config([
        'usage.phase' => 'training',
        'usage.phase_started_at' => '2026-09-01 00:00:00',
        'usage.token_limits.training' => 10_000_000,
    ]);

    $beforeTraining = UsageLog::create([
        'user_id' => $user->id,
        'activity_title' => 'Before training',
        'source' => 'agent_workspace',
        'prompt_tokens' => 300,
        'completion_tokens' => 200,
    ]);
    $beforeTraining->forceFill(['created_at' => '2026-08-31 23:59:59'])->save();

    $duringTraining = UsageLog::create([
        'user_id' => $user->id,
        'activity_title' => 'During training',
        'source' => 'agent_workspace',
        'prompt_tokens' => 700,
        'completion_tokens' => 300,
    ]);
    $duringTraining->forceFill(['created_at' => '2026-09-01 00:00:00'])->save();

    expect(app(TokenQuotaService::class)->summary($user))->toMatchArray([
        'phase' => 'training',
        'used' => 1000,
        'limit' => null,
        'remaining' => null,
        'unlimited' => true,
    ]);

    Carbon::setTestNow();
});

test('chat remains available after usage exceeds legacy monthly token settings', function () {
    $user = User::factory()->create();
    config(['usage.token_limits.testing' => 10]);

    UsageLog::create([
        'user_id' => $user->id,
        'activity_title' => 'Quota exhausted',
        'source' => 'agent_workspace',
        'prompt_tokens' => 6,
        'completion_tokens' => 4,
    ]);
    Http::fake(['https://api.openai.com/*' => Http::response([
        'choices' => [['message' => ['content' => 'The answer still works.']]],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
    ])]);
    Http::preventStrayRequests();

    $this->actingAs($user)
        ->postJson(route('ai-plus.agent-workspace.send'), ['message' => 'Please answer this'])
        ->assertOk()
        ->assertJsonPath('reply', 'The answer still works.')
        ->assertJsonPath('token_quota.unlimited', true);

    Http::assertSentCount(2);
    $this->assertDatabaseCount('conversations', 1);
});

<?php

use App\Models\Conversation;
use App\Models\UsageLog;
use App\Models\User;

test('my usage only displays the authenticated users activity', function () {
    $viewer = User::factory()->create(['name' => 'Viewer User', 'daily_prompt_quota' => 0]);
    $otherUser = User::factory()->create(['name' => 'Other User']);

    UsageLog::create([
        'user_id' => $viewer->id,
        'activity_title' => 'Viewer activity',
        'source' => 'agent_workspace',
    ]);
    UsageLog::create([
        'user_id' => $otherUser->id,
        'activity_title' => 'Other activity',
        'source' => 'agent_workspace',
    ]);

    $this->actingAs($viewer)
        ->get(route('ai-plus.my-usage.index'))
        ->assertOk()
        ->assertSee('Viewer User')
        ->assertSee('Viewer activity')
        ->assertDontSee('Other activity')
        ->assertSee('20,000,000 of 20,000,000 tokens remaining');
});

test('an activity opens its existing conversation in the agent workspace', function () {
    $user = User::factory()->create();
    $conversation = Conversation::create(['user_id' => $user->id, 'title' => 'Open this conversation']);
    UsageLog::create([
        'user_id' => $user->id,
        'activity_title' => 'Open this activity',
        'source' => 'agent_workspace',
        'related_conversation_id' => $conversation->id,
    ]);

    $this->actingAs($user)
        ->get(route('ai-plus.my-usage.index'))
        ->assertOk()
        ->assertSee(route('ai-plus.agent-workspace.index', ['conversation_id' => $conversation->id]), false);
});

test('my usage supplies daily token and prompt data for the selected chart range', function () {
    $user = User::factory()->create();
    $log = UsageLog::create([
        'user_id' => $user->id,
        'activity_title' => 'Yesterday activity',
        'source' => 'agent_workspace',
        'prompt_tokens' => 120,
        'completion_tokens' => 80,
    ]);
    $log->forceFill(['created_at' => now()->subDay()])->save();

    $this->actingAs($user)
        ->get(route('ai-plus.my-usage.index', ['range' => 30]))
        ->assertOk()
        ->assertSee('Last 30 days')
        ->assertSee('usageOverTimeChart')
        ->assertSee('200', false)
        ->assertSee('Yesterday activity');
});

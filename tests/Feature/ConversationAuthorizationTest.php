<?php

use App\Models\Conversation;
use App\Models\Agent;
use App\Models\UsageLog;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('a user cannot send a message to another users conversation', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $conversation = Conversation::create(['user_id' => $owner->id, 'title' => 'Private conversation']);

    Http::fake();
    Http::preventStrayRequests();

    $this->actingAs($otherUser)
        ->postJson(route('ai-plus.agent-workspace.send'), [
            'message' => 'Attempt to access another user conversation',
            'conversation_id' => $conversation->id,
        ])
        ->assertNotFound();

    expect($conversation->fresh()->messages)->toBeEmpty();
    Http::assertNothingSent();
});

test('unsharing a use-only agent revokes access from existing recipient conversations', function () {
    $owner = User::factory()->create();
    $recipient = User::factory()->create();
    $agent = Agent::create([
        'user_id' => $owner->id,
        'title' => 'Withdrawn shared agent',
        'system_prompt' => 'Private operating instructions.',
        'is_shared' => false,
        'sharing_access' => 'use_only',
    ]);
    $conversation = Conversation::create([
        'user_id' => $recipient->id,
        'agent_id' => $agent->id,
        'title' => 'Previous use-only conversation',
    ]);

    Http::fake();
    Http::preventStrayRequests();

    $this->actingAs($recipient)
        ->postJson(route('ai-plus.agent-workspace.send'), [
            'message' => 'Can I still use this agent?',
            'conversation_id' => $conversation->id,
        ])
        ->assertStatus(410)
        ->assertJsonPath('agent_unavailable', true);

    expect($conversation->fresh()->agent_id)->toBeNull();
    Http::assertNothingSent();
});

test('a recipient cannot bypass copy-and-edit sharing with a direct agent URL or chat request', function () {
    $owner = User::factory()->create();
    $recipient = User::factory()->create();
    $agent = Agent::create([
        'user_id' => $owner->id,
        'title' => 'Copy-only shared agent',
        'system_prompt' => 'Do not expose this configuration.',
        'is_shared' => true,
        'sharing_access' => 'copy',
    ]);

    $this->actingAs($recipient)
        ->get(route('ai-plus.agent-workspace.index', ['agent_id' => $agent->id]))
        ->assertOk()
        ->assertSee('window.__AGENT_ACCESS_MESSAGE__', false)
        ->assertSee('window.__SELECTED_AGENT_ID__ = null', false);

    Http::fake();
    Http::preventStrayRequests();
    $this->actingAs($recipient)
        ->postJson(route('ai-plus.agent-workspace.send'), [
            'message' => 'Try to bypass the sharing flow',
            'agent_id' => $agent->id,
        ])
        ->assertForbidden()
        ->assertJsonPath('agent_copy_required', true);

    expect(Conversation::where('user_id', $recipient->id)->count())->toBe(0);
    Http::assertNothingSent();
});

test('deleting an agent preserves use-only conversation history owned by other users', function () {
    $owner = User::factory()->create();
    $recipient = User::factory()->create();
    $agent = Agent::create([
        'user_id' => $owner->id,
        'title' => 'Agent to remove',
        'is_shared' => true,
        'sharing_access' => 'use_only',
    ]);
    $ownerConversation = Conversation::create([
        'user_id' => $owner->id,
        'agent_id' => $agent->id,
        'title' => 'Owner conversation',
    ]);
    $recipientConversation = Conversation::create([
        'user_id' => $recipient->id,
        'agent_id' => $agent->id,
        'title' => 'Recipient conversation',
    ]);

    $this->actingAs($owner)
        ->deleteJson(route('ai-plus.agent-workspace.agents.destroy', $agent))
        ->assertOk();

    expect(Conversation::find($ownerConversation->id))->toBeNull()
        ->and(Conversation::findOrFail($recipientConversation->id)->agent_id)->toBeNull();
});

test('a user cannot rename or delete another users conversation', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $conversation = Conversation::create(['user_id' => $owner->id, 'title' => 'Private conversation']);

    $this->actingAs($otherUser)
        ->patchJson(route('ai-plus.conversations.rename', $conversation), ['title' => 'Changed'])
        ->assertForbidden();

    $this->actingAs($otherUser)
        ->deleteJson(route('ai-plus.conversations.destroy', $conversation))
        ->assertForbidden();

    $this->assertDatabaseHas('conversations', ['id' => $conversation->id, 'title' => 'Private conversation']);
});

test('deleting a conversation hides its activity but retains its token accounting', function () {
    $user = User::factory()->create();
    $conversation = Conversation::create(['user_id' => $user->id, 'title' => 'Deleted conversation']);
    $usageLog = UsageLog::create([
        'user_id' => $user->id,
        'activity_title' => 'Deleted conversation activity',
        'source' => 'agent_workspace',
        'related_conversation_id' => $conversation->id,
        'prompt_tokens' => 100,
        'completion_tokens' => 50,
    ]);

    $this->actingAs($user)
        ->deleteJson(route('ai-plus.conversations.destroy', $conversation))
        ->assertOk();

    expect($usageLog->fresh()->hidden_at)->not->toBeNull()
        ->and($usageLog->fresh()->related_conversation_id)->toBeNull();

    $this->actingAs($user)
        ->get(route('ai-plus.my-usage.index'))
        ->assertOk()
        ->assertDontSee('Deleted conversation activity')
        ->assertSee('150 tokens used');
});

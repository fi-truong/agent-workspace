<?php

use App\Models\Conversation;
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

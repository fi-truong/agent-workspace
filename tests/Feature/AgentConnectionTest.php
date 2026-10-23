<?php

use App\Models\Conversation;
use App\Models\ShowcasePost;
use App\Models\ShowcaseUse;
use App\Models\User;
use App\Services\AgentConnectionService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    Storage::fake('knowledge');
    config(['agent_connections.enabled' => true]);
});

it('lists owned and saved use-only Agents without exposing their configuration', function () {
    $own = $this->user->agents()->create(['title' => 'Owned specialist', 'system_prompt' => 'PRIVATE_PROMPT']);
    $owner = User::factory()->create();
    $shared = $owner->agents()->create(['title' => 'Shared specialist', 'is_shared' => true, 'sharing_access' => 'use_only']);
    $post = ShowcasePost::create(['author_id' => $owner->id, 'source_agent_id' => $shared->id, 'title' => 'Shared', 'description' => 'Shared specialist', 'department' => 'General', 'status' => 'published']);
    ShowcaseUse::create(['user_id' => $this->user->id, 'showcase_post_id' => $post->id]);
    $owner->agents()->create(['title' => 'Private specialist']);
    $owner->agents()->create(['title' => 'Copy first', 'is_shared' => true, 'sharing_access' => 'copy']);
    $response = $this->getJson(route('ai-plus.agent-workspace.agents.connection-options'))->assertOk();
    expect(collect($response->json('agents'))->pluck('id')->all())->toEqualCanonicalizing([$own->id, $shared->id]);
    $response->assertDontSee('PRIVATE_PROMPT')->assertDontSee('system_prompt')->assertDontSee('knowledge');
});

it('creates edits preserves and clears connected Agent setup', function () {
    $child = $this->user->agents()->create(['title' => 'Reviewer']);
    $response = $this->postJson(route('ai-plus.agent-workspace.agents.store'), [
        'title' => 'Coordinator', 'connections' => [['agent_id' => $child->id, 'role' => 'Review objectives']],
    ])->assertCreated();
    $id = $response->json('id');
    $this->getJson(route('ai-plus.agent-workspace.agents.show', $id))->assertJsonPath('connections.0.role', 'Review objectives');
    $this->postJson(route('ai-plus.agent-workspace.agents.update', $id), [
        '_method' => 'PUT', 'title' => 'Updated', 'connections' => [['agent_id' => $child->id, 'role' => 'Check outcomes']],
    ])->assertOk();
    $this->putJson(route('ai-plus.agent-workspace.agents.update', $id), ['title' => 'Keep connections'])->assertOk();
    $this->assertDatabaseHas('agent_connections', ['agent_id' => $id, 'role' => 'Check outcomes']);
    $this->putJson(route('ai-plus.agent-workspace.agents.update', $id), ['title' => 'No connections', 'connections_configured' => true])->assertOk();
    $this->assertDatabaseCount('agent_connections', 0);
});

it('rejects private foreign Agents self links duplicates and oversized setups before editing', function () {
    $parent = $this->user->agents()->create(['title' => 'Original']);
    $foreign = User::factory()->create()->agents()->create(['title' => 'Private']);
    foreach ([
        [['agent_id' => $foreign->id]],
        [['agent_id' => $parent->id]],
        [['agent_id' => $foreign->id], ['agent_id' => $foreign->id]],
        array_fill(0, 11, ['agent_id' => $foreign->id]),
    ] as $connections) {
        $this->putJson(route('ai-plus.agent-workspace.agents.update', $parent), ['title' => 'Changed', 'connections' => $connections])->assertUnprocessable();
    }
    expect($parent->fresh()->title)->toBe('Original');
    $this->assertDatabaseCount('agent_connections', 0);
});

it('does not let another user edit or read connection setup', function () {
    $parent = User::factory()->create()->agents()->create(['title' => 'Private coordinator']);
    $this->getJson(route('ai-plus.agent-workspace.agents.show', $parent))->assertForbidden();
    $this->putJson(route('ai-plus.agent-workspace.agents.update', $parent), ['title' => 'Changed', 'connections' => []])->assertForbidden();
});

it('allows ten connected Agents on creation and edit but rejects an eleventh', function () {
    $connections = collect(range(1, 11))->map(function (int $index): array {
        $child = $this->user->agents()->create(['title' => 'Specialist '.$index]);

        return ['agent_id' => $child->id, 'role' => 'Specialist role '.$index];
    })->all();
    $response = $this->postJson(route('ai-plus.agent-workspace.agents.store'), [
        'title' => 'Ten-Agent coordinator', 'connections' => array_slice($connections, 0, 10),
    ])->assertCreated();
    $parent = $this->user->agents()->findOrFail($response->json('id'));
    expect(app(AgentConnectionService::class)->forAgent($parent, $this->user))->toHaveCount(10);
    $this->putJson(route('ai-plus.agent-workspace.agents.update', $parent), [
        'title' => 'Updated coordinator', 'connections' => array_slice($connections, 0, 10),
    ])->assertOk();
    $this->postJson(route('ai-plus.agent-workspace.agents.store'), [
        'title' => 'Too many connections', 'connections' => $connections,
    ])->assertUnprocessable()->assertJsonValidationErrors('connections');
    $this->putJson(route('ai-plus.agent-workspace.agents.update', $parent), [
        'title' => 'Too many connections', 'connections' => $connections,
    ])->assertUnprocessable()->assertJsonValidationErrors('connections');
    expect($parent->connectedAgents()->count())->toBe(10)
        ->and($parent->fresh()->title)->toBe('Updated coordinator');
});

it('rechecks sharing permissions for the person running a shared coordinator', function () {
    $owner = User::factory()->create();
    $parent = $owner->agents()->create(['title' => 'Shared coordinator', 'is_shared' => true, 'sharing_access' => 'use_only']);
    $privateChild = $owner->agents()->create(['title' => 'Owner private child']);
    $sharedChild = $owner->agents()->create(['title' => 'Available child', 'is_shared' => true, 'sharing_access' => 'use_only']);
    $parent->connectedAgents()->attach([$privateChild->id, $sharedChild->id]);
    $service = app(AgentConnectionService::class);
    expect($service->forAgent($parent, $this->user)->pluck('id')->all())->toBe([$sharedChild->id]);
    $sharedChild->update(['is_shared' => false]);
    expect($service->forAgent($parent, $this->user))->toBeEmpty();
});

it('deleting a specialist preserves the coordinator and chat history', function () {
    $parent = $this->user->agents()->create(['title' => 'Coordinator']);
    $child = $this->user->agents()->create(['title' => 'Specialist']);
    $parent->connectedAgents()->attach($child);
    $conversation = $this->user->conversations()->create(['title' => 'Keep this', 'agent_id' => $parent->id, 'type' => Conversation::TYPE_CHAT]);
    $conversation->messages()->create(['role' => 'user', 'content' => 'School task']);
    $child->delete();
    $this->assertDatabaseCount('agent_connections', 0);
    expect($parent->fresh())->not->toBeNull()->and($conversation->messages()->count())->toBe(1);
});

it('disables setup without deleting connections and tolerates an unmigrated database', function () {
    $parent = $this->user->agents()->create(['title' => 'Coordinator']);
    $child = $this->user->agents()->create(['title' => 'Specialist']);
    $parent->connectedAgents()->attach($child);
    config(['agent_connections.enabled' => false]);
    $this->getJson(route('ai-plus.agent-workspace.agents.connection-options'))->assertJson(['enabled' => false, 'agents' => []]);
    $this->putJson(route('ai-plus.agent-workspace.agents.update', $parent), ['title' => 'Still editable'])->assertOk();
    $this->assertDatabaseCount('agent_connections', 1);
    config(['agent_connections.enabled' => true]);
    expect(app(AgentConnectionService::class)->forAgent($parent, $this->user)->pluck('id')->all())->toBe([$child->id]);
    Schema::drop('agent_connections');
    config(['agent_connections.enabled' => true]);
    $this->getJson(route('ai-plus.agent-workspace.agents.connection-options'))->assertJsonPath('enabled', false);
    $this->getJson(route('ai-plus.agent-workspace.agents.show', $parent))->assertOk();
});

it('shows accessible connected Agent names below the model without exposing their configuration', function () {
    $parent = $this->user->agents()->create(['title' => 'Coordinator']);
    $child = $this->user->agents()->create(['title' => 'Writer & Reviewer', 'system_prompt' => 'CHILD_PRIVATE_CONFIGURATION']);
    $parent->connectedAgents()->attach($child, ['role' => 'PRIVATE_CONNECTION_ROLE']);

    $this->get(route('ai-plus.agent-workspace.index', ['agent_id' => $parent->id]))
        ->assertOk()
        ->assertViewHas('agentConnectionSummaries', [$parent->id => [['id' => $child->id, 'title' => $child->title]], $child->id => []])
        ->assertSeeInOrder(['GPT-6 Luna', 'data-connected-agents', 'Connected Agents:', 'Writer &amp; Reviewer'], false)
        ->assertDontSee('CHILD_PRIVATE_CONFIGURATION')
        ->assertDontSee('PRIVATE_CONNECTION_ROLE');
});

it('uses the conversation Agent connections when reopening a chat', function () {
    $parent = $this->user->agents()->create(['title' => 'Coordinator']);
    $child = $this->user->agents()->create(['title' => 'Lesson reviewer']);
    $parent->connectedAgents()->attach($child);
    $conversation = $this->user->conversations()->create(['title' => 'Lesson chat', 'agent_id' => $parent->id, 'type' => Conversation::TYPE_CHAT]);

    $this->get(route('ai-plus.agent-workspace.index', ['conversation_id' => $conversation->id]))
        ->assertOk()
        ->assertSeeInOrder(['GPT-6 Luna', 'data-connected-agents', 'Lesson reviewer', 'active-agent-breadcrumb', 'Coordinator'], false);
});

it('hides inaccessible connections of a shared coordinator from the header and browser data', function () {
    $owner = User::factory()->create();
    $parent = $owner->agents()->create(['title' => 'Shared coordinator', 'is_shared' => true, 'sharing_access' => 'use_only']);
    $private = $owner->agents()->create(['title' => 'SECRET_CHILD_TITLE']);
    $copy = $owner->agents()->create(['title' => 'COPY_ONLY_CHILD_TITLE', 'is_shared' => true, 'sharing_access' => 'copy']);
    $shared = $owner->agents()->create(['title' => 'Shared reviewer', 'is_shared' => true, 'sharing_access' => 'use_only']);
    $parent->connectedAgents()->attach([$private->id, $copy->id, $shared->id]);

    $this->get(route('ai-plus.agent-workspace.index', ['agent_id' => $parent->id]))
        ->assertOk()
        ->assertViewHas('agentConnectionSummaries', [$parent->id => [['id' => $shared->id, 'title' => $shared->title]]])
        ->assertSeeInOrder(['GPT-6 Luna', 'data-connected-agents', 'Shared reviewer'], false)
        ->assertDontSee('SECRET_CHILD_TITLE')->assertDontSee('COPY_ONLY_CHILD_TITLE');

    $shared->update(['is_shared' => false]);
    $this->get(route('ai-plus.agent-workspace.index', ['agent_id' => $parent->id]))
        ->assertOk()->assertViewHas('agentConnectionSummaries', [$parent->id => []])
        ->assertDontSee('Shared reviewer');
});

it('does not expose connections through a coordinator whose shared access was revoked', function () {
    $owner = User::factory()->create();
    $parent = $owner->agents()->create(['title' => 'Former shared coordinator']);
    $child = $owner->agents()->create(['title' => 'HIDDEN_CHILD_TITLE', 'is_shared' => true, 'sharing_access' => 'use_only']);
    $parent->connectedAgents()->attach($child);
    $conversation = $this->user->conversations()->create(['title' => 'Previous chat', 'agent_id' => $parent->id, 'type' => Conversation::TYPE_CHAT]);

    $this->get(route('ai-plus.agent-workspace.index', ['conversation_id' => $conversation->id]))
        ->assertOk()->assertViewHas('agentConnectionSummaries', [])
        ->assertDontSee('HIDDEN_CHILD_TITLE');
});

it('keeps the header safe when connections are disabled or the table is not migrated', function () {
    $parent = $this->user->agents()->create(['title' => 'Coordinator']);
    $child = $this->user->agents()->create(['title' => 'Reviewer']);
    $parent->connectedAgents()->attach($child);
    config(['agent_connections.enabled' => false]);
    $this->get(route('ai-plus.agent-workspace.index', ['agent_id' => $parent->id]))
        ->assertOk()->assertViewHas('agentConnectionSummaries', []);

    Schema::drop('agent_connections');
    config(['agent_connections.enabled' => true]);
    $this->get(route('ai-plus.agent-workspace.index', ['agent_id' => $parent->id]))
        ->assertOk()->assertViewHas('agentConnectionSummaries', []);
});

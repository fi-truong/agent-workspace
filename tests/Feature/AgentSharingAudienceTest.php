<?php

use App\Models\Agent;
use App\Models\Conversation;
use App\Models\ShowcaseUse;
use App\Models\User;
use App\Services\AgentConnectionService;
use App\Services\AgentSharingService;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Http::preventStrayRequests();
    Storage::fake('knowledge');
    Storage::fake('agent-avatars');
    config(['agent_connections.enabled' => true, 'openai.api_key' => '', 'openai.moderation_enabled' => false, 'openai.rag_embeddings_enabled' => false]);
    $this->owner = User::factory()->create(['department' => 'CIEC']);
    $this->recipient = User::factory()->create(['department' => 'CIEC']);
    $this->outsider = User::factory()->create(['department' => 'HR']);
});

function createAudienceAgent($test, string $scope = 'users', string $access = 'use_only'): Agent
{
    $response = $test->actingAs($test->owner)->postJson(route('ai-plus.agent-workspace.agents.store'), [
        'title' => 'Limited planning assistant', 'description' => 'LIMITED_DESCRIPTION',
        'system_prompt' => 'PRIVATE_INSTRUCTIONS', 'is_shared' => true,
        'sharing_scope' => $scope, 'sharing_access' => $access,
        'sharing_user_ids' => [$test->recipient->id],
    ])->assertCreated();

    return Agent::findOrFail($response->json('id'));
}

it('saves department and selected people audiences and owner-only edit metadata', function (string $scope) {
    $agent = createAudienceAgent($this, $scope);
    expect($agent->sharing_scope)->toBe($scope)
        ->and($agent->sharing_department)->toBe($scope === 'department' ? 'CIEC' : null)
        ->and($agent->sharedRecipients()->count())->toBe($scope === 'users' ? 1 : 0);
    $this->actingAs($this->owner)->getJson(route('ai-plus.agent-workspace.agents.show', $agent))
        ->assertOk()->assertJsonPath('sharing_scope', $scope);
    $this->actingAs($this->recipient)->getJson(route('ai-plus.agent-workspace.agents.show', $agent))->assertForbidden();
})->with(['department', 'users']);

it('restricts discovery counts details use comments and avatars to the audience', function (string $scope) {
    $agent = createAudienceAgent($this, $scope);
    $post = $agent->showcasePosts()->firstOrFail();
    Storage::disk('agent-avatars')->put('limited.png', 'image');
    $agent->update(['avatar_path' => 'limited.png']);
    $comment = $post->comments()->create(['user_id' => $this->recipient->id, 'content' => 'Useful example']);
    $this->actingAs($this->recipient)->get(route('ai-plus.sharing-showcase.index'))
        ->assertOk()->assertSee('LIMITED_DESCRIPTION')->assertViewHas('totalAgents', 1);
    $this->get(route('ai-plus.sharing-showcase.show', $post))->assertOk();
    $this->get(route('ai-plus.agent-workspace.agents.avatar', $agent))->assertOk();

    $this->actingAs($this->outsider)->get(route('ai-plus.sharing-showcase.index'))
        ->assertOk()->assertDontSee('LIMITED_DESCRIPTION')->assertViewHas('totalAgents', 0)->assertViewHas('totalComments', 0);
    $this->get(route('ai-plus.sharing-showcase.show', $post))->assertNotFound();
    $this->post(route('ai-plus.sharing-showcase.use', $post))->assertNotFound();
    $this->post(route('ai-plus.sharing-showcase.comments.store', $post), ['content' => 'Outside comment'])->assertNotFound();
    $this->delete(route('ai-plus.sharing-showcase.comments.destroy', [$post, $comment]))->assertNotFound();
    $this->get(route('ai-plus.agent-workspace.agents.avatar', $agent))->assertNotFound();
    $this->get(route('ai-plus.index'))->assertOk()->assertDontSee('1 showcases');
})->with(['department', 'users']);

it('rechecks access after a recipient is removed or changes department', function (string $scope) {
    $agent = createAudienceAgent($this, $scope);
    $post = $agent->showcasePosts()->firstOrFail();
    $this->actingAs($this->recipient)->post(route('ai-plus.sharing-showcase.use', $post))->assertRedirect();
    $parent = $this->recipient->agents()->create(['title' => 'Coordinator']);
    $parent->connectedAgents()->attach($agent->id);
    $conversation = Conversation::create(['user_id' => $this->recipient->id, 'agent_id' => $agent->id, 'title' => 'Saved discussion', 'type' => 'chat']);
    if ($scope === 'users') {
        $agent->sharedRecipients()->detach($this->recipient->id);
    } else {
        $this->recipient->update(['department' => 'HR']);
    }
    expect(app(AgentConnectionService::class)->forAgent($parent, $this->recipient))->toBeEmpty();
    $this->getJson(route('ai-plus.agent-workspace.agents.connection-options'))->assertOk()->assertDontSee('Limited planning assistant');
    $this->getJson(route('ai-plus.agent-workspace.agents.index'))->assertOk()->assertDontSee('LIMITED_DESCRIPTION');
    $this->get(route('ai-plus.agent-workspace.index', ['agent_id' => $agent->id]))
        ->assertOk()->assertViewHas('selectedAgentId', null);
    $this->get(route('ai-plus.agent-workspace.index', ['conversation_id' => $conversation->id]))
        ->assertOk()->assertViewHas('activeAgent', null)->assertSee('Your conversation history is kept.');
    foreach (['send', 'send-stream'] as $endpoint) {
        $this->postJson('/ai-plus/agent-workspace/'.$endpoint, ['message' => 'Help plan a lesson', 'agent_id' => $agent->id])
            ->assertGone()->assertJsonPath('agent_unavailable', true);
    }
    expect($conversation->fresh())->not->toBeNull();
})->with(['department', 'users']);

it('prevents calling a restricted specialist through a school shared parent', function () {
    $child = createAudienceAgent($this);
    $parent = $this->owner->agents()->create(['title' => 'Shared coordinator', 'is_shared' => true, 'sharing_access' => 'use_only']);
    $parent->connectedAgents()->attach($child->id);
    expect(app(AgentConnectionService::class)->forAgent($parent, $this->recipient))->toHaveCount(1)
        ->and(app(AgentConnectionService::class)->forAgent($parent, $this->outsider))->toBeEmpty();
});

it('never exposes use-only setup in the workspace Agent JSON list', function () {
    $agent = createAudienceAgent($this);
    ShowcaseUse::create(['user_id' => $this->recipient->id, 'showcase_post_id' => $agent->showcasePosts()->firstOrFail()->id]);
    $this->actingAs($this->recipient)->getJson(route('ai-plus.agent-workspace.agents.index'))
        ->assertOk()->assertSee('Limited planning assistant')->assertDontSee('PRIVATE_INSTRUCTIONS')
        ->assertDontSee('knowledge_files')->assertDontSee('sharing_user_ids')->assertDontSee('system_prompt');
});

it('rejects invalid recipients and missing departments before updating an Agent', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    $agent = $this->owner->agents()->create(['title' => 'Unchanged']);
    $inactive = User::factory()->create(['is_active' => false]);
    foreach ([[], [$inactive->id], [$this->owner->id], [999999], [$this->recipient->id, $this->recipient->id]] as $ids) {
        $this->actingAs($this->owner)->putJson(route('ai-plus.agent-workspace.agents.update', $agent), [
            'title' => 'Changed', 'is_shared' => true, 'sharing_access' => 'use_only', 'sharing_scope' => 'users', 'sharing_user_ids' => $ids,
        ])->assertUnprocessable();
    }
    $this->owner->update(['department' => null]);
    $this->putJson(route('ai-plus.agent-workspace.agents.update', $agent), [
        'title' => 'Changed', 'is_shared' => true, 'sharing_access' => 'use_only', 'sharing_scope' => 'department',
    ])->assertUnprocessable()->assertJsonValidationErrors('sharing_scope');
    expect($agent->fresh()->title)->toBe('Unchanged')->and($agent->fresh()->is_shared)->toBeFalse();
});

it('keeps restricted audiences on legacy edits and clears recipients when switching to private', function () {
    $agent = createAudienceAgent($this);
    $this->actingAs($this->owner)->putJson(route('ai-plus.agent-workspace.agents.update', $agent), [
        'title' => 'Updated', 'is_shared' => true, 'sharing_access' => 'use_only',
    ])->assertOk();
    expect($agent->fresh()->sharing_scope)->toBe('users')->and($agent->sharedRecipients()->count())->toBe(1);
    $this->putJson(route('ai-plus.agent-workspace.agents.update', $agent), ['title' => 'Private again', 'is_shared' => false])->assertOk();
    expect($agent->fresh()->is_shared)->toBeFalse()->and($agent->sharedRecipients()->count())->toBe(0);
});

it('keeps the shared department fixed and denies blank departments', function () {
    $agent = createAudienceAgent($this, 'department');
    $this->owner->update(['department' => 'HR']);
    $this->actingAs($this->owner)->putJson(route('ai-plus.agent-workspace.agents.update', $agent), [
        'title' => $agent->title, 'is_shared' => true, 'sharing_access' => 'use_only', 'sharing_scope' => 'department',
    ])->assertOk();
    expect($agent->fresh()->sharing_department)->toBe('CIEC');
    $this->recipient->update(['department' => null]);
    expect(app(AgentSharingService::class)->canView($this->recipient, $agent->fresh()))->toBeFalse();
});

it('permits copies only for the chosen audience and keeps existing copies after revocation', function () {
    $agent = createAudienceAgent($this, 'users', 'copy');
    $post = $agent->showcasePosts()->firstOrFail();
    $this->actingAs($this->outsider)->post(route('ai-plus.sharing-showcase.use', $post))->assertNotFound();
    $this->actingAs($this->recipient)->post(route('ai-plus.sharing-showcase.use', $post))->assertRedirect();
    $copy = $this->recipient->agents()->where('copied_from_agent_id', $agent->id)->firstOrFail();
    expect($copy->is_shared)->toBeFalse()->and($copy->sharedRecipients()->count())->toBe(0);
    $agent->sharedRecipients()->detach();
    expect(app(AgentSharingService::class)->canUse($this->recipient, $copy))->toBeTrue();
});

it('searches only active users with exact and prefix matches first and bounds results', function () {
    $this->recipient->update(['name' => 'An Nguyen', 'email' => 'an@example.test']);
    $this->outsider->update(['name' => 'Binh An', 'email' => 'binh@example.test']);
    User::factory()->create(['name' => 'An Hidden', 'is_active' => false]);
    User::factory()->count(12)->create(['name' => 'Another person']);
    $url = route('ai-plus.agent-workspace.agents.sharing-users');
    $response = $this->actingAs($this->owner)->getJson($url.'?q=an')->assertOk()->assertJsonCount(10, 'users');
    expect($response->json('users.0.id'))->toBe($this->recipient->id);
    expect(array_keys($response->json('users.0')))->toEqualCanonicalizing(['id', 'name', 'email', 'department']);
    $this->getJson($url.'?q=an%40example.test')->assertOk()->assertJsonPath('users.0.id', $this->recipient->id);
    $this->getJson($url.'?q=%25%25')->assertOk()->assertJsonCount(0, 'users');
    $this->getJson($url.'?q=a')->assertUnprocessable();
    $this->getJson($url.'?q=An%20Hidden')->assertOk()->assertJsonCount(0, 'users');
});

it('rejects removing every selected person instead of silently restoring the old audience', function () {
    $agent = createAudienceAgent($this);
    $this->actingAs($this->owner)->putJson(route('ai-plus.agent-workspace.agents.update', $agent), [
        'title' => $agent->title, 'is_shared' => true, 'sharing_access' => 'use_only',
        'sharing_scope' => 'users', 'sharing_recipients_configured' => true,
    ])->assertUnprocessable()->assertJsonValidationErrors('sharing_user_ids');
    expect($agent->sharedRecipients()->count())->toBe(1);
});

it('allows the recipient to chat but blocks a stored conversation after permission is withdrawn', function () {
    $agent = createAudienceAgent($this);
    $response = $this->actingAs($this->recipient)->postJson('/ai-plus/agent-workspace/send', [
        'agent_id' => $agent->id, 'message' => 'Help prepare a lesson plan for the school.',
    ])->assertOk();
    $conversation = Conversation::findOrFail($response->json('conversation_id'));
    $messages = $conversation->messages()->count();
    $agent->sharedRecipients()->detach();
    $this->postJson('/ai-plus/agent-workspace/send', [
        'conversation_id' => $conversation->id, 'message' => 'Continue the plan.',
    ])->assertGone();
    expect($conversation->fresh()->agent_id)->toBeNull()
        ->and($conversation->messages()->count())->toBe($messages);
});

it('rolls back limited shares to private instead of school-wide access', function () {
    $limited = createAudienceAgent($this);
    $post = $limited->showcasePosts()->firstOrFail();
    $school = $this->owner->agents()->create(['title' => 'School assistant', 'is_shared' => true, 'sharing_scope' => 'school']);
    $migration = require database_path('migrations/2026_11_19_073347_add_agent_sharing_audiences.php');
    try {
        $migration->down();
        expect($limited->fresh()->is_shared)->toBeFalse()
            ->and($post->fresh()->status)->toBe('draft')
            ->and($school->fresh()->is_shared)->toBeTrue();
    } finally {
        $migration->up();
    }
});

<?php

use App\Models\Agent;
use App\Models\ShowcasePost;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('sharing an agent publishes it to the school showcase', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->postJson(route('ai-plus.agent-workspace.agents.store'), [
            'title' => 'Science Lesson Planner',
            'description' => 'Creates lesson plans for science classes.',
            'system_prompt' => 'Be a helpful science curriculum assistant.',
            'is_shared' => true,
            'sharing_access' => 'use_only',
        ])
        ->assertCreated();

    $agent = Agent::where('title', 'Science Lesson Planner')->firstOrFail();

    $this->assertDatabaseHas('showcase_posts', [
        'source_agent_id' => $agent->id,
        'title' => 'Science Lesson Planner',
        'description' => 'Creates lesson plans for science classes.',
        'status' => 'published',
    ]);

    $anotherUser = User::factory()->create();
    $this->actingAs($anotherUser)
        ->get(route('ai-plus.sharing-showcase.index'))
        ->assertOk()
        ->assertSee('Science Lesson Planner')
        ->assertSee('Creates lesson plans for science classes.');
});

test('unsharing an agent removes its generated showcase post', function () {
    $owner = User::factory()->create();
    $agent = Agent::create([
        'user_id' => $owner->id,
        'title' => 'Private Planner',
        'description' => 'A private agent.',
        'is_shared' => true,
    ]);
    ShowcasePost::create([
        'source_agent_id' => $agent->id,
        'author_id' => $owner->id,
        'department' => 'General',
        'title' => $agent->title,
        'description' => $agent->description,
        'status' => 'published',
    ]);

    $this->actingAs($owner)
        ->putJson(route('ai-plus.agent-workspace.agents.update', $agent), [
            'title' => $agent->title,
            'description' => $agent->description,
            'system_prompt' => '',
            'is_shared' => false,
        ])
        ->assertOk();

    $this->assertDatabaseMissing('showcase_posts', ['source_agent_id' => $agent->id]);
});

test('using an agent copies knowledge files only when the owner explicitly shares them', function () {
    Storage::fake('knowledge');
    $owner = User::factory()->create();
    $recipient = User::factory()->create();
    $source = Agent::create([
        'user_id' => $owner->id,
        'title' => 'Shared handbook agent',
        'system_prompt' => 'Answer from the handbook.',
        'is_shared' => true,
        'sharing_access' => 'copy',
    ]);
    $sourcePath = $owner->id.'/'.$source->id.'/handbook.txt';
    Storage::disk('knowledge')->put($sourcePath, 'The school handbook contains the shared policy.');
    $source->update(['knowledge' => json_encode([
        ['path' => $sourcePath, 'original_name' => 'handbook.txt'],
    ])]);
    $showcase = ShowcasePost::create([
        'source_agent_id' => $source->id,
        'author_id' => $owner->id,
        'department' => 'CIEC',
        'title' => $source->title,
        'description' => 'A shared handbook agent.',
        'status' => 'published',
    ]);

    $this->actingAs($recipient)
        ->post(route('ai-plus.sharing-showcase.use', $showcase))
        ->assertRedirect();

    $copiedAgent = $recipient->agents()->latest('id')->firstOrFail();
    expect($copiedAgent->knowledge_files)->toHaveCount(1)
        ->and($copiedAgent->knowledge_files[0]['original_name'])->toBe('handbook.txt')
        ->and($copiedAgent->knowledge_files[0]['path'])->not->toBe($sourcePath);
    Storage::disk('knowledge')->assertExists($copiedAgent->knowledge_files[0]['path']);
    expect(Storage::disk('knowledge')->get($copiedAgent->knowledge_files[0]['path']))
        ->toBe('The school handbook contains the shared policy.');
});

test('use-only sharing opens the source agent without copying its setup or knowledge', function () {
    Storage::fake('knowledge');
    $owner = User::factory()->create();
    $recipient = User::factory()->create();
    $source = Agent::create([
        'user_id' => $owner->id,
        'title' => 'Private knowledge agent',
        'is_shared' => true,
        'sharing_access' => 'use_only',
    ]);
    $sourcePath = $owner->id.'/'.$source->id.'/private.txt';
    Storage::disk('knowledge')->put($sourcePath, 'Private material');
    $source->update(['knowledge' => json_encode([
        ['path' => $sourcePath, 'original_name' => 'private.txt'],
    ])]);
    $showcase = ShowcasePost::create([
        'source_agent_id' => $source->id,
        'author_id' => $owner->id,
        'department' => 'CIEC',
        'title' => $source->title,
        'description' => 'A shared agent with private knowledge.',
        'status' => 'published',
    ]);

    $this->actingAs($recipient)
        ->post(route('ai-plus.sharing-showcase.use', $showcase))
        ->assertRedirect(route('ai-plus.agent-workspace.index', ['agent_id' => $source->id]));

    expect($recipient->agents)->toBeEmpty();
    Storage::disk('knowledge')->assertExists($sourcePath);

    $this->actingAs($recipient)
        ->get(route('ai-plus.agent-workspace.agents.show', $source))
        ->assertForbidden();
});

test('using a use-only agent more than once does not inflate its use count', function () {
    $owner = User::factory()->create();
    $recipient = User::factory()->create();
    $source = Agent::create([
        'user_id' => $owner->id,
        'title' => 'Use-only agent',
        'is_shared' => true,
        'sharing_access' => 'use_only',
    ]);
    $showcase = ShowcasePost::create([
        'source_agent_id' => $source->id,
        'author_id' => $owner->id,
        'department' => 'CIEC',
        'title' => $source->title,
        'description' => 'A shared use-only agent.',
        'status' => 'published',
    ]);

    $this->actingAs($recipient)
        ->post(route('ai-plus.sharing-showcase.use', $showcase))
        ->assertRedirect(route('ai-plus.agent-workspace.index', ['agent_id' => $source->id]));

    $this->actingAs($recipient)
        ->post(route('ai-plus.sharing-showcase.use', $showcase))
        ->assertRedirect(route('ai-plus.agent-workspace.index', ['agent_id' => $source->id]))
        ->assertSessionHas('success', 'You are already using this agent. Continue in Agent Workspace.');

    expect($showcase->fresh()->uses_count)->toBe(1)
        ->and(\App\Models\ShowcaseUse::count())->toBe(1);
});

test('copying a shared agent more than once reopens the existing copy', function () {
    $owner = User::factory()->create();
    $recipient = User::factory()->create();
    $source = Agent::create([
        'user_id' => $owner->id,
        'title' => 'Copyable agent',
        'is_shared' => true,
        'sharing_access' => 'copy',
    ]);
    $showcase = ShowcasePost::create([
        'source_agent_id' => $source->id,
        'author_id' => $owner->id,
        'department' => 'CIEC',
        'title' => $source->title,
        'description' => 'A shared copyable agent.',
        'status' => 'published',
    ]);

    $this->actingAs($recipient)
        ->post(route('ai-plus.sharing-showcase.use', $showcase))
        ->assertRedirect();

    $copy = $recipient->agents()->where('copied_from_agent_id', $source->id)->firstOrFail();

    $this->actingAs($recipient)
        ->post(route('ai-plus.sharing-showcase.use', $showcase))
        ->assertRedirect(route('ai-plus.agent-workspace.index', ['agent_id' => $copy->id]))
        ->assertSessionHas('success', 'You have already added this agent. Continue editing it in My Agents.');

    expect($recipient->agents()->where('copied_from_agent_id', $source->id)->count())->toBe(1)
        ->and($showcase->fresh()->uses_count)->toBe(1);
});

test('copying a shared agent stops before creating an incomplete Knowledge clone', function () {
    Storage::fake('knowledge');
    $owner = User::factory()->create();
    $recipient = User::factory()->create();
    $source = Agent::create([
        'user_id' => $owner->id,
        'title' => 'Agent with missing shared Knowledge',
        'is_shared' => true,
        'sharing_access' => 'copy',
        'knowledge' => json_encode([[
            'path' => $owner->id.'/missing-agent/missing.txt',
            'original_name' => 'missing.txt',
        ]]),
    ]);
    $showcase = ShowcasePost::create([
        'source_agent_id' => $source->id,
        'author_id' => $owner->id,
        'department' => 'CIEC',
        'title' => $source->title,
        'description' => 'The source file no longer exists.',
        'status' => 'published',
    ]);

    $this->actingAs($recipient)
        ->from(route('ai-plus.sharing-showcase.show', $showcase))
        ->post(route('ai-plus.sharing-showcase.use', $showcase))
        ->assertRedirect(route('ai-plus.sharing-showcase.show', $showcase))
        ->assertSessionHasErrors('showcase');

    expect($recipient->agents)->toBeEmpty()
        ->and($showcase->fresh()->uses_count)->toBe(0);
});

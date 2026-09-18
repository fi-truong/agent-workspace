<?php

use App\Models\Agent;
use App\Models\ShowcasePost;
use App\Models\User;

test('sharing an agent publishes it to the school showcase', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->postJson(route('ai-plus.agent-workspace.agents.store'), [
            'title' => 'Science Lesson Planner',
            'description' => 'Creates lesson plans for science classes.',
            'system_prompt' => 'Be a helpful science curriculum assistant.',
            'is_shared' => true,
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

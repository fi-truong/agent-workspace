<?php

use App\Models\AgentTemplate;
use App\Models\PromptLibraryPrompt;
use App\Models\User;

test('public prompt library only lists published prompts and supports both taxonomy levels', function () {
    $user = User::factory()->create();
    PromptLibraryPrompt::create([
        'author_id' => $user->id,
        'title' => 'Teaching prompt',
        'description' => 'A detailed lesson prompt.',
        'preview_text' => 'Create a lesson plan.',
        'status' => 'published',
        'category_group' => 'teaching',
        'category' => 'lesson_planning',
    ]);
    PromptLibraryPrompt::create([
        'author_id' => $user->id,
        'title' => 'Office prompt',
        'description' => 'A detailed operations prompt.',
        'preview_text' => 'Create an operations checklist.',
        'status' => 'published',
        'category_group' => 'school_operations',
        'category' => 'administration_operations',
    ]);
    PromptLibraryPrompt::create([
        'author_id' => $user->id,
        'title' => 'Unpublished prompt',
        'description' => 'Not ready for users.',
        'preview_text' => 'Draft.',
        'status' => 'draft',
        'category_group' => 'teaching',
        'category' => 'lesson_planning',
    ]);

    $this->actingAs($user)
        ->get(route('ai-plus.prompt-library.index', ['group' => 'teaching', 'category' => 'lesson_planning']))
        ->assertOk()
        ->assertSee('Teaching prompt')
        ->assertDontSee('Office prompt')
        ->assertDontSee('Unpublished prompt');
});

test('public agent templates only list published templates and support taxonomy filters', function () {
    $user = User::factory()->create();
    AgentTemplate::create([
        'name' => 'Teaching template',
        'description' => 'A classroom assistant.',
        'system_prompt' => 'You are a classroom assistant. Create useful lesson materials for the teacher.',
        'status' => 'published',
        'category_group' => 'teaching',
        'category' => 'lesson_planning',
    ]);
    AgentTemplate::create([
        'name' => 'Operations template',
        'description' => 'An operations assistant.',
        'system_prompt' => 'You are an operations assistant. Create practical school checklists.',
        'status' => 'published',
        'category_group' => 'school_operations',
        'category' => 'administration_operations',
    ]);
    AgentTemplate::create([
        'name' => 'Unpublished template',
        'description' => 'Not ready for users.',
        'status' => 'draft',
        'category_group' => 'teaching',
        'category' => 'lesson_planning',
    ]);

    $this->actingAs($user)
        ->get(route('ai-plus.agent-templates.index', ['group' => 'teaching', 'category' => 'lesson_planning']))
        ->assertOk()
        ->assertSee('Teaching template')
        ->assertDontSee('Operations template')
        ->assertDontSee('Unpublished template');
});

test('using an agent template copies its curated system prompt', function () {
    $user = User::factory()->create();
    $systemPrompt = 'You are a specialist lesson-planning assistant. Ask clarifying questions and produce a practical, structured plan.';
    $template = AgentTemplate::create([
        'name' => 'Lesson Planner',
        'description' => 'Build structured lesson plans.',
        'system_prompt' => $systemPrompt,
        'status' => 'published',
        'category_group' => 'teaching',
        'category' => 'lesson_planning',
    ]);

    $this->actingAs($user)
        ->post(route('ai-plus.agent-templates.use', $template))
        ->assertRedirect();

    expect($user->agents()->firstOrFail()->system_prompt)->toBe($systemPrompt);
});

test('draft agent templates cannot be used through a direct request', function () {
    $user = User::factory()->create();
    $template = AgentTemplate::create([
        'name' => 'Draft Template',
        'description' => 'Not yet approved.',
        'status' => 'draft',
    ]);

    $this->actingAs($user)
        ->post(route('ai-plus.agent-templates.use', $template))
        ->assertNotFound();

    expect($user->agents()->count())->toBe(0);
});

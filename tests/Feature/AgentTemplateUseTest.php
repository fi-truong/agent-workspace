<?php

use App\Models\AgentTemplate;
use App\Models\User;

test('using a template adds a private agent and opens it in the workspace', function () {
    $user = User::factory()->create();
    $template = AgentTemplate::create([
        'name' => 'Lesson Planner',
        'description' => 'Build structured lesson plans for teachers.',
        'status' => 'published',
        'uses_count' => 0,
    ]);

    $response = $this->actingAs($user)
        ->post(route('ai-plus.agent-templates.use', $template))
        ->assertRedirect();

    $agent = $user->agents()->where('title', 'Lesson Planner')->firstOrFail();
    $response->assertRedirect(route('ai-plus.agent-workspace.index', ['agent_id' => $agent->id]));

    expect($agent->is_shared)->toBeFalse();
    expect($agent->system_prompt)->toContain('Lesson Planner');

    $this->assertDatabaseHas('usage_logs', [
        'user_id' => $user->id,
        'source' => 'template_used',
        'related_agent_template_id' => $template->id,
    ]);

    $this->actingAs($user)
        ->get(route('ai-plus.agent-workspace.index', ['agent_id' => $agent->id]))
        ->assertOk()
        ->assertViewHas('selectedAgentId', $agent->id);
});

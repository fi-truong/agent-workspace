<?php

use App\Models\User;

it('renders the LSTS AI+ name and shared presentation across the main screen families', function () {
    $this->get(route('login.local.form'))
        ->assertOk()
        ->assertSee('<title>Sign in — LSTS AI+</title>', false)
        ->assertSee(route('auth.microsoft.redirect'))
        ->assertSee('css/lsts-ai-plus.css');

    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $agent = $admin->agents()->create(['title' => 'Example Planner']);
    $this->actingAs($admin);

    foreach ([
        [route('ai-plus.index'), 'LSTS AI+', 'lsts-ai-home'],
        [route('ai-plus.agent-workspace.index'), 'Agent Workspace — LSTS AI+', 'lsts-ai-chat'],
        [route('ai-plus.prompt-library.index'), 'Prompt Library — LSTS AI+', 'lsts-ai-catalog'],
        [route('ai-plus.agent-workspace.agents.show', $agent), 'Example Planner — LSTS AI+', 'lsts-ai-agent-detail'],
        [route('ai-plus.agent-workspace.agents.index'), 'My Agents — LSTS AI+', 'lsts-ai-agents'],
        [route('admin.users.index'), 'Users & Roles — LSTS AI+', 'admin-body lsts-ai-ui'],
    ] as [$url, $title, $screenClass]) {
        $this->get($url)
            ->assertOk()
            ->assertSee('<title>'.e($title).'</title>', false)
            ->assertSee($screenClass)
            ->assertSee('css/lsts-ai-plus.css');
    }
});

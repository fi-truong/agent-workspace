<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('agent', 'feature');

it('renders the Agent title in the browser tab as escaped text', function () {
    $user = User::factory()->create();
    $agent = $user->agents()->create(['title' => 'Survey & Design']);

    $this->actingAs($user)
        ->get(route('ai-plus.agent-workspace.agents.show', $agent))
        ->assertOk()
        ->assertSee('<title>Survey &amp; Design — Agent Workspace</title>', false)
        ->assertDontSee('<?php echo e($agent->title); ?>');
});

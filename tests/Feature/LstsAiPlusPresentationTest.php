<?php

use App\Models\AppSetting;
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

it('loads the Workspace layout only within Workspace pages', function () {
    $user = User::factory()->create(['is_active' => true]);
    $agent = $user->agents()->create(['title' => 'Example Planner']);
    AppSetting::query()->updateOrCreate(['key' => 'ai_plus_image_generation_enabled'], ['value' => '1']);
    $this->actingAs($user);

    $this->get(route('ai-plus.index'))
        ->assertOk()
        ->assertDontSee('css/lsts-agent-workspace.css');

    $this->get(route('ai-plus.agent-workspace.index'))
        ->assertOk()
        ->assertSee('css/lsts-agent-workspace.css')
        ->assertSee('<h1>Start a conversation</h1>', false)
        ->assertSee('Agent details');

    $this->get(route('ai-plus.agent-workspace.agents.index'))
        ->assertOk()
        ->assertSee('css/lsts-agent-workspace.css')
        ->assertSee('Instructions and resources');

    $this->get(route('ai-plus.agent-workspace.agents.show', $agent))
        ->assertOk()
        ->assertSee('css/lsts-agent-workspace.css')
        ->assertSeeInOrder(['Instructions (System Prompt)', 'Knowledge', 'Details']);

    $this->get(route('ai-plus.agent-workspace.images.index'))
        ->assertOk()
        ->assertSee('css/lsts-agent-workspace.css')
        ->assertSee('<h1>Image Studio</h1>', false);
});

it('links each full homepage card to its destination', function () {
    $user = User::factory()->create(['is_active' => true]);
    $response = $this->actingAs($user)->get(route('ai-plus.index'))->assertOk();

    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $cards = $xpath->query('//a[@class="card"][h3 and p and span[@class="cta"]]');

    $destinations = [
        'ai-plus.agent-workspace.index',
        'ai-plus.prompt-library.index',
        'ai-plus.agent-templates.index',
        'ai-plus.sharing-showcase.index',
        'ai-plus.support.index',
        'ai-plus.ai-policy.index',
        'ai-plus.my-usage.index',
    ];

    expect($cards->length)->toBe(count($destinations));

    foreach ($cards as $index => $card) {
        $heading = $xpath->query('./h3', $card)->item(0);

        expect($card->getAttribute('href'))->toBe(route($destinations[$index]))
            ->and($card->getAttribute('aria-labelledby'))->toBe($heading->getAttribute('id'));
    }
});

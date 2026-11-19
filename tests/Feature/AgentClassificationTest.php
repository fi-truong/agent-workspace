<?php

use App\Models\ShowcasePost;
use App\Models\User;

test('new Agents require a valid Group and Category pair', function () {
    $owner = User::factory()->create();
    $url = route('ai-plus.agent-workspace.agents.store');

    $this->actingAs($owner)->postJson($url, ['title' => 'New Agent'])
        ->assertUnprocessable()->assertJsonValidationErrors(['category_group', 'category']);
    $this->postJson($url, ['title' => 'New Agent', 'category_group' => 'teaching', 'category' => 'budgeting_procurement'])
        ->assertUnprocessable()->assertJsonValidationErrors('category');
    $this->postJson($url, ['title' => 'New Agent', 'category_group' => 'teaching', 'category' => 'educational_research'])
        ->assertCreated()->assertJsonPath('category', 'educational_research');
    $this->assertDatabaseHas('agents', ['title' => 'New Agent', 'category_group' => 'teaching', 'category' => 'educational_research']);
});

test('legacy Agents are still editable and need a classification only when enabling school sharing', function () {
    $owner = User::factory()->create();
    $agent = $owner->agents()->create(['title' => 'Old Agent']);
    $url = route('ai-plus.agent-workspace.agents.update', $agent);
    $share = ['title' => 'Old Agent', 'is_shared' => true, 'sharing_access' => 'use_only'];

    $this->actingAs($owner)->putJson($url, ['title' => 'Old Agent', 'category_group' => '', 'category' => ''])->assertOk();
    $this->putJson($url, $share)->assertUnprocessable()->assertJsonValidationErrors('category_group');
    expect($agent->fresh()->is_shared)->toBeFalse();
    $this->putJson($url, [...$share, 'category_group' => 'school_operations', 'category' => 'other'])->assertOk();
    $this->putJson($url, $share)->assertOk()->assertJsonPath('category', 'other');

    $alreadyShared = $owner->agents()->create(['title' => 'Existing share', 'is_shared' => true, 'sharing_access' => 'use_only']);
    $this->putJson(route('ai-plus.agent-workspace.agents.update', $alreadyShared), $share)
        ->assertOk()->assertJsonPath('is_shared', true);
});

test('Showcase classification filters respect audiences and keep legacy Agents discoverable', function () {
    $owner = User::factory()->create(['department' => 'Synthetic A']);
    $viewer = User::factory()->create(['department' => 'Synthetic B']);
    foreach ([
        ['Visible Teaching Example', 'teaching', 'other', 'school'],
        ['Visible Operations Example', 'school_operations', 'other', 'school'],
        ['Restricted Teaching Example', 'teaching', 'other', 'department'],
        ['Visible Legacy Example', null, null, 'school'],
    ] as [$title, $group, $category, $scope]) {
        $agent = $owner->agents()->create([
            'title' => $title,
            'is_shared' => true,
            'sharing_scope' => $scope,
            'sharing_department' => $scope === 'department' ? $owner->department : null,
            'category_group' => $group,
            'category' => $category,
        ]);
        ShowcasePost::create([
            'author_id' => $owner->id, 'source_agent_id' => $agent->id, 'status' => 'published',
            'department' => $owner->department, 'title' => $title, 'description' => 'An example',
        ]);
    }

    $this->actingAs($viewer)->get(route('ai-plus.sharing-showcase.index', ['group' => 'teaching', 'category' => 'other']))
        ->assertOk()->assertSee('Visible Teaching Example')
        ->assertDontSee('Visible Operations Example')->assertDontSee('Restricted Teaching Example')->assertDontSee('Visible Legacy Example');
    $this->get(route('ai-plus.sharing-showcase.index', ['search' => 'Example']))
        ->assertOk()->assertSee('Visible Teaching Example')->assertSee('Visible Operations Example')
        ->assertSee('Visible Legacy Example')->assertSee('Unclassified')->assertDontSee('Restricted Teaching Example');
});

test('reviewed classifications can be previewed and preserve changed or already classified Agents', function () {
    $owner = User::factory()->create();
    $matched = $owner->agents()->create(['title' => 'A &amp; B', 'description' => '<b>Example</b>']);
    $changed = $owner->agents()->create(['title' => 'Changed name']);
    $classified = $owner->agents()->create([
        'title' => 'Already classified', 'category_group' => 'teaching', 'category' => 'lesson_planning',
    ]);
    $file = tempnam(sys_get_temp_dir(), 'agent-classification-test-');
    try {
        $rows = [
            [$matched->id, "A & B\0Example"],
            [$changed->id, "Old name\0"],
            [$classified->id, "Already classified\0"],
        ];
        file_put_contents($file, json_encode(array_map(fn (array $row): array => [
            'id' => $row[0], 'group' => 'school_operations', 'category' => 'other',
            'fingerprint' => hash('sha256', $row[1]),
        ], $rows), JSON_THROW_ON_ERROR));

        $this->artisan('agents:import-classifications', ['file' => $file])
            ->expectsOutput('Ready to update: 1')->expectsOutput('Changed since review: '.$changed->id)
            ->assertSuccessful();
        expect($matched->fresh()->category)->toBeNull();
        $this->artisan('agents:import-classifications', ['file' => $file, '--apply' => true])
            ->expectsOutput('Updated: 1')->assertSuccessful();
        expect($matched->fresh()->category)->toBe('other')
            ->and($changed->fresh()->category)->toBeNull()
            ->and($classified->fresh()->category)->toBe('lesson_planning');
        $this->artisan('agents:import-classifications', ['file' => $file, '--apply' => true])
            ->expectsOutput('Updated: 0')->expectsOutput('Already classified: 2')->assertSuccessful();
    } finally {
        unlink($file);
    }
});

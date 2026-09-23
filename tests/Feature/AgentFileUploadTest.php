<?php

use App\Models\Agent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class)->group('agent', 'feature');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    Storage::fake('knowledge');
});

it('stores uploaded txt file into knowledge path', function () {
    $file = UploadedFile::fake()->create('tai-lieu.txt', 10, 'text/plain');

    $response = $this->post('/ai-plus/agent-workspace/agents', [
        'title' => 'Agent test',
        'system_prompt' => 'Bạn là trợ lý.',
        'knowledge' => [$file],
    ], ['Accept' => 'application/json']);

    $response->assertStatus(201);

    $agent = $this->user->agents()->first();
    expect($agent)->not->toBeNull();

    $knowledge = json_decode($agent->knowledge, true);
    expect($knowledge)->toBeArray()
        ->and($knowledge)->toHaveCount(1)
        ->and($knowledge[0]['original_name'])->toBe('tai-lieu.txt');

    Storage::disk('knowledge')->assertExists($knowledge[0]['path']);
});

it('stores a pdf file with correct mime', function () {
    $file = UploadedFile::fake()->create('tai-lieu.pdf', 20, 'application/pdf');

    $response = $this->post('/ai-plus/agent-workspace/agents', [
        'title' => 'Agent pdf',
        'knowledge' => [$file],
    ], ['Accept' => 'application/json']);

    $response->assertStatus(201);
    Storage::disk('knowledge')->assertExists(Agent::first()->knowledge_files[0]['path']);
});

it('accepts an HTML file as Agent Knowledge', function () {
    $file = UploadedFile::fake()->createWithContent('saved-page.html', '<h1>School guide</h1><script>alert(1)</script><p>Useful content.</p>');

    $this->post('/ai-plus/agent-workspace/agents', [
        'title' => 'Agent HTML',
        'knowledge' => [$file],
    ], ['Accept' => 'application/json'])
        ->assertStatus(201);

    expect(Agent::first()->knowledge_files[0]['original_name'])->toBe('saved-page.html');
});

it('rejects disallowed file extension', function () {
    $file = UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload');

    $response = $this->post('/ai-plus/agent-workspace/agents', [
        'title' => 'Agent test',
        'knowledge' => [$file],
    ], ['Accept' => 'application/json']);

    $response->assertStatus(422);

    expect($this->user->agents()->count())->toBe(0);
    Storage::disk('knowledge')->assertDirectoryEmpty($this->user->id);
});

it('limits each agent to ten Knowledge files', function () {
    $files = collect(range(1, 11))
        ->map(fn (int $number) => UploadedFile::fake()->create("knowledge-{$number}.txt", 10, 'text/plain'))
        ->all();

    $this->post('/ai-plus/agent-workspace/agents', [
        'title' => 'Too many files',
        'knowledge' => $files,
    ], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('knowledge');

    expect($this->user->agents()->count())->toBe(0);
});

it('limits new Agent Knowledge to twenty five MB in total', function () {
    $files = collect(range(1, 6))
        ->map(fn (int $number) => UploadedFile::fake()->create("large-{$number}.txt", 4500, 'text/plain'))
        ->all();

    $this->post('/ai-plus/agent-workspace/agents', [
        'title' => 'Too much Knowledge',
        'knowledge' => $files,
    ], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('knowledge');

    expect($this->user->agents()->count())->toBe(0);
});

it('counts retained Knowledge files toward the Agent file limit on update', function () {
    $agent = $this->user->agents()->create([
        'title' => 'Agent with existing Knowledge',
    ]);
    $existingKnowledge = collect(range(1, 8))
        ->map(fn (int $number) => [
            'path' => "{$this->user->id}/{$agent->id}/existing-{$number}.txt",
            'original_name' => "existing-{$number}.txt",
        ])
        ->all();
    $agent->update(['knowledge' => json_encode($existingKnowledge)]);

    $newFiles = collect(range(1, 3))
        ->map(fn (int $number) => UploadedFile::fake()->create("new-{$number}.txt", 10, 'text/plain'))
        ->all();

    $this->put("/ai-plus/agent-workspace/agents/{$agent->id}", [
        'title' => 'Should not be saved',
        'knowledge' => $newFiles,
    ], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('knowledge');

    $agent->refresh();
    expect($agent->title)->toBe('Agent with existing Knowledge')
        ->and($agent->knowledge_files)->toHaveCount(8);
});

it('keeps existing files and adds new files on update', function () {
    $oldFile = UploadedFile::fake()->create('cu.txt', 10, 'text/plain');
    $agent = $this->user->agents()->create([
        'title' => 'Agent test',
    ]);
    $knowledge = [['path' => '1/'.$agent->id.'/cu.txt', 'original_name' => 'cu.txt']];
    Storage::disk('knowledge')->put('1/'.$agent->id.'/cu.txt', 'nội dung cũ');
    $agent->update(['knowledge' => json_encode($knowledge)]);

    $newFile = UploadedFile::fake()->create('moi.txt', 10, 'text/plain');

    $response = $this->put("/ai-plus/agent-workspace/agents/{$agent->id}", [
        'title' => 'Agent test updated',
        'knowledge' => [$newFile],
    ], ['Accept' => 'application/json']);

    $response->assertStatus(200);

    $agent->refresh();
    $saved = json_decode($agent->knowledge, true);
    expect($saved)->toHaveCount(2);

    Storage::disk('knowledge')->assertExists('1/'.$agent->id.'/cu.txt');
    $savedNew = collect($saved)->firstWhere('original_name', 'moi.txt');
    expect($savedNew)->not->toBeNull();
    Storage::disk('knowledge')->assertExists($savedNew['path']);
});

it('removes file when knowledge_remove sent', function () {
    $agent = $this->user->agents()->create(['title' => 'Agent test']);
    Storage::disk('knowledge')->put('1/'.$agent->id.'/xoa.txt', 'nội dung');
    $agent->update(['knowledge' => json_encode([
        ['path' => '1/'.$agent->id.'/xoa.txt', 'original_name' => 'xoa.txt'],
    ])]);

    $response = $this->put("/ai-plus/agent-workspace/agents/{$agent->id}", [
        'title' => 'Agent test',
        'knowledge_remove' => ['1/'.$agent->id.'/xoa.txt'],
    ], ['Accept' => 'application/json']);

    $response->assertStatus(200);

    $agent->refresh();
    expect(json_decode($agent->knowledge, true))->toBeEmpty();
    Storage::disk('knowledge')->assertMissing('1/'.$agent->id.'/xoa.txt');
});

it('deletes agent knowledge directory on destroy', function () {
    $agent = $this->user->agents()->create(['title' => 'Agent test']);
    Storage::disk('knowledge')->put('1/'.$agent->id.'/file.txt', 'nội dung');

    $this->delete("/ai-plus/agent-workspace/agents/{$agent->id}", [], ['Accept' => 'application/json'])->assertStatus(200);

    Storage::disk('knowledge')->assertMissing('1/'.$agent->id.'/file.txt');
});

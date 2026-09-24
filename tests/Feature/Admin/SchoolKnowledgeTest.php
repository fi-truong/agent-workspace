<?php

use App\Models\AppSetting;
use App\Models\SchoolKnowledgeChunk;
use App\Models\SchoolKnowledgeSource;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('administrators can upload a document to the School Knowledge Base', function () {
    Storage::fake('knowledge');
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $file = UploadedFile::fake()->createWithContent('staff-handbook.txt', 'SCHOOLHANDBOOK2026 contains the official staff leave procedure.');

    $this->actingAs($admin)
        ->post(route('admin.school-knowledge.upload'), ['documents' => [$file]])
        ->assertSessionHasNoErrors();

    $source = SchoolKnowledgeSource::query()->firstOrFail();
    expect($source->type)->toBe('upload')
        ->and($source->status)->toBe('ready')
        ->and(SchoolKnowledgeChunk::query()->where('school_knowledge_source_id', $source->id)->count())->toBeGreaterThan(0);
    Storage::disk('knowledge')->assertExists($source->storage_path);
});

test('administrators can toggle the shared School Knowledge Base', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

    $this->actingAs($admin)
        ->put(route('admin.school-knowledge.update'), ['enabled' => '0'])
        ->assertRedirect(route('admin.school-knowledge.index'));

    expect(AppSetting::boolean('school_knowledge_enabled', true))->toBeFalse();
});

test('School Knowledge Base page offers the suggested LSTS starter import', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

    $this->actingAs($admin)
        ->get(route('admin.school-knowledge.index'))
        ->assertOk()
        ->assertSee('Import basic school information');
});

test('keyword fallback prioritizes a matching School Knowledge source title', function () {
    config(['openai.rag_embeddings_enabled' => false, 'openai.rag_top_k' => 1]);
    $generic = SchoolKnowledgeSource::create(['type' => 'website', 'title' => 'LSTS official website', 'status' => 'ready']);
    SchoolKnowledgeChunk::create([
        'school_knowledge_source_id' => $generic->id,
        'chunk_index' => 0,
        'content' => 'Trường Đinh Thiện Lý giới thiệu các hoạt động của trường.',
        'embedding' => [],
    ]);
    $purpose = SchoolKnowledgeSource::create(['type' => 'website', 'title' => 'LSTS · Mục đích thành lập', 'status' => 'ready']);
    SchoolKnowledgeChunk::create([
        'school_knowledge_source_id' => $purpose->id,
        'chunk_index' => 0,
        'content' => 'Công ty Phú Mỹ Hưng đầu tư xây dựng Trường Đinh Thiện Lý mang tên ông Đinh Thiện Lý.',
        'embedding' => [],
    ]);

    $context = app(\App\Services\SchoolKnowledgeService::class)
        ->retrieveContext('Trường Đinh Thiện Lý do ai sáng lập?');

    expect($context)->toContain('Công ty Phú Mỹ Hưng')
        ->not->toContain('giới thiệu các hoạt động');
});

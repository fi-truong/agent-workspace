<?php

use App\Models\PromptLibraryPrompt;
use App\Models\Tag;
use App\Models\User;
use App\Support\AiPlusCatalogCategories;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;

test('teaching import publishes all approved full prompts and categories', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $catalog = File::json(database_path('seeders/data/teaching-prompts.vi.json'));

    $this->artisan('prompts:import-teaching')
        ->expectsOutputToContain('Imported 99 prompts; skipped 0 existing prompts.')
        ->assertSuccessful();

    expect(PromptLibraryPrompt::query()->count())->toBe(99);
    $counts = [];

    foreach ($catalog['prompts'] as $entry) {
        $prompt = PromptLibraryPrompt::query()->where('source_key', 'upskii:'.$entry['id'])->firstOrFail();

        expect($prompt->title)->toBe($entry['title'])
            ->and($prompt->description)->toBe($entry['description'])
            ->and($prompt->preview_text)->toBe($entry['preview_text'])
            ->and(mb_strlen($prompt->preview_text))->toBeGreaterThan(900)
            ->and($prompt->status)->toBe('published')
            ->and($prompt->author_id)->toBe($admin->id)
            ->and($prompt->uses_count)->toBe(0)
            ->and($prompt->category_group)->toBe('teaching')
            ->and($prompt->category)->toBe($entry['category'])
            ->and($prompt->tags()->pluck('name')->sort()->values()->all())->toBe(collect($entry['tags'])->sort()->values()->all());

        $counts[$prompt->category] = ($counts[$prompt->category] ?? 0) + 1;
    }

    expect($counts)->toHaveCount(11)
        ->and(array_unique(array_values($counts)))->toBe([9]);

    expect(PromptLibraryPrompt::query()->where('source_key', 'upskii:game-tool-guide')->value('preview_text'))
        ->toContain('Tiêu chí sản phẩm đầu ra phù hợp')
        ->not->toContain('Tiêu chí poster khoa học');
    expect(PromptLibraryPrompt::query()->where('source_key', 'upskii:subject-music-composition')->value('title'))
        ->toBe('Thiết kế hoạt động sáng tác và phân tích âm nhạc');
    expect(PromptLibraryPrompt::query()->where('source_key', 'upskii:lesson-gdpt2018-map')->value('preview_text'))
        ->toContain('văn bản chương trình hoặc trích đoạn chính thức');
});

test('repeating the import preserves curated edits use counts tags and unrelated prompts', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->artisan('prompts:import-teaching')->assertSuccessful();

    $prompt = PromptLibraryPrompt::query()->where('source_key', 'upskii:lesson-bloom-objectives')->firstOrFail();
    $prompt->update(['title' => 'An edited title', 'preview_text' => 'An edited prompt.', 'status' => 'draft', 'uses_count' => 17]);
    $tag = Tag::create(['name' => 'Custom topic', 'category' => 'subject']);
    $prompt->tags()->sync([$tag->id]);
    $originalAttributes = $prompt->fresh()->getAttributes();
    $unrelated = PromptLibraryPrompt::create([
        'author_id' => $admin->id,
        'title' => 'School operations prompt',
        'description' => 'Keep this catalog entry.',
        'preview_text' => 'Original operations content.',
        'category_group' => 'school_operations',
        'category' => 'communication',
        'status' => 'published',
        'uses_count' => 23,
    ]);
    $unrelatedAttributes = $unrelated->fresh()->getAttributes();
    $tagCount = Tag::count();
    $this->travel(1)->hours();

    $this->artisan('prompts:import-teaching')
        ->expectsOutputToContain('Imported 0 prompts; skipped 99 existing prompts.')
        ->assertSuccessful();

    expect(PromptLibraryPrompt::count())->toBe(100)
        ->and($prompt->fresh()->getAttributes())->toBe($originalAttributes)
        ->and($prompt->tags()->pluck('id')->all())->toBe([$tag->id])
        ->and($unrelated->fresh()->getAttributes())->toBe($unrelatedAttributes)
        ->and(Tag::count())->toBe($tagCount);
});

test('an import can use an explicitly selected existing active maintainer', function () {
    User::factory()->create(['role' => 'admin']);
    $maintainer = User::factory()->create();

    $this->artisan('prompts:import-teaching', ['--author' => $maintainer->email])->assertSuccessful();

    expect(PromptLibraryPrompt::query()->distinct()->pluck('author_id')->all())->toBe([$maintainer->id]);
});

test('an import without a valid active maintainer writes nothing', function () {
    User::factory()->create(['role' => 'admin', 'is_active' => false]);

    $this->artisan('prompts:import-teaching')
        ->expectsOutputToContain('No active maintainer found.')
        ->assertFailed();

    expect(PromptLibraryPrompt::count())->toBe(0)
        ->and(Tag::count())->toBe(0);
});

test('invalid bundled catalog entries prevent the whole import', function (string $invalidField) {
    User::factory()->create(['role' => 'admin']);
    $catalog = File::json(database_path('seeders/data/teaching-prompts.vi.json'));

    match ($invalidField) {
        'id' => $catalog['prompts'][98]['id'] = $catalog['prompts'][0]['id'],
        'category' => $catalog['prompts'][98]['category'] = 'unknown_category',
        'body' => $catalog['prompts'][98]['preview_text'] = '',
    };
    File::partialMock()->shouldReceive('get')->with(database_path('seeders/data/teaching-prompts.vi.json'))->once()->andReturn(json_encode($catalog, JSON_THROW_ON_ERROR));

    $this->artisan('prompts:import-teaching')->assertFailed();

    expect(PromptLibraryPrompt::count())->toBe(0)
        ->and(Tag::count())->toBe(0);
})->with(['id', 'category', 'body']);

test('a database failure rolls back prompts and tags from the import', function () {
    User::factory()->create(['role' => 'admin']);
    Event::listen('eloquent.creating: '.PromptLibraryPrompt::class, function (PromptLibraryPrompt $prompt): void {
        if ($prompt->source_key === 'upskii:lesson-detailed-plan') {
            throw new RuntimeException('Synthetic import failure.');
        }
    });

    expect(fn () => Artisan::call('prompts:import-teaching'))->toThrow(RuntimeException::class, 'Synthetic import failure.');
    expect(PromptLibraryPrompt::count())->toBe(0)
        ->and(Tag::count())->toBe(0);
});

test('published teaching prompts are filterable without displaying review annotations', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->artisan('prompts:import-teaching')->assertSuccessful();
    $this->actingAs($admin);

    foreach (AiPlusCatalogCategories::promptCategories()['teaching'] as $category => $label) {
        $this->get(route('ai-plus.prompt-library.index', ['group' => 'teaching', 'category' => $category]))
            ->assertOk()
            ->assertViewHas('prompts', fn ($prompts) => $prompts->count() === 9 && $prompts->every(fn ($prompt) => $prompt['category'] === $category))
            ->assertSee($label)
            ->assertSee('View full prompt')
            ->assertDontSee('Bộ bổ sung Upskii')
            ->assertDontSee('Phân loại dự kiến')
            ->assertDontSee('Đối chiếu 3 đề xuất');
    }

    $prompt = PromptLibraryPrompt::query()->where('source_key', 'upskii:lesson-gdpt2018-map')->firstOrFail();
    $this->get(route('ai-plus.prompt-library.index', ['search' => $prompt->title]))
        ->assertOk()
        ->assertSee('data-prompt="'.e($prompt->preview_text).'"', false)
        ->assertSee('không tự khẳng định bài học đã đáp ứng GDPT 2018.');

    $this->get(route('ai-plus.agent-templates.index'))
        ->assertOk()
        ->assertDontSee('Gamification &amp; Technology', false);
    expect(AiPlusCatalogCategories::CATEGORIES['teaching'])->toHaveCount(5);
});

test('administrators can create and edit prompts in the new teaching categories', function (string $category) {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);
    $data = [
        'title' => 'A new teaching prompt',
        'description' => 'A useful detailed description.',
        'preview_text' => 'A complete prompt to review.',
        'subject' => 'Teaching',
        'category_group' => 'teaching',
        'category' => $category,
        'status' => 'published',
    ];

    $this->post(route('admin.prompts.store'), $data)->assertSessionHasNoErrors()->assertRedirect(route('admin.prompts.index'));
    $prompt = PromptLibraryPrompt::query()->sole();
    expect($prompt->category)->toBe($category)
        ->and($prompt->author_id)->toBe($admin->id);

    $data['title'] = 'An updated teaching prompt';
    $this->put(route('admin.prompts.update', $prompt), $data)->assertSessionHasNoErrors()->assertRedirect(route('admin.prompts.index'));
    expect($prompt->fresh()->title)->toBe($data['title']);

    $data['category_group'] = 'school_operations';
    $this->put(route('admin.prompts.update', $prompt), $data)->assertSessionHasErrors('category');
    expect($prompt->fresh()->category_group)->toBe('teaching');
})->with(['teaching_materials', 'classroom_engagement', 'classroom_management', 'professional_development', 'gamification_technology', 'creative_learning']);

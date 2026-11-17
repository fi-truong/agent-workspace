<?php

namespace App\Console\Commands;

use App\Models\PromptLibraryPrompt;
use App\Models\Tag;
use App\Models\User;
use App\Support\AiPlusCatalogCategories;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use JsonException;

class ImportTeachingPrompts extends Command
{
    protected $signature = 'prompts:import-teaching
        {--author= : Existing active maintainer email; defaults to the first active administrator}';

    protected $description = 'Publish the 99 approved Vietnamese teaching prompts without overwriting existing records';

    public function handle(): int
    {
        if (! Schema::hasColumn('prompt_library_prompts', 'source_key')) {
            $this->error('Run the prompt source-key migration before importing. Nothing was imported.');

            return self::FAILURE;
        }

        $authorEmail = $this->option('author');
        $author = User::query()
            ->where('is_active', true)
            ->when($authorEmail, fn ($query) => $query->where('email', $authorEmail), fn ($query) => $query->where('role', 'admin'))
            ->orderBy('id')
            ->first();

        if ($author === null) {
            $this->error('No active maintainer found. Provide an existing account with --author=email. Nothing was imported.');

            return self::FAILURE;
        }

        try {
            $contents = json_decode(File::get(database_path('seeders/data/teaching-prompts.vi.json')), true, 512, JSON_THROW_ON_ERROR);
            /** @var array{prompts: list<array{id: string, title: string, description: string, preview_text: string, category_group: string, category: string, tags: list<string>}>} $catalog */
            $catalog = Validator::make(is_array($contents) ? $contents : [], [
                'prompts' => ['required', 'array', 'size:99'],
                'prompts.*.id' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/', 'distinct:strict'],
                'prompts.*.title' => ['required', 'string', 'max:255'],
                'prompts.*.description' => ['required', 'string'],
                'prompts.*.preview_text' => ['required', 'string'],
                'prompts.*.category_group' => ['required', Rule::in(['teaching'])],
                'prompts.*.category' => ['required', Rule::in(array_keys(AiPlusCatalogCategories::promptCategories()['teaching']))],
                'prompts.*.tags' => ['required', 'array', 'min:1'],
                'prompts.*.tags.*' => ['required', 'string', 'max:50'],
            ])->validate();
        } catch (ValidationException|FileNotFoundException|JsonException) {
            $this->error('The bundled prompt catalog is missing or invalid. Nothing was imported.');

            return self::FAILURE;
        }

        $created = DB::transaction(function () use ($catalog, $author): int {
            $created = 0;

            foreach ($catalog['prompts'] as $entry) {
                $prompt = PromptLibraryPrompt::query()->firstOrCreate([
                    'source_key' => 'upskii:'.$entry['id'],
                ], [
                    'author_id' => $author->id,
                    'title' => $entry['title'],
                    'description' => $entry['description'],
                    'preview_text' => $entry['preview_text'],
                    'category_group' => $entry['category_group'],
                    'category' => $entry['category'],
                    'status' => 'published',
                    'uses_count' => 0,
                ]);

                if (! $prompt->wasRecentlyCreated) {
                    continue;
                }

                foreach ($entry['tags'] as $name) {
                    $tag = Tag::query()->firstOrCreate(['name' => $name], ['category' => 'subject']);
                    $prompt->tags()->syncWithoutDetaching([$tag->id]);
                }

                $created++;
            }

            return $created;
        });

        $skipped = count($catalog['prompts']) - $created;
        $this->info("Imported {$created} prompts; skipped {$skipped} existing prompts.");
        $this->line('Catalog records present: 99/99. Existing prompts, edits and use counts were preserved.');

        return self::SUCCESS;
    }
}

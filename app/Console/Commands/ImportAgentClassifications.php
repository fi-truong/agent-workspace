<?php

namespace App\Console\Commands;

use App\Services\PiiFilterService;
use App\Support\AgentCategories;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use JsonException;

class ImportAgentClassifications extends Command
{
    protected $signature = 'agents:import-classifications {file : Reviewed JSON mapping} {--apply : Save classifications}';

    protected $description = 'Preview or apply reviewed Group and Category assignments to unclassified Agents';

    public function handle(PiiFilterService $pii): int
    {
        $file = $this->argument('file');
        if (! is_file($file) || ! is_readable($file)) {
            $this->error('The mapping file could not be opened.');

            return self::FAILURE;
        }

        $contents = file_get_contents($file);
        try {
            $rows = json_decode($contents === false ? '' : $contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->error('The mapping file is not valid JSON.');

            return self::FAILURE;
        }

        $validator = Validator::make(['rows' => $rows], [
            'rows' => ['required', 'array', 'list', 'max:10000'],
            'rows.*' => ['required', 'array:id,group,category,fingerprint'],
            'rows.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'rows.*.group' => ['required', 'string'],
            'rows.*.category' => ['required', 'string'],
            'rows.*.fingerprint' => ['required', 'regex:/\A[0-9a-f]{64}\z/'],
        ]);
        if ($validator->fails()) {
            $this->error('The mapping file contains invalid or duplicate entries.');

            return self::FAILURE;
        }

        foreach ($rows as $row) {
            if (! AgentCategories::contains($row['group'], $row['category'])) {
                $this->error('Invalid Group and Category for Agent '.$row['id'].'.');

                return self::FAILURE;
            }
        }

        $apply = (bool) $this->option('apply');
        $review = DB::transaction(function () use ($rows, $pii, $apply): array {
            $agents = DB::table('agents')->whereIntegerInRaw('id', array_column($rows, 'id'))->orderBy('id');
            if ($apply) {
                $agents->lockForUpdate();
            }
            $byId = $agents->get(['id', 'title', 'description', 'category_group', 'category'])->keyBy('id');
            $review = ['matched' => 0, 'classified' => 0, 'missing' => [], 'changed' => []];

            foreach ($rows as $row) {
                $agent = $byId->get($row['id']);
                if (! $agent) {
                    $review['missing'][] = $row['id'];

                    continue;
                }
                if ($agent->category_group !== null || $agent->category !== null) {
                    $review['classified']++;

                    continue;
                }

                // Match the same sanitized and capped fields used in the review export.
                $title = $pii->redact(strip_tags(html_entity_decode((string) $agent->title)));
                $description = $pii->redact(strip_tags(html_entity_decode((string) $agent->description)));
                $fingerprint = hash('sha256', $title."\0".mb_substr($description, 0, 800));
                if (! hash_equals($row['fingerprint'], $fingerprint)) {
                    $review['changed'][] = $row['id'];

                    continue;
                }
                if ($apply) {
                    DB::table('agents')->where('id', $agent->id)->update([
                        'category_group' => $row['group'],
                        'category' => $row['category'],
                    ]);
                }
                $review['matched']++;
            }

            return $review;
        });

        $this->info(($apply ? 'Updated: ' : 'Ready to update: ').$review['matched']);
        $this->line('Already classified: '.$review['classified']);
        $this->line('Missing IDs: '.(implode(', ', $review['missing']) ?: 'None'));
        $this->line('Changed since review: '.(implode(', ', $review['changed']) ?: 'None'));

        return self::SUCCESS;
    }
}

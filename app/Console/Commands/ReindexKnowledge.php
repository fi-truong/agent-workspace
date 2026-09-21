<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Services\KnowledgeService;
use Illuminate\Console\Command;

class ReindexKnowledge extends Command
{
    protected $signature = 'knowledge:reindex {--agent= : Reindex only one agent ID}';

    protected $description = 'Create or refresh semantic RAG embeddings for uploaded Agent Knowledge files';

    public function handle(KnowledgeService $knowledgeService): int
    {
        $agents = Agent::query()
            ->when($this->option('agent'), fn ($query, $agentId) => $query->whereKey($agentId))
            ->whereNotNull('knowledge')
            ->get();

        if ($agents->isEmpty()) {
            $this->info('No agents with Knowledge files found.');

            return self::SUCCESS;
        }

        foreach ($agents as $agent) {
            $knowledgeService->indexAgent($agent);
            $this->line("Indexed Agent #{$agent->id}: {$agent->title}");
        }

        $this->info("Reindexed {$agents->count()} agent(s).");

        return self::SUCCESS;
    }
}

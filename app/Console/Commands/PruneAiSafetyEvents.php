<?php

namespace App\Console\Commands;

use App\Models\AiSafetyEvent;
use Illuminate\Console\Command;

class PruneAiSafetyEvents extends Command
{
    protected $signature = 'ai-plus:prune-work-use-events';

    protected $description = 'Delete Work-Use Monitoring events older than 180 days';

    public function handle(): int
    {
        $deleted = AiSafetyEvent::query()
            ->where('created_at', '<', now()->subDays(180))
            ->delete();

        $this->info("Deleted {$deleted} Work-Use Monitoring event(s) older than 180 days.");

        return self::SUCCESS;
    }
}

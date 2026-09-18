<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\ShowcasePost;

class SharedAgentTemplateSyncService
{
    public function sync(Agent $agent): void
    {
        if (! $agent->is_shared) {
            $agent->showcasePosts()->delete();

            return;
        }

        ShowcasePost::updateOrCreate(
            ['source_agent_id' => $agent->id],
            [
                'author_id' => $agent->user_id,
                'department' => $agent->user?->department ?: 'General',
                'title' => $agent->title,
                'description' => $agent->description ?: 'A school-shared AI agent.',
                'badge' => 'New',
                'status' => 'published',
                'published_at' => now(),
            ],
        );

    }
}

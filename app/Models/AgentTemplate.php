<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property-read Agent|null $sourceAgent
 */
class AgentTemplate extends Model
{
    protected $fillable = ['source_agent_id', 'icon', 'name', 'description', 'system_prompt', 'preview_class', 'badge', 'uses_count', 'category', 'category_group', 'status'];

    /** @return BelongsTo<Agent, $this> */
    public function sourceAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'source_agent_id');
    }

    /** @return HasMany<AgentTemplateFeature, $this> */
    public function features(): HasMany
    {
        return $this->hasMany(AgentTemplateFeature::class)->orderBy('sort_order');
    }

    /** @return HasMany<UsageLog, $this> */
    public function usageLogs(): HasMany
    {
        return $this->hasMany(UsageLog::class, 'related_agent_template_id');
    }

    public function recordUse(User $user): void
    {
        $this->increment('uses_count');

        $this->usageLogs()->create([
            'user_id' => $user->id,
            'activity_title' => 'Used template: '.Str::limit($this->name, 80),
            'source' => 'template_used',
        ]);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}

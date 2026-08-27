<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentTemplate extends Model
{
    protected $fillable = ['icon', 'name', 'description', 'preview_class', 'badge', 'uses_count', 'category', 'status'];

    public function features()
    {
        return $this->hasMany(AgentTemplateFeature::class)->orderBy('sort_order');
    }

    public function usageLogs()
    {
        return $this->hasMany(UsageLog::class, 'related_agent_template_id');
    }
}

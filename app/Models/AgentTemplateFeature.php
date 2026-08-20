<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentTemplateFeature extends Model
{
    public $timestamps = false;

    protected $fillable = ['agent_template_id', 'feature_text', 'sort_order'];

    public function agentTemplate()
    {
        return $this->belongsTo(AgentTemplate::class);
    }
}

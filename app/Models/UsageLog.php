<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsageLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'activity_title', 'source', 'related_conversation_id',
        'related_agent_template_id', 'prompt_tokens', 'completion_tokens',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'related_conversation_id');
    }

    public function agentTemplate()
    {
        return $this->belongsTo(AgentTemplate::class, 'related_agent_template_id');
    }
}

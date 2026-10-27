<?php

namespace App\Models;

use App\Services\ChatModelService;
use Illuminate\Database\Eloquent\Model;

class UsageLog extends Model
{
    protected static function booted(): void
    {
        static::creating(function (UsageLog $log): void {
            $log->quota_units = ((int) $log->prompt_tokens + (int) $log->completion_tokens)
                * ChatModelService::multiplier($log->model);
        });
    }

    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
        'hidden_at' => 'datetime',
        'quota_units' => 'integer',
    ];

    protected $fillable = [
        'user_id', 'activity_title', 'source', 'model', 'source_message_id', 'related_conversation_id',
        'related_agent_template_id', 'prompt_tokens', 'completion_tokens', 'hidden_at',
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

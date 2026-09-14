<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $agent_id
 * @property string $title
 * @property-read Agent|null $agent
 * @property-read Collection<int, Message> $messages
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Conversation extends Model
{
    protected $fillable = ['user_id', 'agent_id', 'title'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * @return HasMany<UsageLog, $this>
     */
    public function usageLogs(): HasMany
    {
        return $this->hasMany(UsageLog::class, 'related_conversation_id');
    }
}

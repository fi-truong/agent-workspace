<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $conversation_id
 * @property string $role
 * @property string $content
 * @property int $prompt_tokens
 * @property int $completion_tokens
 * @property-read Conversation|null $conversation
 * @property Carbon|null $created_at
 */
class Message extends Model
{
    public $timestamps = false;

    protected $fillable = ['conversation_id', 'role', 'content', 'prompt_tokens', 'completion_tokens'];

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}

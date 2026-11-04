<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationAttachment extends Model
{
    protected $fillable = [
        'conversation_id',
        'message_id',
        'user_id',
        'original_name',
        'extension',
        'source_type',
        'status',
        'extracted_text',
        'disk',
        'path',
        'character_count',
        'extraction_complete',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'character_count' => 'integer',
            'extraction_complete' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return BelongsTo<Message, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

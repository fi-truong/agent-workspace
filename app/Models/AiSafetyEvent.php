<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSafetyEvent extends Model
{
    protected $fillable = [
        'user_id', 'feature', 'classification', 'action', 'moderation_flagged',
        'category', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'moderation_flagged' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

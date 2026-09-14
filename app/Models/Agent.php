<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string|null $description
 * @property string|null $system_prompt
 * @property string|null $knowledge
 * @property bool $is_shared
 * @property-read User|null $user
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Agent extends Model
{
    protected $fillable = ['user_id', 'title', 'description', 'system_prompt', 'knowledge', 'is_shared'];

    protected $casts = [
        'is_shared' => 'boolean',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ShowcasePost, $this>
     */
    public function showcasePosts(): HasMany
    {
        return $this->hasMany(ShowcasePost::class, 'source_agent_id');
    }

    /**
     * @return HasMany<Conversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * Đọc cột knowledge (JSON string) thành mảng các file.
     *
     * @return array<int, array{path: string, original_name: string}>
     */
    public function getKnowledgeFilesAttribute(): array
    {
        if (! $this->knowledge) {
            return [];
        }

        $decoded = json_decode($this->knowledge, true);

        if (! is_array($decoded)) {
            return [];
        }

        return $decoded;
    }
}

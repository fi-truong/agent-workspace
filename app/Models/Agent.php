<?php

namespace App\Models;

use App\Support\AgentCategories;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $copied_from_agent_id
 * @property string $title
 * @property string|null $description
 * @property string|null $category_group
 * @property string|null $category
 * @property-read string $classification_label
 * @property string|null $avatar_path
 * @property string|null $system_prompt
 * @property string|null $knowledge
 * @property bool $is_shared
 * @property string $sharing_access
 * @property-read User|null $user
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Agent extends Model
{
    protected $fillable = ['user_id', 'copied_from_agent_id', 'title', 'description', 'category_group', 'category', 'avatar_path', 'system_prompt', 'knowledge', 'is_shared', 'sharing_access', 'shared_with_team_id', 'sharing_scope', 'sharing_department'];

    protected $attributes = ['sharing_scope' => 'school'];

    /** @return BelongsToMany<User, $this> */
    public function sharedRecipients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'agent_share_recipients');
    }

    protected $casts = [
        'is_shared' => 'boolean',
    ];

    // Đưa accessor knowledge_files vào JSON (toArray/toJson) để frontend đọc được
    // khi mở lại agent (Edit modal hiển thị file đã lưu).
    protected $appends = ['knowledge_files', 'avatar_url'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Team, $this> */
    public function sharedWithTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'shared_with_team_id');
    }

    /** @return BelongsTo<Agent, $this> */
    public function copiedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'copied_from_agent_id');
    }

    /** @return BelongsToMany<Agent, $this> */
    public function connectedAgents(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'agent_connections', 'agent_id', 'connected_agent_id')
            ->withPivot('role')
            ->withTimestamps();
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
     * @return HasOne<AgentTemplate, $this>
     */
    public function sharedTemplate(): HasOne
    {
        return $this->hasOne(AgentTemplate::class, 'source_agent_id');
    }

    /**
     * @return HasMany<KnowledgeChunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class);
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

    /** @return array<int, string> */
    public function sharingAudiences(): array
    {
        if (! $this->is_shared) {
            return [];
        }
        $scopes = [];
        if ($this->sharing_scope === 'school') {
            $scopes[] = 'school';
        }
        if ($this->sharing_department) {
            $scopes[] = 'department';
        }
        $hasRecipients = $this->relationLoaded('sharedRecipients')
            ? $this->sharedRecipients->isNotEmpty() : $this->sharedRecipients()->exists();
        if ($this->sharing_scope === 'users' || $hasRecipients) {
            $scopes[] = 'users';
        }

        return $scopes;
    }

    public function getSharingAudienceLabelAttribute(): string
    {
        $scopes = $this->sharingAudiences();
        if (count($scopes) > 1) {
            return 'Multiple audiences';
        }

        return match ($scopes[0] ?? null) {
            'school' => 'School',
            'department' => 'Department · '.$this->sharing_department,
            'users' => 'Specific people',
            default => 'Private',
        };
    }

    public function getClassificationLabelAttribute(): string
    {
        $group = AgentCategories::GROUPS[$this->category_group] ?? null;
        $category = AgentCategories::CATEGORIES[$this->category_group][$this->category] ?? null;

        return $group === null || $category === null ? 'Unclassified' : $group.' · '.$category;
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path
            ? route('ai-plus.agent-workspace.agents.avatar', $this, false)
            : null;
    }
}

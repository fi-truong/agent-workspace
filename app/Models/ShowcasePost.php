<?php

namespace App\Models;

use App\Services\AgentSharingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShowcasePost extends Model
{
    protected $fillable = [
        'author_id', 'source_agent_id', 'source_workflow_id', 'department',
        'title', 'description', 'views_count', 'comments_count', 'uses_count', 'badge',
        'status', 'published_at', 'source_type',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'views_count' => 'integer',
        'comments_count' => 'integer',
        'uses_count' => 'integer',
    ];

    /** @param Builder<ShowcasePost> $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $agents = Agent::query();
        app(AgentSharingService::class)->constrain($agents, $user);
        $query->where(function (Builder $posts) use ($agents): void {
            $posts->whereNull('source_agent_id')->orWhereIn('source_agent_id', $agents->select('agents.id'));
        });
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsTo<Agent, $this> */
    public function sourceAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'source_agent_id');
    }

    public function sourceWorkflow()
    {
        return $this->belongsTo(Workflow::class, 'source_workflow_id');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'showcase_tags', 'showcase_post_id', 'tag_id');
    }

    /** @return HasMany<ShowcaseComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(ShowcaseComment::class, 'showcase_post_id');
    }
}

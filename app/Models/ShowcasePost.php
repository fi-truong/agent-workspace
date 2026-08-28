<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShowcasePost extends Model
{
    protected $fillable = [
        'author_id', 'source_agent_id', 'source_workflow_id', 'department',
        'title', 'description', 'views_count', 'comments_count', 'uses_count', 'badge',
        'status', 'published_at', 'source_type',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function sourceAgent()
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
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'category'];

    public function prompts()
    {
        return $this->belongsToMany(PromptLibraryPrompt::class, 'prompt_tags', 'tag_id', 'prompt_id');
    }

    public function showcasePosts()
    {
        return $this->belongsToMany(ShowcasePost::class, 'showcase_tags', 'tag_id', 'showcase_post_id');
    }
}

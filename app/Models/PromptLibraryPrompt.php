<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromptLibraryPrompt extends Model
{
    protected $table = 'prompt_library_prompts';

    protected $fillable = ['author_id', 'title', 'description', 'preview_text', 'uses_count', 'status'];

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'prompt_tags', 'prompt_id', 'tag_id');
    }
}

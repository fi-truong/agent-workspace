<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Workflow extends Model
{
    protected $fillable = ['user_id', 'title', 'description', 'config', 'is_shared'];

    protected $casts = [
        'config' => 'array',
        'is_shared' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function showcasePosts()
    {
        return $this->hasMany(ShowcasePost::class, 'source_workflow_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    protected $fillable = ['user_id', 'title', 'description', 'system_prompt', 'is_shared'];

    protected $casts = [
        'is_shared' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function showcasePosts()
    {
        return $this->hasMany(ShowcasePost::class, 'source_agent_id');
    }
}

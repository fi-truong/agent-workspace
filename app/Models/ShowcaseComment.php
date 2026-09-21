<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShowcaseComment extends Model
{
    protected $fillable = [
        'showcase_post_id',
        'user_id',
        'content',
    ];

    public function showcase()
    {
        return $this->belongsTo(ShowcasePost::class, 'showcase_post_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

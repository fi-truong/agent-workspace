<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShowcaseView extends Model
{
    protected $fillable = ['showcase_post_id', 'user_id', 'viewed_on'];

    protected $casts = [
        'viewed_on' => 'date',
    ];
}

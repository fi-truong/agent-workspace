<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShowcaseUse extends Model
{
    protected $fillable = ['showcase_post_id', 'user_id'];

    /** @return BelongsTo<ShowcasePost, $this> */
    public function showcase(): BelongsTo
    {
        return $this->belongsTo(ShowcasePost::class, 'showcase_post_id');
    }
}

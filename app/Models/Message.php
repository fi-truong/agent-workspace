<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    public $timestamps = false;

    protected $fillable = ['conversation_id', 'role', 'content', 'prompt_tokens', 'completion_tokens'];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}

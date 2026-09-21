<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketReply extends Model
{
    protected $fillable = ['support_ticket_id', 'author_id', 'body', 'sent_at', 'read_at', 'admin_read_at'];

    protected $casts = [
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
        'admin_read_at' => 'datetime',
    ];

    /** @return BelongsTo<SupportTicket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicketReply extends Model
{
    protected $fillable = ['support_ticket_id', 'author_id', 'body', 'sent_at', 'read_at', 'admin_read_at'];

    protected $casts = [
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
        'admin_read_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (SupportTicketReply $reply): void {
            $reply->attachments()->get()->each->delete();
        });
    }

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

    /** @return HasMany<SupportTicketReplyAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(SupportTicketReplyAttachment::class);
    }
}

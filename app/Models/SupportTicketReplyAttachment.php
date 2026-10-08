<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SupportTicketReplyAttachment extends Model
{
    protected $fillable = ['support_ticket_reply_id', 'original_name', 'path', 'mime_type', 'size'];

    protected static function booted(): void
    {
        static::deleting(function (SupportTicketReplyAttachment $attachment): void {
            Storage::disk('support-attachments')->delete($attachment->path);
        });
    }

    /** @return BelongsTo<SupportTicketReply, $this> */
    public function reply(): BelongsTo
    {
        return $this->belongsTo(SupportTicketReply::class, 'support_ticket_reply_id');
    }
}

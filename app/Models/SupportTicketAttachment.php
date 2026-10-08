<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SupportTicketAttachment extends Model
{
    protected $fillable = [
        'support_ticket_id', 'original_name', 'path', 'mime_type', 'size',
    ];

    protected static function booted(): void
    {
        static::deleting(function (SupportTicketAttachment $attachment): void {
            Storage::disk('support-attachments')->delete($attachment->path);
        });
    }

    /** @return BelongsTo<SupportTicket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }
}

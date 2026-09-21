<?php

namespace App\Mail;

use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupportTicketReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly SupportTicket $ticket,
        public readonly SupportTicketReply $reply,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "AI+ Support: {$this->ticket->subject} (#{$this->ticket->id})");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.support-ticket-reply', with: [
            'ticket' => $this->ticket,
            'reply' => $this->reply,
        ]);
    }
}

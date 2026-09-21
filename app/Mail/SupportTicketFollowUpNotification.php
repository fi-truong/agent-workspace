<?php

namespace App\Mail;

use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupportTicketFollowUpNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly SupportTicket $ticket,
        public readonly SupportTicketReply $reply,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "[AI+ Support] Follow-up on Ticket #{$this->ticket->id}: {$this->ticket->subject}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.support-ticket-follow-up-notification', with: [
            'ticket' => $this->ticket,
            'reply' => $this->reply,
            'adminUrl' => route('admin.tickets.show', $this->ticket),
        ]);
    }
}

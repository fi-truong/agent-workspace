<?php

namespace App\Mail;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupportTicketNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly SupportTicket $ticket) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[AI+ Support] New Ticket #{$this->ticket->id}: {$this->ticket->subject}",
            replyTo: [new Address($this->ticket->email, $this->ticket->name)],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.support-ticket-notification',
            with: [
                'ticket' => $this->ticket,
                'adminUrl' => route('admin.tickets.show', $this->ticket),
            ],
        );
    }
}

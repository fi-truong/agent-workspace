<?php

namespace App\Mail;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupportTicketReceipt extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly SupportTicket $ticket) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "AI+ Support: We received your request (#{$this->ticket->id})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.support-ticket-receipt',
            with: ['ticket' => $this->ticket],
        );
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SupportController extends Controller
{
    public function index()
    {
        $faqs = Faq::orderBy('sort_order')->get()->map(function ($faq) {
            return [
                'question' => $faq->question,
                'answer' => $faq->answer,
            ];
        })->toArray();

        return view('ai-plus.support.index', [
            'faqs' => $faqs,
            'viewingAs' => 'Teacher / Staff',
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150',
            'type' => 'required|string|in:Technical Issue / Bug Report,Feature Request,Question / How-To,Access / Account Issue,Training Request,Other',
            'subject' => 'required|string|max:200',
            'details' => 'required|string|min:10',
        ]);

        $ticket = SupportTicket::create($validated);

        // Send notification email to CIEC coordinator
        $this->sendNotificationEmail($ticket);

        // Send auto-reply to user
        $this->sendAutoReplyEmail($ticket);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Your request has been submitted. We\'ll get back to you within 1-2 business days.',
                'ticket_id' => $ticket->id,
            ]);
        }

        return back()->with('success', 'Your request has been submitted. We\'ll get back to you within 1-2 business days.');
    }

    private function sendNotificationEmail(SupportTicket $ticket)
    {
        try {
            $to = 'ciec.coordinator.04@lsts.edu.vn';
            $subject = "[AI+ Support] New Ticket #{$ticket->id}: {$ticket->subject}";

            $message = "
                <h2>New Support Ticket</h2>
                <p><strong>Ticket ID:</strong> #{$ticket->id}</p>
                <p><strong>Type:</strong> {$ticket->type}</p>
                <p><strong>From:</strong> {$ticket->name} ({$ticket->email})</p>
                <p><strong>Subject:</strong> {$ticket->subject}</p>
                <hr>
                <p><strong>Details:</strong></p>
                <p>" . nl2br(e($ticket->details)) . "</p>
                <hr>
                <p><a href=\"" . route('admin.support.tickets.show', $ticket) . "\">View in Admin Panel</a></p>
            ";

            // Using Mail::raw for simplicity - in production, use a proper Mailable class
            Mail::raw($message, function ($m) use ($to, $subject) {
                $m->to($to)->subject($subject);
            });
        } catch (\Exception $e) {
            Log::error('Failed to send support notification email', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendAutoReplyEmail(SupportTicket $ticket)
    {
        try {
            $subject = "AI+ Support: We received your request (#{$ticket->id})";

            $message = "
                <h2>Thank you for contacting AI+ Support</h2>
                <p>Hi {$ticket->name},</p>
                <p>We've received your request and will review it shortly.</p>
                <p><strong>Ticket ID:</strong> #{$ticket->id}</p>
                <p><strong>Subject:</strong> {$ticket->subject}</p>
                <p><strong>Type:</strong> {$ticket->type}</p>
                <hr>
                <p>Our team typically responds within 1-2 business days. You can reply to this email if you need to add more information.</p>
                <p>Best regards,<br>CIEC Team<br>Lawrence S. Ting School</p>
            ";

            Mail::raw($message, function ($m) use ($ticket, $subject) {
                $m->to($ticket->email)->subject($subject);
            });
        } catch (\Exception $e) {
            Log::error('Failed to send support auto-reply email', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
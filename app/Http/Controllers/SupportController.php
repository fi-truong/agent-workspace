<?php

namespace App\Http\Controllers;

use App\Mail\SupportTicketFollowUpNotification;
use App\Mail\SupportTicketNotification;
use App\Mail\SupportTicketReceipt;
use App\Models\Faq;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SupportController extends Controller
{
    public function index(Request $request)
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
            'unreadReplyCount' => $this->unreadReplyCount($request),
        ]);
    }

    public function myRequests(Request $request)
    {
        $tickets = SupportTicket::query()
            ->where('user_id', $request->user()->id)
            ->withCount('replies')
            ->withCount(['replies as unread_replies_count' => fn ($query) => $query
                ->whereNull('read_at')
                ->where(fn ($query) => $query->where('author_id', '!=', $request->user()->id)->orWhereNull('author_id'))])
            ->latest()
            ->paginate(12);

        return view('ai-plus.support.requests.index', compact('tickets'));
    }

    public function showRequest(Request $request, SupportTicket $ticket)
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);

        $ticket->replies()
            ->whereNull('read_at')
            ->where(fn ($query) => $query->where('author_id', '!=', $request->user()->id)->orWhereNull('author_id'))
            ->update(['read_at' => now()]);
        $ticket->load(['replies.author']);

        return view('ai-plus.support.requests.show', compact('ticket'));
    }

    public function store(Request $request)
    {
        // Tickets always belong to an authenticated AI+ account. Do not allow a
        // requester to submit a ticket under somebody else's name or email.
        $request->merge([
            'name' => $request->user()->name,
            'email' => $request->user()->email,
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150',
            'type' => 'required|string|in:Technical Issue / Bug Report,Feature Request,Question / How-To,Access / Account Issue,Training Request,Other',
            'subject' => 'required|string|max:200',
            'details' => 'required|string|min:10',
            'priority' => 'nullable|in:low,medium,high',
        ]);

        $validated['user_id'] = $request->user()->id;

        $ticket = SupportTicket::create($validated);

        $this->sendEmails($ticket);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Your request has been submitted. We\'ll get back to you within 1-2 business days.',
                'ticket_id' => $ticket->id,
            ]);
        }

        return back()->with('success', 'Your request has been submitted. We\'ll get back to you within 1-2 business days.');
    }

    public function storeFollowUp(Request $request, SupportTicket $ticket)
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);

        $data = $request->validate(['body' => 'required|string|min:3|max:5000']);
        $reply = SupportTicketReply::create([
            'support_ticket_id' => $ticket->id,
            'author_id' => $request->user()->id,
            'body' => trim($data['body']),
            'sent_at' => now(),
        ]);

        if (in_array($ticket->status, ['resolved', 'closed'], true)) {
            $ticket->update(['status' => 'pending', 'resolved_at' => null]);
        }

        try {
            Mail::to(config('support.notification_recipient'))
                ->send(new SupportTicketFollowUpNotification($ticket, $reply));
        } catch (\Throwable $exception) {
            Log::error('Failed to send support ticket follow-up notification', [
                'ticket_id' => $ticket->id,
                'reply_id' => $reply->id,
                'exception' => $exception::class,
            ]);
        }

        return back()->with('success', 'Your follow-up has been sent to the support team.');
    }

    private function sendEmails(SupportTicket $ticket): void
    {
        try {
            Mail::to(config('support.notification_recipient'))
                ->send(new SupportTicketNotification($ticket));
        } catch (\Exception $e) {
            Log::error('Failed to send support notification email', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            Mail::to($ticket->email)->send(new SupportTicketReceipt($ticket));
        } catch (\Exception $e) {
            Log::error('Failed to send support auto-reply email', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function unreadReplyCount(Request $request): int
    {
        return SupportTicketReply::query()
            ->whereNull('read_at')
            ->where(fn ($query) => $query->where('author_id', '!=', $request->user()->id)->orWhereNull('author_id'))
            ->whereHas('ticket', fn ($query) => $query->where('user_id', $request->user()->id))
            ->count();
    }
}

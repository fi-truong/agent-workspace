<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\SupportTicketReplyMail;
use App\Models\AdminAuditLog;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $query = SupportTicket::with('user', 'assignee')
            ->withCount(['replies as unread_follow_ups_count' => fn ($replyQuery) => $replyQuery
                ->whereNull('admin_read_at')
                ->whereColumn('support_ticket_replies.author_id', 'support_tickets.user_id')]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('details', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('assignee_id')) {
            $query->where('assigned_to', $request->assignee_id);
        }

        if ($request->boolean('needs_reply')) {
            $query->whereHas('replies', fn ($replyQuery) => $replyQuery
                ->whereNull('admin_read_at')
                ->whereColumn('support_ticket_replies.author_id', 'support_tickets.user_id'));
        }

        $sort = $request->get('sort', 'newest');
        match ($sort) {
            'oldest' => $query->oldest(),
            'priority' => $query->orderByDesc('priority'),
            default => $query->orderByDesc('unread_follow_ups_count')->latest(),
        };

        $tickets = $query->paginate(15)->withQueryString();
        $statuses = ['pending' => 'Pending', 'in_progress' => 'In Progress', 'resolved' => 'Resolved', 'closed' => 'Closed'];
        $assignees = User::whereIn('role', ['admin', 'staff'])->get(['id', 'name', 'email']);

        return view('admin.tickets.index', compact('tickets', 'statuses', 'assignees'));
    }

    public function show(SupportTicket $ticket)
    {
        $ticket->replies()
            ->whereNull('admin_read_at')
            ->where('author_id', $ticket->user_id)
            ->update(['admin_read_at' => now()]);
        $ticket->load(['user', 'assignee', 'replies.author']);
        $assignees = User::whereIn('role', ['admin', 'staff'])->get(['id', 'name', 'email']);

        return view('admin.tickets.show', compact('ticket', 'assignees'));
    }

    public function updateStatus(Request $request, SupportTicket $ticket)
    {
        $request->validate(['status' => 'required|in:pending,in_progress,resolved,closed']);

        $ticket->update([
            'status' => $request->status,
            'resolved_at' => in_array($request->status, ['resolved', 'closed'], true) ? now() : null,
        ]);
        AdminAuditLog::record('ticket.status_updated', $ticket, ['status' => $ticket->status]);

        if ($request->ajax()) {
            return response()->json(['success' => true, 'status' => $ticket->status]);
        }

        return back()->with('success', 'Ticket status updated.');
    }

    public function assign(Request $request, SupportTicket $ticket)
    {
        $request->validate(['assignee_id' => 'nullable|exists:users,id']);

        $assignee = $request->filled('assignee_id') ? User::findOrFail($request->integer('assignee_id')) : null;
        if ($assignee && (! $assignee->is_active || ! in_array($assignee->role, ['admin', 'staff'], true))) {
            return back()->withErrors(['assignee_id' => 'Only active administrators or staff can be assigned tickets.']);
        }

        $ticket->update(['assigned_to' => $assignee?->id]);
        AdminAuditLog::record('ticket.assigned', $ticket, ['assigned_to' => $assignee?->id]);

        if ($request->ajax()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Ticket assigned.');
    }

    public function storeNote(Request $request, SupportTicket $ticket)
    {
        $request->validate(['admin_notes' => 'required|string']);

        $ticket->update(['admin_notes' => $request->admin_notes]);
        AdminAuditLog::record('ticket.note_saved', $ticket);

        return back()->with('success', 'Admin note saved.');
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $validated = $request->validate([
            'body' => 'required|string|min:3|max:5000',
            'resolve' => 'nullable|boolean',
        ]);

        $reply = SupportTicketReply::create([
            'support_ticket_id' => $ticket->id,
            'author_id' => $request->user()->id,
            'body' => trim($validated['body']),
        ]);

        $sent = false;
        try {
            Mail::to($ticket->email)->send(new SupportTicketReplyMail($ticket, $reply));
            $reply->update(['sent_at' => now()]);
            $sent = true;
        } catch (\Throwable $exception) {
            Log::error('Failed to send support ticket reply email', [
                'ticket_id' => $ticket->id,
                'reply_id' => $reply->id,
                'exception' => $exception::class,
            ]);
        }

        if ($request->boolean('resolve')) {
            $ticket->update(['status' => 'resolved', 'resolved_at' => now()]);
        } elseif ($ticket->status === 'pending') {
            $ticket->update(['status' => 'in_progress']);
        }

        AdminAuditLog::record('ticket.reply_sent', $ticket, [
            'reply_id' => $reply->id,
            'email_sent' => $sent,
            'resolved' => $request->boolean('resolve'),
        ]);

        return back()->with($sent ? 'success' : 'warning', $sent
            ? 'Reply sent to the requester.'
            : 'Reply saved, but the email could not be sent. Check the mail configuration and try again.');
    }

    public function destroy(SupportTicket $ticket)
    {
        AdminAuditLog::record('ticket.deleted', $ticket, ['subject' => $ticket->subject]);
        $ticket->delete();

        return redirect()->route('admin.tickets.index')->with('success', 'Ticket deleted.');
    }
}

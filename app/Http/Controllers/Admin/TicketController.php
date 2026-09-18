<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $query = SupportTicket::with('user', 'assignee');

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

        $sort = $request->get('sort', 'newest');
        match ($sort) {
            'oldest' => $query->oldest(),
            'priority' => $query->orderByDesc('priority'),
            default => $query->latest(),
        };

        $tickets = $query->paginate(15)->withQueryString();
        $statuses = ['pending' => 'Pending', 'in_progress' => 'In Progress', 'resolved' => 'Resolved', 'closed' => 'Closed'];
        $assignees = User::whereIn('role', ['admin', 'staff'])->get(['id', 'name', 'email']);

        return view('admin.tickets.index', compact('tickets', 'statuses', 'assignees'));
    }

    public function show(SupportTicket $ticket)
    {
        $ticket->load('user', 'assignee');
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

    public function destroy(SupportTicket $ticket)
    {
        AdminAuditLog::record('ticket.deleted', $ticket, ['subject' => $ticket->subject]);
        $ticket->delete();

        return redirect()->route('admin.tickets.index')->with('success', 'Ticket deleted.');
    }
}

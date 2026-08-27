<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
            $query->where(function($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($q) => $q->where('name', 'like', "%{$search}%")
                                                     ->orWhere('email', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('assignee_id')) {
            $query->where('assignee_id', $request->assignee_id);
        }

        $sort = $request->get('sort', 'newest');
        match($sort) {
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

        $ticket->update(['status' => $request->status]);

        if ($request->ajax()) {
            return response()->json(['success' => true, 'status' => $ticket->status]);
        }

        return back()->with('success', 'Ticket status updated.');
    }

    public function assign(Request $request, SupportTicket $ticket)
    {
        $request->validate(['assignee_id' => 'required|exists:users,id']);

        $ticket->update(['assignee_id' => $request->assignee_id]);

        if ($request->ajax()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Ticket assigned.');
    }

    public function storeNote(Request $request, SupportTicket $ticket)
    {
        $request->validate(['admin_notes' => 'required|string']);

        $ticket->update(['admin_notes' => $request->admin_notes]);

        return back()->with('success', 'Admin note saved.');
    }

    public function destroy(SupportTicket $ticket)
    {
        $ticket->delete();
        return redirect()->route('admin.tickets.index')->with('success', 'Ticket deleted.');
    }
}
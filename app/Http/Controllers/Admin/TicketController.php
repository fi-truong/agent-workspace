<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketReply;
use App\Models\SupportTicketReplyAttachment;
use App\Models\User;
use App\Services\PiiFilterService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function newCount(): JsonResponse
    {
        return response()->json([
            'count' => SupportTicket::query()->where('status', 'pending')->count(),
        ]);
    }

    public function show(SupportTicket $ticket)
    {
        $ticket->update(['admin_read_at' => now()]);
        $ticket->replies()
            ->whereNull('admin_read_at')
            ->where('author_id', $ticket->user_id)
            ->update(['admin_read_at' => now()]);
        $ticket->load(['user', 'assignee', 'replies.author', 'replies.attachments', 'attachments']);
        $assignees = User::whereIn('role', ['admin', 'staff'])->get(['id', 'name', 'email']);

        return view('admin.tickets.show', compact('ticket', 'assignees'));
    }

    public function downloadAttachment(SupportTicket $ticket, SupportTicketAttachment $attachment): StreamedResponse
    {
        $attachment = $ticket->attachments()->findOrFail($attachment->id);

        return Storage::disk('support-attachments')->download($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function showReplyAttachment(SupportTicket $ticket, SupportTicketReply $reply, SupportTicketReplyAttachment $attachment): StreamedResponse
    {
        $reply = $ticket->replies()->findOrFail($reply->id);
        $attachment = $reply->attachments()->findOrFail($attachment->id);

        return Storage::disk('support-attachments')->response($attachment->path, null, [
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
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

    public function storeNote(Request $request, SupportTicket $ticket, PiiFilterService $piiFilter)
    {
        $validated = $request->validate(['admin_notes' => 'required|string']);
        if ($piiFilter->scan($validated['admin_notes'])['flagged']) {
            throw ValidationException::withMessages([
                'admin_notes' => 'Admin notes cannot contain sensitive personal information. Please remove it and try again.',
            ]);
        }

        $ticket->update(['admin_notes' => $validated['admin_notes']]);
        AdminAuditLog::record('ticket.note_saved', $ticket);

        return back()->with('success', 'Admin note saved.');
    }

    public function reply(Request $request, SupportTicket $ticket, PiiFilterService $piiFilter, DatabaseManager $database): RedirectResponse
    {
        $validated = $request->validate([
            'body' => 'nullable|string|min:3|max:5000',
            'images' => 'nullable|array|max:4',
            'images.*' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096',
            'resolve' => 'nullable|boolean',
        ]);
        $body = trim((string) ($validated['body'] ?? ''));
        $uploadedImages = $request->file('images', []);
        if ($body === '' && $uploadedImages === []) {
            throw ValidationException::withMessages([
                'body' => 'Write a message or attach at least one image.',
            ]);
        }
        if ($body !== '' && $piiFilter->scan($body)['flagged']) {
            throw ValidationException::withMessages([
                'body' => 'Support messages cannot contain sensitive personal information. Please remove it and try again.',
            ]);
        }

        if (array_sum(array_map(fn ($file): int => $file->getSize(), $uploadedImages)) > 16 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'images' => 'Images must not exceed 16 MB in total.',
            ]);
        }

        $storedPaths = [];
        try {
            $reply = $database->transaction(function () use ($ticket, $request, $body, $uploadedImages, &$storedPaths): SupportTicketReply {
                $reply = SupportTicketReply::create([
                    'support_ticket_id' => $ticket->id,
                    'author_id' => $request->user()->id,
                    'body' => $body,
                    'sent_at' => now(),
                ]);

                foreach ($uploadedImages as $image) {
                    $extension = strtolower($image->guessExtension() ?: $image->extension() ?: 'png');
                    $path = $image->storeAs('replies/'.$reply->id, Str::uuid().'.'.$extension, 'support-attachments');
                    $storedPaths[] = $path;
                    $originalName = basename(str_replace('\\', '/', $image->getClientOriginalName()));

                    SupportTicketReplyAttachment::create([
                        'support_ticket_reply_id' => $reply->id,
                        'original_name' => Str::limit($originalName, 255, ''),
                        'path' => $path,
                        'mime_type' => $image->getMimeType() ?: 'application/octet-stream',
                        'size' => $image->getSize(),
                    ]);
                }

                return $reply;
            });
        } catch (\Throwable $exception) {
            Storage::disk('support-attachments')->delete($storedPaths);

            throw $exception;
        }

        if ($request->boolean('resolve')) {
            $ticket->update(['status' => 'resolved', 'resolved_at' => now()]);
        } elseif ($ticket->status === 'pending') {
            $ticket->update(['status' => 'in_progress']);
        }

        AdminAuditLog::record('ticket.public_reply_posted', $ticket, [
            'reply_id' => $reply->id,
            'resolved' => $request->boolean('resolve'),
            'image_count' => count($uploadedImages),
        ]);

        return back()->with('success', 'Public reply posted to the request history.');
    }

    public function destroy(SupportTicket $ticket)
    {
        AdminAuditLog::record('ticket.deleted', $ticket, ['subject' => $ticket->subject]);
        $ticket->replies()->with('attachments')->get()->each->delete();
        $ticket->attachments()->get()->each->delete();
        $ticket->delete();

        return redirect()->route('admin.tickets.index')->with('success', 'Ticket deleted.');
    }
}

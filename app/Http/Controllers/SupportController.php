<?php

namespace App\Http\Controllers;

use App\Mail\SupportTicketFollowUpNotification;
use App\Mail\SupportTicketNotification;
use App\Models\Faq;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketReply;
use App\Models\SupportTicketReplyAttachment;
use App\Services\PiiFilterService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $ticket->load(['replies.author', 'replies.attachments', 'attachments']);

        return view('ai-plus.support.requests.show', compact('ticket'));
    }

    public function downloadAttachment(Request $request, SupportTicket $ticket, SupportTicketAttachment $attachment): StreamedResponse
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);
        $attachment = $ticket->attachments()->findOrFail($attachment->id);

        return Storage::disk('support-attachments')->download($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function showReplyAttachment(Request $request, SupportTicket $ticket, SupportTicketReply $reply, SupportTicketReplyAttachment $attachment): StreamedResponse
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);
        $reply = $ticket->replies()->findOrFail($reply->id);
        $attachment = $reply->attachments()->findOrFail($attachment->id);

        return Storage::disk('support-attachments')->response($attachment->path, null, [
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    public function store(Request $request, PiiFilterService $piiFilter, DatabaseManager $database)
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
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,csv,txt,log',
        ]);
        $this->rejectSensitiveContent($piiFilter, $validated['subject']."\n".$validated['details'], 'details');

        $uploadedFiles = $request->file('attachments', []);
        if (array_sum(array_map(fn ($file): int => $file->getSize(), $uploadedFiles)) > 15 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'attachments' => 'Attachments must not exceed 15 MB in total.',
            ]);
        }

        $validated['user_id'] = $request->user()->id;
        unset($validated['attachments']);

        $storedPaths = [];
        try {
            $ticket = $database->transaction(function () use ($validated, $uploadedFiles, &$storedPaths): SupportTicket {
                $ticket = SupportTicket::create($validated);

                foreach ($uploadedFiles as $file) {
                    $extension = strtolower($file->guessExtension() ?: $file->extension() ?: 'bin');
                    $path = $file->storeAs((string) $ticket->id, Str::uuid().'.'.$extension, 'support-attachments');
                    $storedPaths[] = $path;
                    $originalName = basename(str_replace('\\', '/', $file->getClientOriginalName()));

                    SupportTicketAttachment::create([
                        'support_ticket_id' => $ticket->id,
                        'original_name' => Str::limit($originalName, 255, ''),
                        'path' => $path,
                        'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                        'size' => $file->getSize(),
                    ]);
                }

                return $ticket;
            });
        } catch (\Throwable $exception) {
            Storage::disk('support-attachments')->delete($storedPaths);

            throw $exception;
        }

        $this->sendEmails($ticket);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Your request has been submitted. We\'ll get back to you within 1-2 business days.',
                'ticket_id' => $ticket->id,
                'attachments_count' => count($uploadedFiles),
            ]);
        }

        return back()->with('success', 'Your request has been submitted. We\'ll get back to you within 1-2 business days.');
    }

    public function storeFollowUp(Request $request, SupportTicket $ticket, PiiFilterService $piiFilter)
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);

        $data = $request->validate(['body' => 'required|string|min:3|max:5000']);
        $this->rejectSensitiveContent($piiFilter, $data['body'], 'body');
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

    }

    private function unreadReplyCount(Request $request): int
    {
        return SupportTicketReply::query()
            ->whereNull('read_at')
            ->where(fn ($query) => $query->where('author_id', '!=', $request->user()->id)->orWhereNull('author_id'))
            ->whereHas('ticket', fn ($query) => $query->where('user_id', $request->user()->id))
            ->count();
    }

    private function rejectSensitiveContent(PiiFilterService $piiFilter, string $content, string $field): void
    {
        if ($piiFilter->scan($content)['flagged']) {
            throw ValidationException::withMessages([
                $field => 'Support messages cannot contain sensitive personal information. Please remove it and try again.',
            ]);
        }
    }
}

@extends('layouts.admin')

@section('page-title', 'Ticket Details')
@section('page-desc', 'Ticket: ' . $ticket->subject)

@section('content')
<div class="table-section" style="max-width:900px;">
    <div style="padding:24px;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px;padding-bottom:16px;border-bottom:1px solid var(--line);">
            <div>
                <h2 style="font-family:'Fraunces',serif;font-size:22px;margin:0 0 8px;">{{ $ticket->subject }}</h2>
                <div style="display:flex;gap:16px;flex-wrap:wrap;font-size:13px;color:var(--ink-soft);">
                    <span><strong>User:</strong> {{ $ticket->user->name }} ({{ $ticket->user->email }})</span>
                    <span><strong>Created:</strong> {{ $ticket->created_at->format('d/m/Y H:i') }}</span>
                    <span><strong>Priority:</strong> <span class="badge {{ $ticket->priority === 'high' ? 'pending' : ($ticket->priority === 'medium' ? 'new' : 'published') }}">{{ ucfirst($ticket->priority) }}</span></span>
                </div>
            </div>
        </div>

        <div style="margin-bottom:24px;padding:16px;background:var(--paper);border-radius:8px;">
            <strong style="display:block;margin-bottom:8px;">Description</strong>
            <div style="white-space:pre-wrap;">{{ $ticket->description }}</div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;">
            <div class="form-group">
                <label>Status</label>
                <form action="{{ route('admin.tickets.status', $ticket) }}" method="POST" id="statusForm">
                    @csrf @method('PATCH')
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="pending" {{ $ticket->status === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="in_progress" {{ $ticket->status === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                        <option value="resolved" {{ $ticket->status === 'resolved' ? 'selected' : '' }}>Resolved</option>
                        <option value="closed" {{ $ticket->status === 'closed' ? 'selected' : '' }}>Closed</option>
                    </select>
                </form>
            </div>
            <div class="form-group">
                <label>Assignee</label>
                <form action="{{ route('admin.tickets.assign', $ticket) }}" method="POST" id="assignForm">
                    @csrf @method('PATCH')
                    <select name="assignee_id" class="form-select" onchange="this.form.submit()">
                        <option value="">Unassigned</option>
                        @foreach($assignees as $assignee)
                        <option value="{{ $assignee->id }}" {{ $ticket->assignee_id == $assignee->id ? 'selected' : '' }}>
                            {{ $assignee->name }} ({{ $assignee->email }})
                        </option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>

        <div style="border-top:1px solid var(--line);padding-top:24px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <h3 style="font-family:'Fraunces',serif;font-size:16px;margin:0;">Admin Notes</h3>
            </div>
            <form action="{{ route('admin.tickets.show', $ticket) }}" method="POST" id="notesForm">
                @csrf @method('PUT')
                <div class="form-group" style="margin-bottom:12px;">
                    <textarea name="admin_notes" class="form-textarea" placeholder="Internal notes for admin team..." rows="4">{{ $ticket->admin_notes }}</textarea>
                </div>
                <button type="submit" class="btn-primary" style="padding:8px 16px;font-size:13px;">Save Notes</button>
            </form>
        </div>
    </div>
</div>
@endsection
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
                    <span><strong>User:</strong> {{ $ticket->user?->name ?? $ticket->name }} ({{ $ticket->user?->email ?? $ticket->email }})</span>
                    <span><strong>Created:</strong> {{ $ticket->created_at->format('d/m/Y H:i') }}</span>
                    <span><strong>Priority:</strong> <span class="badge {{ $ticket->priority === 'high' ? 'pending' : ($ticket->priority === 'medium' ? 'new' : 'published') }}">{{ ucfirst($ticket->priority) }}</span></span>
                </div>
            </div>
        </div>

        <div style="margin-bottom:24px;padding:16px;background:var(--paper);border-radius:8px;">
            <strong style="display:block;margin-bottom:8px;">Description</strong>
            <div style="white-space:pre-wrap;">{{ $ticket->details }}</div>
            @if($ticket->attachments->isNotEmpty())
                <div style="display:grid;gap:8px;margin-top:16px;padding-top:12px;border-top:1px solid var(--line);">
                    <strong>Attachments</strong>
                    @foreach($ticket->attachments as $attachment)
                        <a href="{{ route('admin.tickets.attachments.download', [$ticket, $attachment]) }}">📎 {{ $attachment->original_name }} ({{ number_format($attachment->size / 1048576, 2) }} MB)</a>
                    @endforeach
                </div>
            @endif
        </div>

        <div style="border-top:1px solid var(--line);padding-top:24px;margin-bottom:24px;">
            <h3 style="font-family:'Fraunces',serif;font-size:16px;margin:0 0 14px;">Conversation</h3>
            <div style="display:grid;gap:12px;">
                <div style="border:1px solid var(--line);border-radius:8px;padding:14px;background:var(--paper);">
                    <div style="font-size:12px;color:var(--ink-soft);margin-bottom:7px;"><strong>{{ $ticket->name }}</strong> · {{ $ticket->created_at->format('d/m/Y H:i') }}</div>
                    <div style="white-space:pre-wrap;">{{ $ticket->details }}</div>
                </div>
                @forelse($ticket->replies as $reply)
                    <div style="border:1px solid #b9decf;border-radius:8px;padding:14px;background:#f1faf6;">
                        <div style="font-size:12px;color:var(--ink-soft);margin-bottom:7px;"><strong>{{ $reply->author_id === $ticket->user_id ? ($reply->author?->name ?? $ticket->name) : ($reply->author?->name ?? 'Former staff member') }}</strong> · {{ $reply->created_at->format('d/m/Y H:i') }} · {{ $reply->author_id === $ticket->user_id ? 'Requester follow-up' : 'Public reply' }}</div>
                        <div style="white-space:pre-wrap;">{{ $reply->body }}</div>
                        @if($reply->attachments->isNotEmpty())
                            <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:12px;">
                                @foreach($reply->attachments as $attachment)
                                    <a href="{{ route('admin.tickets.reply-attachments.show', [$ticket, $reply, $attachment]) }}" target="_blank" rel="noopener" aria-label="View {{ $attachment->original_name }}">
                                        <img src="{{ route('admin.tickets.reply-attachments.show', [$ticket, $reply, $attachment]) }}" alt="{{ $attachment->original_name }}" style="display:block;max-width:min(100%,520px);max-height:420px;object-fit:contain;border:1px solid var(--line);border-radius:8px;background:#fff;">
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="text-muted" style="margin:0;">No replies have been posted yet.</p>
                @endforelse
            </div>
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
            <h3 style="font-family:'Fraunces',serif;font-size:16px;margin:0 0 8px;">Public reply</h3>
            <p class="text-muted" style="margin:0 0 14px;">The requester can read this in My Support Requests. No email will be sent.</p>
            <form action="{{ route('admin.tickets.replies.store', $ticket) }}" method="POST" enctype="multipart/form-data" style="margin-bottom:24px;" data-public-reply-form>
                @csrf
                <div class="form-group" style="margin-bottom:10px;">
                    <textarea name="body" class="form-textarea" placeholder="Write a response visible in the requester's support history..." rows="5" maxlength="5000" data-public-reply-text>{{ old('body') }}</textarea>
                    @error('body')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple hidden data-public-reply-images>
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px;">
                    <button type="button" class="btn-secondary" style="padding:7px 12px;font-size:13px;" data-choose-reply-images>Attach images</button>
                    <span class="text-muted" style="font-size:12px;">Paste or select up to 4 JPG, PNG, or WebP images (4 MB each; 16 MB total).</span>
                </div>
                <p class="text-muted" style="font-size:12px;margin:0 0 10px;">Images are visible to the requester and support admins. Avoid including sensitive student or family information.</p>
                <div data-reply-image-error role="status" class="field-error" style="display:none;margin-bottom:8px;"></div>
                <div data-reply-image-preview style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:12px;"></div>
                @error('images')<div class="field-error">{{ $message }}</div>@enderror
                @error('images.*')<div class="field-error">{{ $message }}</div>@enderror
                <label style="display:flex;align-items:center;gap:8px;font-size:13px;margin-bottom:12px;cursor:pointer;">
                    <input type="checkbox" name="resolve" value="1"> Mark this ticket as resolved after posting
                </label>
                <button type="submit" class="btn-primary" style="padding:8px 16px;font-size:13px;">Post Reply</button>
            </form>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <h3 style="font-family:'Fraunces',serif;font-size:16px;margin:0;">Admin Notes</h3>
            </div>
            <form action="{{ route('admin.tickets.notes', $ticket) }}" method="POST" id="notesForm">
                @csrf @method('PATCH')
                @error('admin_notes')<div class="field-error">{{ $message }}</div>@enderror
                <div class="form-group" style="margin-bottom:12px;">
                    <textarea name="admin_notes" class="form-textarea" placeholder="Internal notes for admin team..." rows="4">{{ $ticket->admin_notes }}</textarea>
                </div>
                <button type="submit" class="btn-primary" style="padding:8px 16px;font-size:13px;">Save Notes</button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.querySelector('[data-public-reply-form]');
    if (!form) return;

    const textarea = form.querySelector('[data-public-reply-text]');
    const input = form.querySelector('[data-public-reply-images]');
    const chooseButton = form.querySelector('[data-choose-reply-images]');
    const preview = form.querySelector('[data-reply-image-preview]');
    const error = form.querySelector('[data-reply-image-error]');
    const maxFiles = 4;
    const maxFileSize = 4 * 1024 * 1024;
    const maxTotalSize = 16 * 1024 * 1024;
    let selected = [];

    const showError = (message = '') => {
        error.textContent = message;
        error.style.display = message ? 'block' : 'none';
    };

    const syncInput = () => {
        const transfer = new DataTransfer();
        selected.forEach(file => transfer.items.add(file));
        input.files = transfer.files;
    };

    const render = () => {
        preview.replaceChildren();
        selected.forEach((file, index) => {
            const item = document.createElement('div');
            item.style.cssText = 'position:relative;width:112px;';
            const image = document.createElement('img');
            image.src = URL.createObjectURL(file);
            image.alt = file.name;
            image.style.cssText = 'width:112px;height:84px;object-fit:cover;border:1px solid var(--line);border-radius:7px;background:#fff;';
            image.addEventListener('load', () => URL.revokeObjectURL(image.src), { once: true });
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.textContent = '×';
            remove.setAttribute('aria-label', `Remove ${file.name}`);
            remove.style.cssText = 'position:absolute;top:4px;right:4px;border:0;border-radius:50%;width:24px;height:24px;background:#fff;color:#333;cursor:pointer;font-size:18px;line-height:1;';
            remove.addEventListener('click', () => {
                selected.splice(index, 1);
                syncInput();
                render();
                showError();
            });
            const name = document.createElement('div');
            name.textContent = file.name;
            name.style.cssText = 'font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-top:3px;';
            item.append(image, remove, name);
            preview.append(item);
        });
    };

    const addFiles = files => {
        let message = '';
        for (const file of files) {
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                message = 'Only JPG, PNG, and WebP images are supported.';
                continue;
            }
            if (file.size > maxFileSize) {
                message = 'Each image must be 4 MB or smaller.';
                continue;
            }
            if (selected.length >= maxFiles) {
                message = 'You can attach up to 4 images.';
                break;
            }
            if (selected.reduce((sum, item) => sum + item.size, 0) + file.size > maxTotalSize) {
                message = 'Images must not exceed 16 MB in total.';
                continue;
            }
            selected.push(file);
        }
        syncInput();
        render();
        showError(message);
    };

    chooseButton.addEventListener('click', () => input.click());
    input.addEventListener('change', () => {
        addFiles(Array.from(input.files));
    });
    textarea.addEventListener('paste', event => {
        const pastedImages = Array.from(event.clipboardData?.items ?? [])
            .filter(item => item.type.startsWith('image/'))
            .map(item => item.getAsFile())
            .filter(Boolean);
        if (!pastedImages.length) return;
        event.preventDefault();
        addFiles(pastedImages);
    });
    form.addEventListener('submit', () => syncInput());
})();
</script>
@endpush

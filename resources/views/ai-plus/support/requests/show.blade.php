@extends('layouts.ai-plus')

@section('title', 'Support Request #' . $ticket->id . ' — AI+ LSTS')
@section('breadcrumb', 'Support / My Requests / #' . $ticket->id)

@section('content')
<header class="page-header">
  <div class="wrap">
    <a class="back-requests" href="{{ route('ai-plus.support.requests.index') }}">← My Support Requests</a>
    <h1>{{ $ticket->subject }}</h1>
    <p>Request #{{ $ticket->id }} · <span class="status status-{{ $ticket->status }}">{{ str_replace('_', ' ', $ticket->status) }}</span></p>
  </div>
</header>

<main class="content"><div class="wrap" style="max-width:900px;">
  <section class="message request-message">
    <div class="message-meta">You · {{ $ticket->created_at->format('d/m/Y H:i') }} · {{ $ticket->type }}</div>
    <div class="message-body">{{ $ticket->details }}</div>
  </section>
  @forelse($ticket->replies as $reply)
    <section class="message reply-message">
      <div class="message-meta">{{ $reply->author_id === $ticket->user_id ? 'You' : 'AI+ Support' }} · {{ $reply->created_at->format('d/m/Y H:i') }}</div>
      <div class="message-body">{{ $reply->body }}</div>
    </section>
  @empty
    <div class="waiting">No reply yet. The support team will respond by email and update this page.</div>
  @endforelse
  <section class="follow-up">
    <h2>Send a follow-up</h2>
    <p>Add context, ask a question, or reopen this request if you still need help.</p>
    <form action="{{ route('ai-plus.support.requests.replies.store', $ticket) }}" method="POST">
      @csrf
      <textarea name="body" rows="4" maxlength="5000" required placeholder="Write your follow-up…">{{ old('body') }}</textarea>
      @error('body')<div class="field-error">{{ $message }}</div>@enderror
      <button type="submit">Send follow-up</button>
    </form>
  </section>
</div></main>
@endsection

@push('styles')
<style>
  .page-header{background:var(--page-header-bg);color:var(--page-header-text);padding:40px 0 48px}.back-requests{color:var(--page-header-muted);font-size:14px;text-decoration:none}.page-header h1{font-family:'Fraunces',serif;font-size:clamp(28px,4vw,40px);margin:14px 0 10px}.page-header p{color:var(--page-header-muted);font-size:15px;margin:0}.content{padding:38px 0 60px}.message{padding:20px;border-radius:12px;border:1px solid var(--surface-border);margin-bottom:14px}.request-message{background:var(--surface)}.reply-message{background:#f1faf6;border-color:#b9decf}.message-meta{font-size:13px;color:var(--text-soft);margin-bottom:10px}.message-body{white-space:pre-wrap;line-height:1.65;color:var(--text-main)}.waiting{padding:20px;background:var(--surface);border:1px dashed var(--surface-border);border-radius:12px;color:var(--text-soft)}.follow-up{margin-top:24px;padding:20px;background:var(--surface);border:1px solid var(--surface-border);border-radius:12px}.follow-up h2{font-family:'Fraunces',serif;font-size:19px;margin:0 0 6px}.follow-up p{color:var(--text-soft);font-size:14px;margin:0 0 14px}.follow-up textarea{box-sizing:border-box;width:100%;padding:12px;border:1px solid var(--input-border);border-radius:8px;background:var(--input-bg);color:var(--input-text);font:inherit;resize:vertical}.follow-up button{margin-top:10px;padding:9px 15px;border:0;border-radius:8px;background:var(--navy);color:#fff;font-weight:600;cursor:pointer}.status{padding:4px 9px;border-radius:999px;font-size:12px;font-weight:600;text-transform:capitalize}.status-pending{background:#fff3cd;color:#856404}.status-in_progress{background:#dceeff;color:#1d5d93}.status-resolved{background:#dff5e8;color:#17623a}.status-closed{background:#e9ecef;color:#495057}
</style>
@endpush

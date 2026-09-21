@extends('layouts.ai-plus')

@section('title', 'My Support Requests — AI+ LSTS')
@section('breadcrumb', 'Support / My Requests')

@section('content')
<header class="page-header">
  <div class="wrap">
    <h1>My Support Requests</h1>
    <p>Track the status of your requests and read replies from the AI+ support team.</p>
  </div>
</header>

<main class="content">
  <div class="wrap" style="max-width:900px;">
    <div style="display:flex;justify-content:flex-end;margin-bottom:18px;">
      <a class="primary-link" href="{{ route('ai-plus.support.index') }}#contact-section">Submit a Request</a>
    </div>
    @forelse($tickets as $ticket)
      <a href="{{ route('ai-plus.support.requests.show', $ticket) }}" class="ticket-card">
        <div>
          <div class="ticket-subject">#{{ $ticket->id }} · {{ $ticket->subject }}</div>
          <div class="ticket-meta">{{ $ticket->type }} · Submitted {{ $ticket->created_at->format('d/m/Y H:i') }} · {{ $ticket->replies_count }} {{ \Illuminate\Support\Str::plural('reply', $ticket->replies_count) }} @if($ticket->unread_replies_count > 0)<span class="unread-badge">{{ $ticket->unread_replies_count }} new</span>@endif</div>
        </div>
        <span class="status status-{{ $ticket->status }}">{{ str_replace('_', ' ', $ticket->status) }}</span>
      </a>
    @empty
      <div class="empty-card">
        <h2>No support requests yet</h2>
        <p>When you submit a request, its status and responses will appear here.</p>
        <a class="primary-link" href="{{ route('ai-plus.support.index') }}#contact-section">Submit your first request</a>
      </div>
    @endforelse
    <div style="margin-top:20px;">{{ $tickets->links() }}</div>
  </div>
</main>
@endsection

@push('styles')
<style>
  .page-header{background:var(--page-header-bg);color:var(--page-header-text);padding:48px 0 56px}.page-header h1{font-family:'Fraunces',serif;font-size:clamp(32px,4vw,44px);margin:0 0 12px}.page-header p{color:var(--page-header-muted);font-size:16px}.content{padding:40px 0 60px}.ticket-card{display:flex;justify-content:space-between;gap:16px;align-items:center;padding:18px 20px;margin-bottom:10px;background:var(--surface);border:1px solid var(--surface-border);border-radius:12px;color:inherit;text-decoration:none}.ticket-card:hover{border-color:var(--navy)}.ticket-subject{font-size:16px;font-weight:600;color:var(--text-main);margin-bottom:6px}.ticket-meta{font-size:13px;color:var(--text-soft)}.unread-badge{display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;margin-left:4px;padding:0 5px;border-radius:99px;background:#c0392b;color:#fff;font-size:11px;font-weight:600}.status{padding:5px 10px;border-radius:999px;font-size:12px;font-weight:600;text-transform:capitalize;white-space:nowrap}.status-pending{background:#fff3cd;color:#856404}.status-in_progress{background:#dceeff;color:#1d5d93}.status-resolved{background:#dff5e8;color:#17623a}.status-closed{background:#e9ecef;color:#495057}.empty-card{padding:38px;text-align:center;background:var(--surface);border:1px solid var(--surface-border);border-radius:12px}.empty-card h2{font-family:'Fraunces',serif;margin:0 0 8px}.empty-card p{color:var(--text-soft);margin:0 0 20px}.primary-link{display:inline-block;padding:10px 16px;background:var(--navy);color:#fff!important;border-radius:8px;text-decoration:none;font-size:14px;font-weight:600}@media(max-width:600px){.ticket-card{align-items:flex-start;flex-direction:column}}
</style>
@endpush

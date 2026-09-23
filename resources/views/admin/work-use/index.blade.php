@extends('layouts.admin')

@section('page-title', 'Work-Use Monitoring')
@section('page-desc', 'Review only requests that may need attention; routine school-work requests are not retained here')

@section('content')
@if(session('success'))<div class="alert-success">{{ session('success') }}</div>@endif
<section class="table-section" style="max-width:1100px;">
  <div class="table-toolbar"><h3 style="font-family:'Fraunces',serif;font-size:18px;margin:0;">Monitoring mode</h3></div>
  <form method="POST" action="{{ route('admin.work-use.update') }}" style="padding:24px;">@csrf @method('PUT')
    <label style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;">
      <input type="hidden" name="enabled" value="0"><input type="checkbox" name="enabled" value="1" {{ $enabled ? 'checked' : '' }} style="width:18px;height:18px;margin-top:2px;">
      <span><strong style="display:block;font-size:15px;">Monitor work-use scope</strong><span class="text-muted" style="display:block;margin-top:4px;">AI+ retains only ambiguous, personal/unrelated, or safety-flagged requests. Routine school-work requests are not stored in this review queue. Retained events are deleted automatically after 180 days.</span></span>
    </label>
    <div style="margin-top:18px;"><button type="submit" class="btn-primary">Save monitoring setting</button><span class="badge {{ $enabled ? 'published' : 'draft' }}" style="margin-left:10px;">{{ $enabled ? 'Monitor only' : 'Disabled' }}</span></div>
  </form>
</section>

<section class="stats-grid" style="margin-top:24px;max-width:1100px;">
  <div class="stat-card"><span class="stat-label">Review events (30 days)</span><strong class="stat-value">{{ number_format($metrics['total']) }}</strong></div>
  <div class="stat-card"><span class="stat-label">Ambiguous</span><strong class="stat-value">{{ number_format($metrics['ambiguous']) }}</strong></div>
  <div class="stat-card"><span class="stat-label">Personal / unrelated</span><strong class="stat-value">{{ number_format($metrics['personal']) }}</strong></div>
  <div class="stat-card"><span class="stat-label">Safety-blocked</span><strong class="stat-value">{{ number_format($metrics['safety_flagged']) }}</strong></div>
</section>

<section class="table-section" style="margin-top:24px;max-width:1100px;">
  <div class="table-toolbar"><h3 style="font-family:'Fraunces',serif;font-size:18px;margin:0;">Review queue</h3><span class="text-muted">Prompt contents are not stored here. Events expire after 180 days.</span></div>
  <div class="table-wrap"><table><thead><tr><th>User</th><th>Feature</th><th>Scope</th><th>Safety</th><th>Action</th><th>Time</th></tr></thead><tbody>
    @forelse($events as $event)
      <tr><td><strong>{{ $event->user?->name ?? 'Deleted user' }}</strong><br><small class="text-muted">{{ $event->user?->email }}</small></td><td>{{ str($event->feature)->headline() }}</td><td><span class="badge {{ $event->classification === 'school_work' ? 'published' : 'draft' }}">{{ str($event->classification)->replace('_', ' ')->title() }}</span></td><td>{{ $event->moderation_flagged ? 'Flagged'.($event->category ? ': '.$event->category : '') : 'Clear' }}</td><td>{{ str($event->action)->title() }}</td><td>{{ $event->created_at->format('d M Y, H:i') }}</td></tr>
    @empty
      <tr><td colspan="6" class="text-muted" style="text-align:center;padding:28px;">No monitored requests yet.</td></tr>
    @endforelse
  </tbody></table></div>
  @if($events->hasPages())<div style="padding:16px;">{{ $events->links() }}</div>@endif
</section>
@endsection

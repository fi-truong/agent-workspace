@extends('layouts.admin')

@section('page-title', 'Token Usage')
@section('page-desc', "Usage per user for {$monthName} · ".ucfirst($phase).' quota')

@section('content')
<div class="usage-page">
<section class="usage-hero">
  <div>
    <p class="eyebrow">MONTHLY OVERVIEW</p>
    <h2>{{ $monthName }} token usage</h2>
    <p>Each user has a {{ number_format($limit) }}-token {{ $phase }} allowance. Review the users approaching their limit first.</p>
  </div>
  <div class="quota-pill"><span>Per-user allowance</span><strong>{{ number_format($limit) }}</strong><small>tokens</small></div>
</section>

<section class="image-usage-card" aria-label="Image Studio usage">
  <div class="image-usage-heading"><div><p class="eyebrow">IMAGE STUDIO</p><h2>Image generation by model</h2><p>Usage for {{ $monthName }}. Image tokens are included in the monthly token totals above.</p></div></div>
  <div class="image-usage-grid">
    @foreach($imageUsage as $model)
    <article><span class="image-usage-label">{{ $model['label'] }}</span><strong>{{ number_format($model['images']) }}</strong><small>images · {{ number_format($model['tokens']) }} tokens</small></article>
    @endforeach
    <article class="image-top-users"><span class="image-usage-label">Most active users</span>@forelse($topImageUsers as $row)<div><span>{{ $row->user?->name ?? 'Deleted user' }}</span><b>{{ number_format($row->images) }} images</b></div>@empty<p>No image activity this month.</p>@endforelse</article>
  </div>
</section>

<section class="usage-stats" aria-label="Usage summary">
  <article><span class="stat-icon mint">👥</span><div><span class="stat-label">Users tracked</span><strong>{{ number_format($summary['users']) }}</strong><small>{{ number_format($summary['active_users']) }} active accounts</small></div></article>
  <article><span class="stat-icon blue">◒</span><div><span class="stat-label">Tokens used</span><strong>{{ number_format($summary['total_tokens']) }}</strong><small>Across all users this month</small></div></article>
  <article><span class="stat-icon {{ $summary['near_limit'] ? 'amber' : 'mint' }}">⚠</span><div><span class="stat-label">Need attention</span><strong>{{ number_format($summary['near_limit']) }}</strong><small>At or above 85% of quota</small></div></article>
</section>

<form method="GET" class="admin-filters" style="margin-bottom:20px;">
  <input class="filter-search" type="search" name="search" value="{{ request('search') }}" placeholder="Search users…">
  <button class="filter-btn" type="submit">Search</button>
  @if(request()->filled('search'))<a class="filter-clear" href="{{ route('admin.usage.index') }}">Clear</a>@endif
</form>

<div class="usage-table-card">
  <div class="usage-table-heading"><div><h2>User usage</h2><p>Sorted from highest to lowest usage.</p></div><span class="usage-legend"><i></i> Normal <i class="warning"></i> Near limit <i class="danger"></i> At limit</span></div>
<div class="table-section">
  <div class="table-wrap"><table class="admin-table"><thead><tr>
    <th>User</th><th>Role</th><th>Usage</th><th>Quota</th><th>Remaining</th><th>Status</th><th></th>
  </tr></thead><tbody>
  @forelse($users as $user)
    @php
      $used = (int) $user->used_tokens;
      $percentage = min(($used / $limit) * 100, 100);
      $remaining = max($limit - $used, 0);
      $status = $used >= $limit ? ['At limit', 'pending', 'danger'] : ($percentage >= 85 ? ['Near limit', 'new', 'warning'] : ['Normal', 'published', 'normal']);
    @endphp
    <tr>
      <td><div class="item-title">{{ $user->name }}</div><div class="item-sub">{{ $user->email }}</div></td>
      <td>{{ ucfirst($user->role) }}</td>
      <td class="usage-cell"><div><strong>{{ number_format($used) }}</strong> <span>{{ number_format($percentage, 1) }}%</span></div><div class="usage-bar {{ $status[2] }}"><span style="width:{{ $percentage }}%"></span></div></td>
      <td>{{ number_format($limit) }}<span class="token-caption">tokens</span></td><td>{{ number_format($remaining) }}<span class="token-caption">tokens left</span></td>
      <td><span class="badge {{ $status[1] }}">{{ $status[0] }}</span></td>
      <td><a href="{{ route('admin.users.edit', $user) }}" class="action-btn">View user</a></td>
    </tr>
  @empty
    <tr><td colspan="7" class="text-muted" style="text-align:center;padding:32px;">No users found.</td></tr>
  @endforelse
  </tbody></table></div>
</div>
</div>

{{ $users->links('pagination::admin-simple') }}
</div>
@endsection

@push('styles')
<style>
  .admin-wrap:has(.usage-page){max-width:1540px}.usage-hero{display:flex;justify-content:space-between;gap:28px;align-items:center;margin-bottom:20px;padding:28px 30px;border-radius:16px;background:linear-gradient(125deg,#183a57,#20605b);color:#fff}.usage-hero h2{margin:4px 0 8px;font:600 28px 'Fraunces',serif}.usage-hero p{max-width:610px;margin:0;color:#d6e8e4;line-height:1.55}.eyebrow{font-size:11px!important;font-weight:700;letter-spacing:.11em}.quota-pill{min-width:166px;padding:16px 18px;border:1px solid rgba(255,255,255,.25);border-radius:12px;background:rgba(255,255,255,.11);text-align:center}.quota-pill span,.quota-pill small{display:block;color:#d6e8e4;font-size:12px}.quota-pill strong{display:block;margin:4px 0;font-size:21px}.usage-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px}.usage-stats article{display:flex;align-items:center;gap:13px;padding:18px;background:var(--surface,#fff);border:1px solid var(--line);border-radius:12px}.stat-icon{display:flex;width:42px;height:42px;align-items:center;justify-content:center;border-radius:11px;font-size:18px}.stat-icon.mint{background:#dff5e8}.stat-icon.blue{background:#dceeff}.stat-icon.amber{background:#fff1d6}.stat-label,.usage-stats small{display:block;color:var(--ink-soft);font-size:12px}.usage-stats strong{display:block;margin:2px 0;font-size:21px}.image-usage-card{margin:0 0 24px;padding:24px 28px;border:1px solid var(--line);border-radius:14px;background:var(--surface,#fff)}.image-usage-heading h2{margin:3px 0 5px;font:600 20px 'Fraunces',serif}.image-usage-heading p:not(.eyebrow){margin:0;color:var(--ink-soft);font-size:13px}.image-usage-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:18px}.image-usage-grid article{padding:16px;border-radius:10px;background:var(--paper);border:1px solid var(--line)}.image-usage-label{display:block;color:var(--ink-soft);font-size:12px}.image-usage-grid strong{display:block;margin:5px 0 2px;font-size:24px}.image-usage-grid small{color:var(--ink-soft);font-size:12px}.image-top-users div{display:flex;justify-content:space-between;gap:10px;margin-top:8px;font-size:12px}.image-top-users b{font-weight:600;white-space:nowrap}.image-top-users p{color:var(--ink-soft);font-size:12px;margin:9px 0 0}.usage-table-card{padding:24px 28px;border:1px solid var(--line);border-radius:14px;background:var(--surface,#fff)}.usage-table-card .table-section{border:0;border-radius:0}.usage-table-card .admin-table th,.usage-table-card .admin-table td{padding:17px 18px}.usage-table-heading{display:flex;justify-content:space-between;gap:16px;align-items:center;margin-bottom:18px}.usage-table-heading h2{margin:0 0 4px;font:600 20px 'Fraunces',serif}.usage-table-heading p{margin:0;color:var(--ink-soft);font-size:13px}.usage-legend{color:var(--ink-soft);font-size:12px;white-space:nowrap}.usage-legend i{display:inline-block;width:8px;height:8px;margin:0 4px 0 10px;border-radius:50%;background:#2d8a69}.usage-legend i.warning{background:#d98921}.usage-legend i.danger{background:#c0392b}.usage-cell{min-width:210px}.usage-cell span{font-size:12px;color:var(--ink-soft)}.usage-bar{width:200px;height:8px;border-radius:99px;background:#e6ece9;margin-top:7px;overflow:hidden}.usage-bar span{display:block;height:100%;background:#2d8a69;border-radius:99px}.usage-bar.warning span{background:#d98921}.usage-bar.danger span{background:#c0392b}.token-caption{display:block;margin-top:3px;color:var(--ink-soft);font-size:11px}@media(max-width:820px){.admin-wrap:has(.usage-page){max-width:1280px}.usage-hero{align-items:flex-start;flex-direction:column}.usage-stats,.image-usage-grid{grid-template-columns:1fr}.usage-table-heading{align-items:flex-start;flex-direction:column}.usage-legend{white-space:normal}} 
</style>
@endpush

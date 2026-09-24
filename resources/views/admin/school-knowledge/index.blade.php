@extends('layouts.admin')

@section('page-title', 'School Knowledge Base')
@section('page-desc', 'Shared reference material used by every AI+ Chat and Agent')

@push('styles')
<style>
  .school-source-actions { display:flex; justify-content:flex-end; gap:8px; flex-wrap:nowrap; }
  .school-source-actions form { flex:0 0 auto; margin:0; }
  .school-source-actions button { white-space:nowrap; }
</style>
@endpush

@section('content')
@if(session('success'))<div class="alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert-error">{{ $errors->first() }}</div>@endif

<section class="table-section" style="max-width:960px;">
  <div class="table-toolbar"><h3 style="font-family:'Fraunces',serif;font-size:18px;margin:0;">Global availability</h3></div>
  <form method="POST" action="{{ route('admin.school-knowledge.update') }}" style="padding:22px 24px;">@csrf @method('PUT')
    <label style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;">
      <input type="hidden" name="enabled" value="0"><input type="checkbox" name="enabled" value="1" {{ $enabled ? 'checked' : '' }} style="width:18px;height:18px;margin-top:2px;">
      <span><strong style="display:block;font-size:15px;">Use School Knowledge Base in every Chat and Agent</strong><span class="text-muted" style="display:block;margin-top:4px;">AI+ retrieves only the most relevant excerpts for each request. This material is treated as reference, never as instructions.</span></span>
    </label>
    <div style="margin-top:18px;"><button type="submit" class="btn-primary">Save availability</button> <span class="badge {{ $enabled ? 'published' : 'draft' }}">{{ $enabled ? 'Enabled' : 'Disabled' }}</span></div>
  </form>
</section>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:20px;margin-top:22px;max-width:960px;">
  <section class="table-section">
    <div class="table-toolbar"><h3 style="font-family:'Fraunces',serif;font-size:18px;margin:0;">Official LSTS website</h3></div>
    <form method="POST" action="{{ route('admin.school-knowledge.website') }}" style="padding:22px 24px;">@csrf
      <p class="text-muted" style="margin-top:0;line-height:1.5;">Add a public HTTPS page from <code>lsts.edu.vn</code>. Only this domain is accepted, and it is read with the same safe web-reader limits as AI+ chat.</p>
      <label style="display:block;font-weight:600;">Page URL<input name="url" type="url" required placeholder="https://lsts.edu.vn/en/..." style="width:100%;box-sizing:border-box;margin-top:7px;padding:10px;border:1px solid var(--line);border-radius:7px;background:var(--card-bg);"></label>
      <button type="submit" class="btn-primary" style="margin-top:16px;">Add and index page</button>
    </form>
    <div style="padding:0 24px 22px;">
      <div style="border-top:1px solid var(--line);padding-top:16px;">
        <strong style="font-size:14px;">Import basic school information</strong>
        <p class="text-muted" style="margin:5px 0 11px;line-height:1.45;font-size:13px;">Add the core public information from the official school website. Existing sources are refreshed safely without creating duplicates.</p>
        <form method="POST" action="{{ route('admin.school-knowledge.import-introduction') }}">@csrf<button type="submit" class="btn-outline">Import basic school information</button></form>
      </div>
    </div>
  </section>
  <section class="table-section">
    <div class="table-toolbar"><h3 style="font-family:'Fraunces',serif;font-size:18px;margin:0;">Upload school documents</h3></div>
    <form method="POST" action="{{ route('admin.school-knowledge.upload') }}" enctype="multipart/form-data" style="padding:22px 24px;">@csrf
      <p class="text-muted" style="margin-top:0;line-height:1.5;">Up to 10 documents at once, 5 MB each and 25 MB total. Supported: {{ strtoupper(implode(', ', $allowedExtensions)) }}. Uploaded files remain private.</p>
      <input name="documents[]" type="file" multiple required accept="{{ collect($allowedExtensions)->map(fn ($extension) => '.'.$extension)->implode(',') }}" style="width:100%;">
      <button type="submit" class="btn-primary" style="margin-top:16px;">Upload and index</button>
    </form>
  </section>
</div>

<section class="table-section" style="margin-top:22px;max-width:1120px;">
  <div class="table-toolbar"><h3 style="font-family:'Fraunces',serif;font-size:18px;margin:0;">Indexed sources <span class="text-muted" style="font-family:inherit;font-size:14px;font-weight:400;">({{ $sources->count() }})</span></h3></div>
  @if($sources->isEmpty())
    <div style="padding:30px 24px;" class="text-muted">No shared sources yet. Add an official LSTS page or upload an approved school document.</div>
  @else
    <div style="overflow-x:auto;"><table class="data-table"><thead><tr><th>Source</th><th>Type</th><th>Status</th><th>Indexed</th><th>Chunks</th><th style="text-align:right;">Actions</th></tr></thead><tbody>
      @foreach($sources as $source)
      <tr>
        <td><strong>{{ $source->title }}</strong>@if($source->source_url)<a href="{{ $source->source_url }}" target="_blank" rel="noopener noreferrer" class="text-muted" style="display:block;font-size:12px;margin-top:4px;max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $source->source_url }}</a>@elseif($source->original_name)<span class="text-muted" style="display:block;font-size:12px;margin-top:4px;">Uploaded by {{ $source->creator?->name ?? 'a former administrator' }}</span>@endif @if($source->failure_reason)<small style="display:block;color:#a33;max-width:360px;margin-top:5px;">{{ $source->failure_reason }}</small>@endif</td>
        <td><span class="badge {{ $source->type === 'website' ? 'published' : 'draft' }}">{{ $source->type === 'website' ? 'Website' : 'Document' }}</span></td>
        <td><span class="badge {{ $source->status === 'ready' ? 'published' : 'draft' }}">{{ ucfirst($source->status) }}</span></td>
        <td>{{ $source->last_synced_at?->format('d M Y, H:i') ?? 'Never' }}</td>
        <td>{{ $source->chunks_count }}</td>
        <td><div class="school-source-actions">
          <form method="POST" action="{{ route('admin.school-knowledge.sync', $source) }}">@csrf<button class="btn-outline" type="submit">Re-index</button></form>
          <form method="POST" action="{{ route('admin.school-knowledge.destroy', $source) }}" data-confirm="Remove this source from the School Knowledge Base?">@csrf @method('DELETE')<button class="btn-danger" type="submit">Remove</button></form>
        </div></td>
      </tr>
      @endforeach
    </tbody></table></div>
  @endif
</section>
@endsection

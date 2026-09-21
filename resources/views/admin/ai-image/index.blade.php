@extends('layouts.admin')

@section('page-title', 'Image Generation')
@section('page-desc', 'Control access to the dedicated Image Studio in Agent Workspace')

@section('content')
@if(session('success'))<div class="alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert-error">{{ $errors->first() }}</div>@endif
<section class="table-section" style="max-width:760px;">
  <div class="table-toolbar"><h3 style="font-family:'Fraunces',serif;font-size:18px;margin:0;">Workspace visibility</h3></div>
  <form method="POST" action="{{ route('admin.ai-image.update') }}" style="padding:24px;">@csrf @method('PUT')
    <label style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;">
      <input type="hidden" name="enabled" value="0"><input type="checkbox" name="enabled" value="1" {{ $enabled ? 'checked' : '' }} style="width:18px;height:18px;margin-top:2px;">
      <span><strong style="display:block;font-size:15px;">Show Image Studio in Agent Workspace</strong><span class="text-muted" style="display:block;margin-top:4px;">When enabled, users see a separate Image section for creating 1024×1024 images. Image requests use the configured OpenAI image model and incur separate API costs.</span></span>
    </label>
    <fieldset style="margin:24px 0 0;padding:18px;border:1px solid var(--line);border-radius:10px;"><legend style="padding:0 6px;font-weight:600;">Models available to users</legend>
      <label style="display:flex;gap:10px;align-items:flex-start;cursor:pointer;"><input type="hidden" name="flare_enabled" value="0"><input type="checkbox" name="flare_enabled" value="1" {{ old('flare_enabled', $flareEnabled) ? 'checked' : '' }}><span><strong>Flare</strong><small class="text-muted" style="display:block;margin-top:3px;">{{ $models['gpt-image-2.5-flare']['description'] }}</small></span></label>
      <label style="display:flex;gap:10px;align-items:flex-start;cursor:pointer;margin-top:14px;"><input type="hidden" name="sunburst_enabled" value="0"><input type="checkbox" name="sunburst_enabled" value="1" {{ old('sunburst_enabled', $sunburstEnabled) ? 'checked' : '' }}><span><strong>Sunburst</strong><small class="text-muted" style="display:block;margin-top:3px;">{{ $models['gpt-image-2.5-sunburst']['description'] }}</small></span></label>
      <label style="display:block;margin-top:18px;font-weight:600;">Default model<select name="default_model" style="display:block;margin-top:6px;padding:8px 10px;border:1px solid var(--line);border-radius:7px;background:var(--card-bg);"><option value="gpt-image-2.5-flare" {{ old('default_model', $defaultModel) === 'gpt-image-2.5-flare' ? 'selected' : '' }}>Flare — Fast</option><option value="gpt-image-2.5-sunburst" {{ old('default_model', $defaultModel) === 'gpt-image-2.5-sunburst' ? 'selected' : '' }}>Sunburst — Precise</option></select></label>
    </fieldset>
    <div style="margin-top:24px;display:flex;align-items:center;gap:12px;"><button type="submit" class="btn-primary">Save visibility</button><span class="badge {{ $enabled ? 'published' : 'draft' }}">{{ $enabled ? 'Visible' : 'Hidden' }}</span></div>
  </form>
</section>
@endsection

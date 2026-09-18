@extends('layouts.admin')

@section('page-title', 'AI Plus Guide')
@section('page-desc', 'Control the homepage guide chatbot')

@section('content')
@if(session('success'))
  <div class="alert-success">{{ session('success') }}</div>
@endif

<section class="table-section" style="max-width:760px;">
  <div class="table-toolbar">
    <h3 style="font-family:'Fraunces',serif;font-size:18px;margin:0;">Homepage visibility</h3>
  </div>
  <form method="POST" action="{{ route('admin.ai-plus-guide.update') }}" style="padding:24px;">
    @csrf
    @method('PUT')
    <label style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;">
      <input type="hidden" name="enabled" value="0">
      <input type="checkbox" name="enabled" value="1" {{ $enabled ? 'checked' : '' }} style="width:18px;height:18px;margin-top:2px;">
      <span>
        <strong style="display:block;font-size:15px;">Show AI Plus Guide on the homepage</strong>
        <span class="text-muted" style="display:block;margin-top:4px;">When enabled, signed-in users can ask the guide where to find AI+ features. The guide cannot open files or perform actions for users.</span>
      </span>
    </label>
    <div style="margin-top:24px;display:flex;align-items:center;gap:12px;">
      <button type="submit" class="btn-primary">Save visibility</button>
      <span class="badge {{ $enabled ? 'published' : 'draft' }}">{{ $enabled ? 'Visible' : 'Hidden' }}</span>
    </div>
  </form>
</section>
@endsection

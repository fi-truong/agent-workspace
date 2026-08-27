@extends('layouts.admin')

@section('page-title', 'Edit Prompt')
@section('page-desc', 'Chỉnh sửa prompt: ' . $prompt->title)

@section('content')
<form action="{{ route('admin.prompts.update', $prompt) }}" method="POST" class="table-section" style="max-width:800px;">
    @csrf
    @method('PUT')
    <div style="padding:24px;display:flex;flex-direction:column;gap:20px;">
        <div class="form-group">
            <label>Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-input" placeholder="Enter prompt title" required value="{{ old('title', $prompt->title) }}">
        </div>
        <div class="form-group">
            <label>Subject <span class="text-danger">*</span></label>
            <select name="subject" class="form-select" required>
                <option value="">— Select Subject —</option>
                @foreach($subjects as $s)
                <option value="{{ $s }}" {{ (old('subject', $currentSubject) ?? '') == $s ? 'selected' : '' }}>{{ $s }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label>Description <span class="text-danger">*</span></label>
            <textarea name="description" class="form-textarea" placeholder="Describe what this prompt does" required>{{ old('description', $prompt->description) }}</textarea>
        </div>
        <div class="form-group">
            <label>Prompt Text <span class="text-danger">*</span></label>
            <textarea name="preview_text" class="form-textarea" placeholder="The actual prompt text..." required>{{ old('preview_text', $prompt->preview_text) }}</textarea>
        </div>
        <div class="form-group">
            <label>Status <span class="text-danger">*</span></label>
            <select name="status" class="form-select" required>
                <option value="draft" {{ old('status', $prompt->status) == 'draft' ? 'selected' : '' }}>Draft</option>
                <option value="published" {{ old('status', $prompt->status) == 'published' ? 'selected' : '' }}>Published</option>
            </select>
        </div>
        <div style="display:flex;gap:12px;justify-content:flex-end;padding-top:16px;border-top:1px solid var(--line);">
            <a href="{{ route('admin.prompts.index') }}" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary">Update Prompt</button>
        </div>
    </div>
</form>
@endsection
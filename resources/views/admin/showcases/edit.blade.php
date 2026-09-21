@extends('layouts.admin')

@section('page-title', 'Edit Showcase')
@section('page-desc', 'Update showcase: ' . $showcase->title)

@section('content')
<form action="{{ route('admin.showcases.update', $showcase) }}" method="POST" class="table-section" style="max-width:800px;">
    @csrf
    @method('PUT')
    <div style="padding:24px;display:flex;flex-direction:column;gap:20px;">
        @include('admin.partials.validation-summary')
        <div class="form-group">
            <label>Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-input" placeholder="Showcase title" required value="{{ old('title', $showcase->title) }}">
            @include('admin.partials.field-error', ['field' => 'title'])
        </div>
        <div class="form-group">
            <label>Description <span class="text-danger">*</span></label>
            <textarea name="description" class="form-textarea" placeholder="Describe the showcase" required rows="12">{{ old('description', $showcase->description) }}</textarea>
            @include('admin.partials.field-error', ['field' => 'description'])
        </div>
        <div class="form-group">
            <label>Status <span class="text-danger">*</span></label>
            <select name="status" class="form-select" required>
                <option value="draft" {{ old('status', $showcase->status) == 'draft' ? 'selected' : '' }}>Draft</option>
                <option value="pending" {{ old('status', $showcase->status) == 'pending' ? 'selected' : '' }}>Pending review</option>
                <option value="published" {{ old('status', $showcase->status) == 'published' ? 'selected' : '' }}>Published</option>
                <option value="rejected" {{ old('status', $showcase->status) == 'rejected' ? 'selected' : '' }}>Rejected</option>
            </select>
            @include('admin.partials.field-error', ['field' => 'status'])
        </div>
        <div style="display:flex;gap:12px;justify-content:flex-end;padding-top:16px;border-top:1px solid var(--line);">
            <a href="{{ route('admin.showcases.index') }}" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary">Update Showcase</button>
        </div>
    </div>
</form>
@endsection

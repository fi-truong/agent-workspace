@extends('layouts.admin')

@section('page-title', 'Create Showcase')
@section('page-desc', 'Thêm showcase mới')

@section('content')
<form action="{{ route('admin.showcases.store') }}" method="POST" class="table-section" style="max-width:800px;">
    @csrf
    <div style="padding:24px;display:flex;flex-direction:column;gap:20px;">
        <div class="form-group">
            <label>Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-input" placeholder="Showcase title" required value="{{ old('title') }}">
        </div>
        <div class="form-group">
            <label>Description <span class="text-danger">*</span></label>
            <textarea name="description" class="form-textarea" placeholder="Describe the showcase" required rows="12">{{ old('description') }}</textarea>
        </div>
        <div class="form-group">
            <label>Status <span class="text-danger">*</span></label>
            <select name="status" class="form-select" required>
                <option value="draft" {{ old('status', 'draft') == 'draft' ? 'selected' : '' }}>Draft</option>
                <option value="pending" {{ old('status') == 'pending' ? 'selected' : '' }}>Pending review</option>
                <option value="published" {{ old('status') == 'published' ? 'selected' : '' }}>Published</option>
                <option value="rejected" {{ old('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
            </select>
        </div>
        <div style="display:flex;gap:12px;justify-content:flex-end;padding-top:16px;border-top:1px solid var(--line);">
            <a href="{{ route('admin.showcases.index') }}" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary">Create Showcase</button>
        </div>
    </div>
</form>
@endsection

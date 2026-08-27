@extends('layouts.admin')

@section('page-title', 'Create Template')
@section('page-desc', 'Thêm agent template mới')

@section('content')
<form action="{{ route('admin.templates.store') }}" method="POST" class="table-section" style="max-width:900px;">
    @csrf
    <div style="padding:24px;display:flex;flex-direction:column;gap:20px;">
        <div class="form-group">
            <label>Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-input" placeholder="Template name" required value="{{ old('name') }}">
        </div>
        <div class="form-group">
            <label>Description <span class="text-danger">*</span></label>
            <textarea name="description" class="form-textarea" placeholder="Describe this template" required>{{ old('description') }}</textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
            <div class="form-group">
                <label>Category</label>
                <select name="category" class="form-select">
                    <option value="">— Select Category —</option>
                    @foreach($categories as $c)
                    <option value="{{ $c }}" {{ old('category') == $c ? 'selected' : '' }}>{{ $c }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Status <span class="text-danger">*</span></label>
                <select name="status" class="form-select" required>
                    <option value="draft" {{ old('status', 'draft') == 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="published" {{ old('status') == 'published' ? 'selected' : '' }}>Published</option>
                    <option value="archived" {{ old('status') == 'archived' ? 'selected' : '' }}>Archived</option>
                </select>
            </div>
        </div>

        <div class="form-group" style="border-top:1px solid var(--line);padding-top:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <label>Features</label>
                <button type="button" class="btn-secondary" style="padding:6px 12px;font-size:12px;" onclick="addFeatureRow()">+ Add Feature</button>
            </div>
            <div id="featuresContainer">
                <div class="feature-row" style="display:flex;gap:8px;align-items:flex-start;margin-bottom:12px;padding:12px;background:var(--paper);border-radius:8px;">
                    <input type="text" name="features[0][title]" class="form-input" placeholder="Feature title" required style="flex:1;">
                    <textarea name="features[0][description]" class="form-textarea" placeholder="Description (optional)" style="flex:1;min-height:60px;"></textarea>
                    <input type="text" name="features[0][icon]" class="form-input" placeholder="Icon (emoji/class)" style="width:120px;">
                    <button type="button" class="action-btn danger" onclick="this.closest('.feature-row').remove()" style="margin-top:30px;">Remove</button>
                </div>
            </div>
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end;padding-top:16px;border-top:1px solid var(--line);">
            <a href="{{ route('admin.templates.index') }}" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary">Create Template</button>
        </div>
    </div>
</form>

@push('scripts')
<script>
let featureIndex = 1;
function addFeatureRow() {
    const container = document.getElementById('featuresContainer');
    const row = document.createElement('div');
    row.className = 'feature-row';
    row.style.cssText = 'display:flex;gap:8px;align-items:flex-start;margin-bottom:12px;padding:12px;background:var(--paper);border-radius:8px;';
    row.innerHTML = `
        <input type="text" name="features[${featureIndex}][title]" class="form-input" placeholder="Feature title" required style="flex:1;">
        <textarea name="features[${featureIndex}][description]" class="form-textarea" placeholder="Description (optional)" style="flex:1;min-height:60px;"></textarea>
        <input type="text" name="features[${featureIndex}][icon]" class="form-input" placeholder="Icon (emoji/class)" style="width:120px;">
        <button type="button" class="action-btn danger" onclick="this.closest('.feature-row').remove()" style="margin-top:30px;">Remove</button>
    `;
    container.appendChild(row);
    featureIndex++;
}
</script>
@endpush
@endsection
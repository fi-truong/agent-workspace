@extends('layouts.admin')

@section('page-title', 'Edit FAQ')
@section('page-desc', 'Chỉnh sửa FAQ')

@section('content')
<form action="{{ route('admin.faqs.update', $faq) }}" method="POST" class="table-section" style="max-width:800px;">
    @csrf
    @method('PUT')
    <div style="padding:24px;display:flex;flex-direction:column;gap:20px;">
        <div class="form-group">
            <label>Question <span class="text-danger">*</span></label>
            <input type="text" name="question" class="form-input" placeholder="FAQ question" required value="{{ old('question', $faq->question) }}" maxlength="500">
        </div>
        <div class="form-group">
            <label>Answer <span class="text-danger">*</span></label>
            <textarea name="answer" class="form-textarea" placeholder="FAQ answer" required rows="8">{{ old('answer', $faq->answer) }}</textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
            <div class="form-group">
                <label>Category</label>
                <select name="category" class="form-select">
                    <option value="">— Select Category —</option>
                    @foreach($categories as $c)
                    <option value="{{ $c }}" {{ (old('category', $faq->category) ?? '') == $c ? 'selected' : '' }}>{{ $c }}</option>
                    @endforeach
                    <option value="general" {{ (old('category', $faq->category) ?? '') == 'general' ? 'selected' : '' }}>General</option>
                    <option value="technical" {{ (old('category', $faq->category) ?? '') == 'technical' ? 'selected' : '' }}>Technical</option>
                    <option value="billing" {{ (old('category', $faq->category) ?? '') == 'billing' ? 'selected' : '' }}>Billing</option>
                    <option value="account" {{ (old('category', $faq->category) ?? '') == 'account' ? 'selected' : '' }}>Account</option>
                </select>
            </div>
            <div class="form-group">
                <label>Sort Order</label>
                <input type="number" name="sort_order" class="form-input" placeholder="0" min="0" value="{{ old('sort_order', $faq->sort_order ?? 0) }}">
            </div>
        </div>
        <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                <input type="checkbox" name="is_published" value="1" {{ old('is_published', $faq->is_published) ? 'checked' : '' }} style="width:18px;height:18px;">
                <span>Published</span>
            </label>
        </div>
        <div style="display:flex;gap:12px;justify-content:flex-end;padding-top:16px;border-top:1px solid var(--line);">
            <a href="{{ route('admin.faqs.index') }}" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary">Update FAQ</button>
        </div>
    </div>
</form>
@endsection
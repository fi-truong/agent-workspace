@extends('layouts.admin')

@section('page-title', 'FAQ')
@section('page-desc', 'Quản lý câu hỏi thường gặp')

@section('content')
@include('admin.partials.filters', [
    'searchPlaceholder' => 'Search FAQs...',
    'searchValue' => request('search'),
    'filters' => [
        ['key' => 'category', 'label' => 'Category', 'options' => collect($categories)->mapWithKeys(fn($c) => [$c => $c])->all(), 'selected' => request('category')],
    ],
    'sortOptions' => ['newest' => 'Newest', 'oldest' => 'Oldest', 'alpha' => 'A–Z'],
    'sortValue' => request('sort', 'newest'),
    'createUrl' => route('admin.faqs.create'),
    'createLabel' => 'Add FAQ',
])

@include('admin.partials.table', [
    'headers' => ['Question', 'Category', 'Status', 'Order', 'Actions'],
    'rows' => $faqs,
    'renderRow' => function($faq) {
        $csrf = csrf_field();
        $method = method_field('DELETE');
        return [
            '<div><div class="item-title">' . e($faq->question) . '</div><div class="item-sub">' . Str::limit($faq->answer, 80) . '</div></div>',
            '<span class="badge new">' . e($faq->category ?? 'General') . '</span>',
            '<span class="badge ' . ($faq->is_published ? 'published' : 'draft') . '">' . ($faq->is_published ? 'Published' : 'Draft') . '</span>',
            $faq->sort_order ?? 0,
            '<div class="action-group">
                <a href="' . route('admin.faqs.edit', $faq) . '" class="action-btn">Edit</a>
                <form action="' . route('admin.faqs.destroy', $faq) . '" method="POST" style="display:inline" onsubmit="return confirm(\'Delete this FAQ?\')">
                    ' . $csrf . $method . '
                    <button type="submit" class="action-btn danger">Delete</button>
                </form>
            </div>',
        ];
    },
    'emptyMessage' => 'No FAQs found',
    'sortable' => true,
])

{{ $faqs->links('pagination::admin-simple') }}
@endsection
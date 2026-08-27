@extends('layouts.admin')

@section('page-title', 'Agent Templates')
@section('page-desc', 'Quản lý agent templates')

@section('content')
@include('admin.partials.filters', [
    'searchPlaceholder' => 'Search templates...',
    'searchValue' => request('search'),
    'filters' => [
        ['key' => 'category', 'label' => 'Category', 'options' => collect($categories)->mapWithKeys(fn($c) => [$c => $c])->all(), 'selected' => request('category')],
        ['key' => 'status', 'label' => 'Status', 'options' => $statuses, 'selected' => request('status')],
    ],
    'sortOptions' => ['newest' => 'Newest', 'oldest' => 'Oldest', 'alpha' => 'A–Z'],
    'sortValue' => request('sort', 'newest'),
    'createUrl' => route('admin.templates.create'),
    'createLabel' => 'Add Template',
])

@include('admin.partials.table', [
    'headers' => ['Name', 'Category', 'Status', 'Features', 'Created', 'Actions'],
    'rows' => $templates,
    'renderRow' => function($template) {
        $csrf = csrf_field();
        $method = method_field('DELETE');
        return [
            '<div><div class="item-title">' . e($template->name) . '</div><div class="item-sub">' . Str::limit($template->description, 60) . '</div></div>',
            '<span class="badge new">' . e($template->category ?? '—') . '</span>',
            '<span class="badge ' . ($template->status === 'published' ? 'published' : ($template->status === 'archived' ? 'draft' : 'pending')) . '">' . ucfirst($template->status) . '</span>',
            $template->features_count ?? $template->features->count(),
            $template->created_at?->format('d/m/Y') ?? '—',
            '<div class="action-group">
                <a href="' . route('admin.templates.edit', $template) . '" class="action-btn">Edit</a>
                <form action="' . route('admin.templates.destroy', $template) . '" method="POST" style="display:inline" onsubmit="return confirm(\'Delete this template?\')">
                    ' . $csrf . $method . '
                    <button type="submit" class="action-btn danger">Delete</button>
                </form>
            </div>',
        ];
    },
    'emptyMessage' => 'No templates found',
    'sortable' => true,
])

{{ $templates->links('pagination::admin-simple') }}
@endsection
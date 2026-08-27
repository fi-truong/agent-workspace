@extends('layouts.admin')

@section('page-title', 'Prompt Library')
@section('page-desc', 'Quản lý prompt library')

@section('content')
@include('admin.partials.filters', [
    'searchPlaceholder' => 'Search prompts...',
    'searchValue' => request('search'),
    'filters' => [
        ['key' => 'subject', 'label' => 'Subject', 'options' => collect($subjects)->mapWithKeys(fn($s) => [$s => $s])->all(), 'selected' => request('subject')],
        ['key' => 'status', 'label' => 'Status', 'options' => $statuses, 'selected' => request('status')],
    ],
    'sortOptions' => ['newest' => 'Newest', 'oldest' => 'Oldest', 'alpha' => 'A–Z'],
    'sortValue' => request('sort', 'newest'),
    'createUrl' => route('admin.prompts.create'),
    'createLabel' => 'Add Prompt',
])

@include('admin.partials.table', [
    'headers' => ['Title', 'Subject', 'Status', 'Created', 'Actions'],
    'rows' => $prompts,
    'renderRow' => function($prompt) {
        $subject = $prompt->tags->where('category', 'subject')->first()?->name ?? '—';
        $csrf = csrf_field();
        $method = method_field('DELETE');
        return [
            '<div><div class="item-title">' . e($prompt->title) . '</div><div class="item-sub">' . Str::limit($prompt->description, 60) . '</div></div>',
            '<span class="badge new">' . e($subject) . '</span>',
            '<span class="badge ' . ($prompt->status === 'published' ? 'published' : 'draft') . '">' . ucfirst($prompt->status) . '</span>',
            $prompt->created_at?->format('d/m/Y') ?? '—',
            '<div class="action-group">
                <a href="' . route('admin.prompts.edit', $prompt) . '" class="action-btn">Edit</a>
                <form action="' . route('admin.prompts.destroy', $prompt) . '" method="POST" style="display:inline" onsubmit="return confirm(\'Delete this prompt?\')">
                    ' . $csrf . $method . '
                    <button type="submit" class="action-btn danger">Delete</button>
                </form>
            </div>',
        ];
    },
    'emptyMessage' => 'No prompts found',
    'sortable' => true,
])

{{ $prompts->links('pagination::admin-simple') }}

@include('admin.partials.form-modal', [
    'id' => 'deleteConfirmModal',
    'title' => 'Confirm Delete',
    'route' => '#',
    'method' => 'POST',
    'fields' => [],
    'submitLabel' => 'Delete',
    'extraHidden' => ['_method' => 'DELETE'],
])
@endsection
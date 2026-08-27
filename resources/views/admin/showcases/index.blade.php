@extends('layouts.admin')

@section('page-title', 'Showcase')
@section('page-desc', 'Quản lý showcase posts')

@section('content')
@include('admin.partials.filters', [
    'searchPlaceholder' => 'Search showcases...',
    'searchValue' => request('search'),
    'filters' => [
        ['key' => 'status', 'label' => 'Status', 'options' => $statuses, 'selected' => request('status')],
    ],
    'sortOptions' => ['newest' => 'Newest', 'oldest' => 'Oldest', 'popular' => 'Most Viewed', 'alpha' => 'A–Z'],
    'sortValue' => request('sort', 'newest'),
    'createUrl' => route('admin.showcases.create'),
    'createLabel' => 'Add Showcase',
])

@include('admin.partials.table', [
    'headers' => ['Title', 'Author', 'Status', 'Views', 'Published', 'Actions'],
    'rows' => $showcases,
    'renderRow' => function($showcase) {
        $csrf = csrf_field();
        $method = method_field('DELETE');
        return [
            '<div><div class="item-title">' . e($showcase->title) . '</div><div class="item-sub">' . Str::limit($showcase->content, 80) . '</div></div>',
            $showcase->author?->name ?? '—',
            '<span class="badge ' . ($showcase->status === 'published' ? 'published' : 'draft') . '">' . ucfirst($showcase->status) . '</span>',
            $showcase->views_count ?? 0,
            $showcase->published_at?->format('d/m/Y') ?? '—',
            '<div class="action-group">
                <a href="' . route('admin.showcases.edit', $showcase) . '" class="action-btn">Edit</a>
                <form action="' . route('admin.showcases.destroy', $showcase) . '" method="POST" style="display:inline" onsubmit="return confirm(\'Delete this showcase?\')">
                    ' . $csrf . $method . '
                    <button type="submit" class="action-btn danger">Delete</button>
                </form>
            </div>',
        ];
    },
    'emptyMessage' => 'No showcases found',
    'sortable' => true,
])

{{ $showcases->links('pagination::admin-simple') }}
@endsection
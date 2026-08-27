@extends('layouts.admin')

@section('page-title', 'Support Tickets')
@section('page-desc', 'Quản lý support tickets')

@section('content')
@include('admin.partials.filters', [
    'searchPlaceholder' => 'Search tickets...',
    'searchValue' => request('search'),
    'filters' => [
        ['key' => 'status', 'label' => 'Status', 'options' => $statuses, 'selected' => request('status')],
        ['key' => 'assignee_id', 'label' => 'Assignee', 'options' => collect($assignees)->mapWithKeys(fn($u) => [$u->id => $u->name])->all(), 'selected' => request('assignee_id')],
    ],
    'sortOptions' => ['newest' => 'Newest', 'oldest' => 'Oldest', 'priority' => 'Priority'],
    'sortValue' => request('sort', 'newest'),
    'createUrl' => '#',
    'createLabel' => '',
])

@include('admin.partials.table', [
    'headers' => ['Subject', 'User', 'Status', 'Assignee', 'Priority', 'Created', 'Actions'],
    'rows' => $tickets,
    'renderRow' => function($ticket) {
        return [
            '<div><div class="item-title">' . e($ticket->subject) . '</div><div class="item-sub">' . Str::limit($ticket->description, 80) . '</div></div>',
            $ticket->user?->name . ' (' . $ticket->user?->email . ')',
            '<span class="badge ' . ($ticket->status === 'resolved' ? 'resolved' : ($ticket->status === 'in_progress' ? 'in_progress' : ($ticket->status === 'closed' ? 'draft' : 'pending'))) . '">' . ucfirst(str_replace('_', ' ', $ticket->status)) . '</span>',
            $ticket->assignee?->name ?? '<span class="text-muted">Unassigned</span>',
            '<span class="badge ' . ($ticket->priority === 'high' ? 'pending' : ($ticket->priority === 'medium' ? 'new' : 'published')) . '">' . ucfirst($ticket->priority) . '</span>',
            $ticket->created_at?->format('d/m/Y H:i') ?? '—',
            '<div class="action-group">
                <a href="' . route('admin.tickets.show', $ticket) . '" class="action-btn">View</a>
            </div>',
        ];
    },
    'emptyMessage' => 'No tickets found',
    'sortable' => true,
])

{{ $tickets->links('pagination::admin-simple') }}
@endsection
@extends('layouts.admin')

@section('page-title', 'Users & Roles')
@section('page-desc', 'Quản lý người dùng và phân quyền')

@section('content')
@include('admin.partials.filters', [
    'searchPlaceholder' => 'Search users...',
    'searchValue' => request('search'),
    'filters' => [
        ['key' => 'role', 'label' => 'Role', 'options' => collect($roles)->mapWithKeys(fn($r) => [$r => ucfirst($r)])->all(), 'selected' => request('role')],
        ['key' => 'department', 'label' => 'Department', 'options' => collect($departments)->mapWithKeys(fn($d) => [$d => $d])->all(), 'selected' => request('department')],
    ],
    'sortOptions' => ['newest' => 'Newest', 'oldest' => 'Oldest', 'alpha' => 'A–Z'],
    'sortValue' => request('sort', 'newest'),
    'createUrl' => route('admin.users.create'),
    'createLabel' => 'Add User',
])

@include('admin.partials.table', [
    'headers' => ['Name', 'Email', 'Role', 'Department', 'Employee ID', 'Created', 'Actions'],
    'rows' => $users,
    'renderRow' => function($user) {
        $roleBadge = match($user->role) {
            'admin' => 'pending',
            'staff' => 'in_progress',
            'teacher' => 'published',
            default => 'new',
        };
        $csrf = csrf_field();
        $method = method_field('DELETE');
        $currentUserId = auth()->id();
        $deleteForm = ($user->id !== $currentUserId)
            ? '<form action="' . route('admin.users.destroy', $user) . '" method="POST" style="display:inline" onsubmit="return confirm(\'Delete this user?\')">' . $csrf . $method . '<button type="submit" class="action-btn danger">Delete</button></form>'
            : '';
        return [
            '<div><div class="item-title">' . e($user->name) . '</div><div class="item-sub">' . e($user->email) . '</div></div>',
            '',
            '<span class="badge ' . $roleBadge . '">' . ucfirst($user->role) . '</span>',
            $user->department ?? '<span class="text-muted">—</span>',
            $user->employee_id ?? '<span class="text-muted">—</span>',
            $user->created_at?->format('d/m/Y') ?? '—',
            '<div class="action-group">
                <a href="' . route('admin.users.edit', $user) . '" class="action-btn">Edit</a>
                ' . $deleteForm . '
            </div>',
        ];
    },
    'emptyMessage' => 'No users found',
    'sortable' => true,
])

{{ $users->links('pagination::admin-simple') }}
@endsection
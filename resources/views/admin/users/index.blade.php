@extends('layouts.admin')

@section('page-title', 'Users & Roles')
@section('page-desc', 'Manage user accounts, roles, and access')

@section('content')
@if(session('success'))
<div style="margin-bottom:16px;padding:12px 16px;border-radius:8px;background:#edf9f1;color:#17633b;border:1px solid #b9decf;">{{ session('success') }}</div>
@endif
@if($errors->has('user'))
<div role="alert" style="margin-bottom:16px;padding:12px 16px;border-radius:8px;background:#fff1f1;color:#9f2626;border:1px solid #f1c0c0;">{{ $errors->first('user') }}</div>
@endif
@include('admin.partials.filters', [
    'searchPlaceholder' => 'Search users...',
    'searchValue' => request('search'),
    'filters' => [
        ['key' => 'role', 'label' => 'Role', 'options' => collect($roles)->mapWithKeys(fn($r) => [$r => ucfirst($r)])->all(), 'selected' => request('role')],
        ['key' => 'department', 'label' => 'Department', 'options' => collect($departments)->mapWithKeys(fn($d) => [$d => $d])->all(), 'selected' => request('department')],
        ['key' => 'active', 'label' => 'Account status', 'options' => $activeStatuses, 'selected' => request('active')],
    ],
    'sortOptions' => ['newest' => 'Newest', 'oldest' => 'Oldest', 'alpha' => 'A–Z'],
    'sortValue' => request('sort', 'newest'),
    'createUrl' => route('admin.users.create'),
    'createLabel' => 'Add User',
])

@include('admin.partials.table', [
    'headers' => ['Name', 'Email', 'Role', 'Last sign-in', 'Department', 'Active', 'Created', 'Actions'],
    'rows' => $users,
    'renderRow' => function($user) {
        $roleBadge = match($user->role) {
            'admin' => 'pending',
            'staff' => 'in_progress',
            'teacher' => 'published',
            default => 'new',
        };
        $currentUserId = auth()->id();
        $ownedData = [
            'Agents' => $user->agents_count,
            'conversations' => $user->conversations_count,
            'workflows' => $user->workflows_count,
            'artifacts' => $user->ai_artifacts_count,
            'email drafts' => $user->email_drafts_count,
            'usage records' => $user->usage_logs_count,
            'showcase posts' => $user->showcase_posts_count,
            'library prompts' => $user->prompt_library_prompts_count,
            'support requests' => $user->support_tickets_count,
        ];
        $blockers = array_keys(array_filter($ownedData));
        $deleteForm = '';
        if ($user->id !== $currentUserId && $blockers !== []) {
            $reason = 'Cannot delete: this user still has '.implode(', ', $blockers).'. Set the account to Inactive instead.';
            $deleteForm = '<span class="delete-user-disabled" tabindex="0" data-tooltip="' . e($reason) . '"><button type="button" class="action-btn danger" disabled>Delete</button></span>';
        } elseif ($user->id !== $currentUserId) {
            $deleteForm = '<button type="button" class="action-btn danger delete-user-btn" data-delete-url="' . e(route('admin.users.destroy', $user)) . '" data-user-name="' . e($user->name) . '">Delete</button>';
        }
        return [
            '<div class="item-title">' . e($user->name) . '</div>',
            e($user->email),
            '<span class="badge ' . $roleBadge . '">' . ucfirst($user->role) . '</span>',
            $user->last_login_at
                ? e($user->last_login_at->timezone(config('app.timezone'))->format('d M Y, H:i'))
                : '<span class="text-muted">Never</span>',
            $user->department ? e($user->department) : '<span class="text-muted">—</span>',
            '<span class="badge ' . ($user->is_active ? 'published' : 'draft') . '">' . ($user->is_active ? 'Active' : 'Inactive') . '</span>',
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

<div class="modal-overlay" id="deleteUserModal" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="deleteUserModalTitle" style="max-width:480px;">
        <div class="modal-header">
            <h2 class="modal-title" id="deleteUserModalTitle">Delete user?</h2>
            <button type="button" class="modal-close" data-close-delete-modal aria-label="Close">&times;</button>
        </div>
        <div class="modal-body">
            <p style="margin:0 0 10px;">You are about to permanently delete <strong id="deleteUserName"></strong>.</p>
            <p class="text-muted" style="margin:0;line-height:1.55;">Deletion is available only when this account has no related data. Accounts with data should be set to Inactive instead.</p>
        </div>
        <form method="POST" id="deleteUserForm">
            @csrf
            @method('DELETE')
            <div class="modal-footer">
                <button type="button" class="btn-secondary" data-close-delete-modal>Cancel</button>
                <button type="submit" class="action-btn danger" style="padding:9px 15px;">Delete permanently</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('deleteUserModal');
    const form = document.getElementById('deleteUserForm');
    const userName = document.getElementById('deleteUserName');

    document.querySelectorAll('.delete-user-btn').forEach((button) => {
        button.addEventListener('click', () => {
            form.action = button.dataset.deleteUrl;
            userName.textContent = button.dataset.userName;
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
        });
    });

    const close = () => {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
    };
    document.querySelectorAll('[data-close-delete-modal]').forEach((button) => button.addEventListener('click', close));
    modal.addEventListener('click', (event) => { if (event.target === modal) close(); });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); });
});
</script>
@endpush
@endsection

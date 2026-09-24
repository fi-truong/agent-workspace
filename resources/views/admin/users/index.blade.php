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

<form action="{{ route('admin.users.token-quota.update') }}" method="POST" class="token-quota-bulk" id="tokenQuotaBulkForm">
    @csrf
    <div><p class="token-quota-eyebrow">MONTHLY TOKEN QUOTA</p><h2>Set quota for one or more users</h2><p>Custom quotas override the current phase default of {{ number_format($defaultTokenQuota) }} tokens. Reset restores the default.</p></div>
    <div class="token-quota-selected" id="tokenQuotaSelected" aria-live="polite"><span>No users selected. Tick users in the list below.</span></div>
    <label>Monthly tokens<input type="number" name="token_quota_limit" min="1" max="1000000000" step="1" placeholder="e.g. 10000000"></label>
    <div class="token-quota-actions"><button type="submit" name="action" value="set" class="btn-primary">Set custom quota</button><button type="submit" name="action" value="reset" class="btn-secondary">Use phase default</button></div>
</form>
@if($errors->has('token_quota_limit'))<p class="text-danger" style="margin:0 0 16px;">{{ $errors->first('token_quota_limit') }}</p>@endif

@include('admin.partials.table', [
    'headers' => ['Select', 'Name', 'Email', 'Role', 'Last sign-in', 'Department', 'Token quota', 'Active', 'Created', 'Actions'],
    'rows' => $users,
    'renderRow' => function($user) use ($defaultTokenQuota) {
        $roleBadge = match($user->role) {
            'admin' => 'pending',
            'staff' => 'in_progress',
            'teacher' => 'published',
            default => 'new',
        };
        $currentUserId = auth()->id();
        $quota = $user->token_quota_limit ?? $defaultTokenQuota;
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
            '<input type="checkbox" class="token-quota-user-checkbox" form="tokenQuotaBulkForm" name="user_ids[]" value="'.$user->id.'" data-user-name="'.e($user->name).'" aria-label="Select '.e($user->name).' for quota">',
            '<div class="item-title">' . e($user->name) . '</div>',
            e($user->email),
            '<span class="badge ' . $roleBadge . '">' . ucfirst($user->role) . '</span>',
            $user->last_login_at
                ? e($user->last_login_at->timezone(config('app.timezone'))->format('d M Y, H:i'))
                : '<span class="text-muted">Never</span>',
            $user->department ? e($user->department) : '<span class="text-muted">—</span>',
            '<strong>'.number_format($quota).'</strong><br><span class="text-muted">'.($user->token_quota_limit ? 'Custom' : 'Default').'</span>',
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

    const selected = document.getElementById('tokenQuotaSelected');
    const checkboxes = Array.from(document.querySelectorAll('.token-quota-user-checkbox'));
    const refreshSelectedUsers = () => {
        const checked = checkboxes.filter((checkbox) => checkbox.checked);
        selected.replaceChildren();
        if (!checked.length) {
            const empty = document.createElement('span');
            empty.textContent = 'No users selected. Tick users in the list below.';
            selected.appendChild(empty);
            return;
        }
        checked.forEach((checkbox) => {
            const name = document.createElement('span');
            name.textContent = checkbox.dataset.userName;
            selected.appendChild(name);
        });
    };
    checkboxes.forEach((checkbox) => checkbox.addEventListener('change', refreshSelectedUsers));
    refreshSelectedUsers();
});
</script>
@endpush

@push('styles')
<style>
.token-quota-bulk{display:grid;grid-template-columns:minmax(220px,1.1fr) minmax(260px,1fr) minmax(150px,.52fr) auto;gap:16px;align-items:end;margin:0 0 20px;padding:20px 22px;border:1px solid var(--line);border-radius:14px;background:linear-gradient(135deg,#f4faf7,#fff)}.token-quota-bulk h2{margin:2px 0 5px;font:600 20px 'Fraunces',serif}.token-quota-bulk p{margin:0;color:var(--ink-soft);font-size:12px;line-height:1.45}.token-quota-eyebrow{font-size:10px!important;font-weight:700;letter-spacing:.1em;color:#246352!important}.token-quota-bulk label{display:flex;flex-direction:column;gap:6px;font-size:12px;font-weight:700;color:var(--ink)}.token-quota-bulk input{width:100%;box-sizing:border-box;border:1px solid var(--line);border-radius:8px;background:#fff;padding:9px;font:13px inherit;color:var(--ink)}.token-quota-selected{min-height:42px;display:flex;flex-wrap:wrap;align-content:flex-start;gap:6px;padding:9px;border:1px dashed #b5cfc5;border-radius:8px;background:#fff;font-size:12px;color:var(--ink-soft)}.token-quota-selected span:not(:only-child){padding:3px 7px;border-radius:99px;background:#e0f1e9;color:#1e5d4c;font-weight:600}.token-quota-actions{display:flex;flex-direction:column;gap:8px}.token-quota-actions button{white-space:nowrap}.data-table th:first-child,.data-table td:first-child{width:34px;text-align:center}@media(max-width:1100px){.token-quota-bulk{grid-template-columns:1fr 1fr}.token-quota-bulk>div:first-child{grid-column:1/-1}}@media(max-width:640px){.token-quota-bulk{grid-template-columns:1fr}}
</style>
@endpush
@endsection

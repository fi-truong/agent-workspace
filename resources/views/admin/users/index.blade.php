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
        ['key' => 'department', 'label' => 'Unit / Department', 'options' => collect($departments)->mapWithKeys(fn($d) => [$d => $d])->all(), 'selected' => request('department')],
        ['key' => 'active', 'label' => 'Account status', 'options' => $activeStatuses, 'selected' => request('active')],
    ],
    'sortOptions' => ['newest' => 'Newest', 'oldest' => 'Oldest', 'alpha' => 'A–Z'] + (request()->filled('sort_by') ? ['column' => 'Column sort'] : []),
    'sortValue' => request()->filled('sort_by') ? 'column' : request('sort', 'newest'),
    'createUrl' => route('admin.users.create'),
    'createLabel' => 'Add User',
])

<section class="organization-unit-manager" aria-labelledby="organizationUnitsHeading">
    <div class="organization-unit-copy">
        <h2 id="organizationUnitsHeading">Organization units</h2>
        <p>Create units here, then select them when creating or editing a user.</p>
    </div>
    <form action="{{ route('admin.users.units.store') }}" method="POST" class="organization-unit-form">
        @csrf
        <label for="newOrganizationUnit">New unit</label>
        <div class="organization-unit-input-row">
            <input id="newOrganizationUnit" name="unit_name" class="form-input" maxlength="100" required value="{{ old('unit_name') }}" placeholder="e.g. Human Resources">
            <button type="submit" class="btn-primary">Add unit</button>
        </div>
        @include('admin.partials.field-error', ['field' => 'unit_name'])
    </form>
    <div class="organization-unit-list" aria-label="Available units">
        <span class="organization-unit-list-label">Available units</span>
        @forelse($departments as $department)
            <span class="organization-unit-chip">{{ $department }}</span>
        @empty
            <span class="text-muted">No units created yet.</span>
        @endforelse
    </div>
</section>

<form action="{{ route('admin.users.token-quota.update') }}" method="POST" class="token-quota-bulk" id="tokenQuotaBulkForm">
    @csrf
    <div><p class="token-quota-eyebrow">MONTHLY AI+ CREDITS</p><h2>Set quota for one or more users</h2><p>Custom quotas override the current phase default of {{ \App\Services\ChatModelService::formatCredits($defaultTokenQuota) }} credits. Reset restores the default.</p></div>
    <div class="token-quota-selected" id="tokenQuotaSelected" aria-live="polite"><span>No users selected. Tick users in the list below.</span></div>
    <label>Monthly credits<input type="number" name="credit_quota_limit" min="0.001" max="1000000" step="0.001" placeholder="e.g. 10000"></label>
    <div class="token-quota-actions"><button type="submit" name="action" value="set" class="btn-primary">Set custom quota</button><button type="submit" name="action" value="reset" class="btn-secondary">Use phase default</button></div>
</form>
@if($errors->has('token_quota_limit'))<p class="text-danger" style="margin:0 0 16px;">{{ $errors->first('token_quota_limit') }}</p>@endif
@if($errors->has('credit_quota_limit'))<p class="text-danger" style="margin:0 0 16px;">{{ $errors->first('credit_quota_limit') }}</p>@endif

@include('admin.partials.table', [
    'headers' => ['Select', 'Name', 'Email', 'Role', 'Last sign-in', 'Unit / Department', 'Credit quota', 'Active', 'Created', 'Actions'],
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
            '<strong>'.\App\Services\ChatModelService::formatCredits($quota).'</strong><br><span class="text-muted">'.($user->token_quota_limit ? 'Custom' : 'Default').' credits</span>',
            '<span class="badge ' . ($user->is_active ? 'published' : 'draft') . '">' . ($user->is_active ? 'Active' : 'Inactive') . '</span>',
            $user->created_at?->format('d/m/Y') ?? '—',
            '<div class="action-group">
                <a href="' . route('admin.users.edit', $user) . '" class="action-btn" aria-label="Edit user ' . e($user->name) . '">Edit user</a>
                ' . $deleteForm . '
            </div>',
        ];
    },
    'emptyMessage' => 'No users found',
    'sortColumns' => [1 => 'name', 2 => 'email', 3 => 'role', 4 => 'last_login_at', 5 => 'department', 6 => 'token_quota_limit', 7 => 'is_active', 8 => 'created_at'],
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
.organization-unit-manager{display:grid;grid-template-columns:minmax(200px,.8fr) minmax(300px,1fr);gap:14px 24px;align-items:start;margin:0 0 20px;padding:18px 20px;border:1px solid var(--line);border-radius:14px;background:var(--card-bg)}.organization-unit-copy h2{margin:0 0 5px;font:600 20px 'Fraunces',serif;color:var(--ink)}.organization-unit-copy p{margin:0;color:var(--ink-soft);font-size:14px;line-height:1.45}.organization-unit-form>label{display:block;margin-bottom:6px;font-size:14px;font-weight:700;color:var(--ink)}.organization-unit-input-row{display:flex;gap:8px}.organization-unit-input-row input{flex:1;min-width:0}.organization-unit-input-row button{white-space:nowrap}.organization-unit-list{grid-column:1/-1;display:flex;align-items:center;gap:7px;flex-wrap:wrap;padding-top:12px;border-top:1px solid var(--line)}.organization-unit-list-label{margin-right:4px;color:var(--ink-soft);font-size:13px;font-weight:700}.organization-unit-chip{padding:4px 9px;border:1px solid var(--line);border-radius:999px;background:var(--paper);color:var(--ink);font-size:13px}@media(max-width:700px){.organization-unit-manager{grid-template-columns:1fr}.organization-unit-list{grid-column:auto}.organization-unit-input-row{align-items:stretch;flex-direction:column}}
.token-quota-bulk{display:grid;grid-template-columns:minmax(220px,1.1fr) minmax(260px,1fr) minmax(150px,.52fr) auto;gap:16px;align-items:end;margin:0 0 20px;padding:20px 22px;border:1px solid var(--line);border-radius:14px;background:linear-gradient(135deg,#f4faf7,#fff)}.token-quota-bulk h2{margin:2px 0 5px;font:600 20px 'Fraunces',serif}.token-quota-bulk p{margin:0;color:var(--ink-soft);font-size:14px;line-height:1.45}.token-quota-eyebrow{font-size:12px!important;font-weight:700;letter-spacing:.1em;color:#246352!important}.token-quota-bulk label{display:flex;flex-direction:column;gap:6px;font-size:14px;font-weight:700;color:var(--ink)}.token-quota-bulk input{width:100%;box-sizing:border-box;border:1px solid var(--line);border-radius:8px;background:#fff;padding:9px;font:15px inherit;color:var(--ink)}.token-quota-selected{min-height:42px;display:flex;flex-wrap:wrap;align-content:flex-start;gap:6px;padding:9px;border:1px dashed #b5cfc5;border-radius:8px;background:#fff;font-size:14px;color:var(--ink-soft)}.token-quota-selected span:not(:only-child){padding:3px 7px;border-radius:99px;background:#e0f1e9;color:#1e5d4c;font-weight:600}.token-quota-actions{display:flex;flex-direction:column;gap:8px}.token-quota-actions button{white-space:nowrap}.data-table th:first-child,.data-table td:first-child{width:34px;text-align:center}@media(max-width:1100px){.token-quota-bulk{grid-template-columns:1fr 1fr}.token-quota-bulk>div:first-child{grid-column:1/-1}}@media(max-width:640px){.token-quota-bulk{grid-template-columns:1fr}}
</style>
@endpush
@endsection

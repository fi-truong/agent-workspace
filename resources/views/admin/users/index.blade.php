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
        <p>Create, rename, or remove units. Renaming updates the unit assigned to its users; reassign users before deleting a unit.</p>
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
    @if($errors->has('unit_manage'))
        <p class="organization-unit-error" role="alert">{{ $errors->first('unit_manage') }}</p>
    @endif
    <div class="organization-unit-list" aria-label="Available units">
        <span class="organization-unit-list-label">Manage units</span>
        @forelse($departments as $department)
            @php($assignedCount = $unitUserCounts[mb_strtolower($department)] ?? 0)
            <details class="organization-unit-item">
                <summary>
                    <span>{{ $department }}</span>
                    <span class="organization-unit-count">{{ $assignedCount }} {{ $assignedCount === 1 ? 'user' : 'users' }} · Manage</span>
                </summary>
                <div class="organization-unit-item-actions">
                    <form action="{{ route('admin.users.units.update') }}" method="POST">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="current_unit" value="{{ $department }}">
                        <label for="renameUnit{{ $loop->index }}">Rename unit</label>
                        <div class="organization-unit-input-row">
                            <input id="renameUnit{{ $loop->index }}" name="new_name" class="form-input" maxlength="100" required value="{{ old('current_unit') === $department ? old('new_name', $department) : $department }}">
                            <button type="submit" class="btn-secondary">Save</button>
                        </div>
                        @if($errors->has('new_name') && old('current_unit') === $department)
                            <p class="organization-unit-error" role="alert">{{ $errors->first('new_name') }}</p>
                        @endif
                    </form>
                    <form action="{{ route('admin.users.units.destroy') }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="unit_name" value="{{ $department }}">
                        @if($assignedCount > 0)
                            <button type="button" class="btn-secondary" disabled>Delete unit</button>
                            <small>Reassign {{ $assignedCount }} {{ $assignedCount === 1 ? 'user' : 'users' }} before deleting.</small>
                        @else
                            <button type="submit" class="btn-secondary danger">Delete unit</button>
                        @endif
                    </form>
                </div>
            </details>
        @empty
            <span class="text-muted">No units created yet.</span>
        @endforelse
    </div>
</section>

@include('admin.partials.table', [
    'tableId' => 'usersTable',
    'headers' => ['Name', 'Email', 'Role', 'Last sign-in', 'Unit / Department', 'Active', 'Created', 'Actions'],
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
                <a href="' . route('admin.users.edit', $user) . '" class="action-btn" aria-label="Edit user ' . e($user->name) . '">Edit user</a>
                ' . $deleteForm . '
            </div>',
        ];
    },
    'emptyMessage' => 'No users found',
    'sortColumns' => [0 => 'name', 1 => 'email', 2 => 'role', 3 => 'last_login_at', 4 => 'department', 5 => 'is_active', 6 => 'created_at'],
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

@push('styles')
<style>
.organization-unit-manager{display:grid;grid-template-columns:minmax(200px,.8fr) minmax(300px,1fr);gap:14px 24px;align-items:start;margin:0 0 20px;padding:18px 20px;border:1px solid var(--line);border-radius:14px;background:var(--card-bg)}.organization-unit-copy h2{margin:0 0 5px;font:600 20px var(--lsts-font);color:var(--ink)}.organization-unit-copy p{margin:0;color:var(--ink-soft);font-size:14px;line-height:1.45}.organization-unit-form>label,.organization-unit-item label{display:block;margin-bottom:6px;font-size:14px;font-weight:700;color:var(--ink)}.organization-unit-input-row{display:flex;gap:8px}.organization-unit-input-row input{flex:1;min-width:0}.organization-unit-input-row button{white-space:nowrap}.organization-unit-list{grid-column:1/-1;display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:8px;padding-top:12px;border-top:1px solid var(--line)}.organization-unit-list-label{grid-column:1/-1;margin-right:4px;color:var(--ink-soft);font-size:13px;font-weight:700}.organization-unit-item{align-self:start;border:1px solid var(--line);border-radius:9px;background:var(--paper);color:var(--ink)}.organization-unit-item summary{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:9px 11px;cursor:pointer;font-size:14px;font-weight:600;list-style:none}.organization-unit-item summary::-webkit-details-marker{display:none}.organization-unit-item summary::after{content:'▾';color:var(--ink-soft)}.organization-unit-item[open] summary::after{content:'▴'}.organization-unit-count{margin-left:auto;color:var(--ink-soft);font-size:12px;font-weight:400;white-space:nowrap}.organization-unit-item-actions{display:grid;gap:12px;padding:11px;border-top:1px solid var(--line)}.organization-unit-item-actions form:last-child{display:flex;align-items:center;gap:9px}.organization-unit-item-actions small{color:var(--ink-soft);font-size:12px}.organization-unit-item-actions button.danger{border-color:#dba8a8;color:#a53636}.organization-unit-item-actions button:disabled{opacity:.58;cursor:not-allowed}.organization-unit-error{grid-column:1/-1;margin:0;padding:9px 12px;border:1px solid #efb8b8;border-radius:8px;background:#fff4f4;color:#962d2d;font-size:13px}.organization-unit-item .organization-unit-error{margin-top:7px}.@media(max-width:700px){.organization-unit-manager{grid-template-columns:1fr}.organization-unit-list{grid-template-columns:1fr}.organization-unit-input-row{align-items:stretch;flex-direction:column}}
#usersTable{min-width:1180px}#usersTable th:last-child,#usersTable td:last-child{position:sticky;right:0;width:184px;min-width:184px;background:var(--card-bg);box-shadow:-8px 0 12px rgba(22,48,42,.08);z-index:2}#usersTable th:last-child{background:#FAF8F2;z-index:3}#usersTable tr:hover td:last-child{background:#FAF8F2}#usersTable td:last-child .action-group{white-space:nowrap}
.token-quota-bulk{display:grid;grid-template-columns:minmax(220px,1.1fr) minmax(260px,1fr) minmax(150px,.52fr) auto;gap:16px;align-items:end;margin:0 0 20px;padding:20px 22px;border:1px solid var(--line);border-radius:14px;background:linear-gradient(135deg,#f4faf7,#fff)}.token-quota-bulk h2{margin:2px 0 5px;font:600 20px var(--lsts-font)}.token-quota-bulk p{margin:0;color:var(--ink-soft);font-size:14px;line-height:1.45}.token-quota-eyebrow{font-size:12px!important;font-weight:700;letter-spacing:.1em;color:#246352!important}.token-quota-bulk label{display:flex;flex-direction:column;gap:6px;font-size:14px;font-weight:700;color:var(--ink)}.token-quota-bulk input{width:100%;box-sizing:border-box;border:1px solid var(--line);border-radius:8px;background:#fff;padding:9px;font:15px inherit;color:var(--ink)}.token-quota-selected{min-height:42px;display:flex;flex-wrap:wrap;align-content:flex-start;gap:6px;padding:9px;border:1px dashed #b5cfc5;border-radius:8px;background:#fff;font-size:14px;color:var(--ink-soft)}.token-quota-selected span:not(:only-child){padding:3px 7px;border-radius:99px;background:#e0f1e9;color:#1e5d4c;font-weight:600}.token-quota-actions{display:flex;flex-direction:column;gap:8px}.token-quota-actions button{white-space:nowrap}.data-table th:first-child,.data-table td:first-child{width:34px;text-align:center}@media(max-width:1100px){.token-quota-bulk{grid-template-columns:1fr 1fr}.token-quota-bulk>div:first-child{grid-column:1/-1}}@media(max-width:640px){.token-quota-bulk{grid-template-columns:1fr}}
</style>
@endpush
@endsection

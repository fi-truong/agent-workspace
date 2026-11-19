<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\AppSetting;
use App\Models\User;
use App\Services\ChatModelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    private const ORGANIZATION_UNITS_SETTING = 'ai_plus_organization_units';

    private const DEFAULT_UNITS = ['CIEC', 'IT', 'Academic', 'Admin'];

    public function index(Request $request)
    {
        $defaultTokenQuota = max((int) (config('usage.token_limits.'.config('usage.phase', 'testing')) ?? config('usage.token_limits.testing', 20_000_000)), 1);
        $query = User::query()->withCount([
            'agents',
            'conversations',
            'workflows',
            'aiArtifacts',
            'emailDrafts',
            'usageLogs',
            'showcasePosts',
            'promptLibraryPrompts',
            'supportTickets',
        ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('department')) {
            $query->where('department', $request->department);
        }

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $sortableColumns = ['name', 'email', 'role', 'last_login_at', 'department', 'token_quota_limit', 'is_active', 'created_at'];
        $sortBy = $request->query('sort_by');
        $sortDirection = $request->query('sort_dir') === 'desc' ? 'desc' : 'asc';
        if (in_array($sortBy, $sortableColumns, true)) {
            if ($sortBy === 'token_quota_limit') {
                $query->orderByRaw('COALESCE(token_quota_limit, ?) '.$sortDirection, [$defaultTokenQuota]);
            } else {
                $query->orderBy($sortBy, $sortDirection);
            }
            $query->orderBy('id');
        } else {
            match ($request->get('sort', 'newest')) {
                'oldest' => $query->oldest(),
                'alpha' => $query->orderBy('name')->orderBy('id'),
                default => $query->latest(),
            };
        }

        $users = $query->paginate(15)->withQueryString();
        $roles = ['admin', 'staff', 'teacher', 'student'];
        $departments = $this->departments();
        $unitUserCounts = [];
        User::query()
            ->select('department')
            ->selectRaw('COUNT(*) as users_count')
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->groupBy('department')
            ->get()
            ->each(function (User $user) use (&$unitUserCounts): void {
                $key = $this->unitKey($user->department);
                $unitUserCounts[$key] = ($unitUserCounts[$key] ?? 0) + (int) $user->getAttribute('users_count');
            });

        $activeStatuses = ['1' => 'Active', '0' => 'Inactive'];

        return view('admin.users.index', compact('users', 'roles', 'departments', 'unitUserCounts', 'activeStatuses', 'defaultTokenQuota'));
    }

    public function storeUnit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'unit_name' => ['required', 'string', 'max:100'],
        ]);
        $unit = preg_replace('/\s+/u', ' ', trim($data['unit_name'])) ?? trim($data['unit_name']);
        $existing = array_map(mb_strtolower(...), $this->departments());
        if (in_array(mb_strtolower($unit), $existing, true)) {
            throw ValidationException::withMessages(['unit_name' => 'This unit already exists.']);
        }

        $setting = $this->saveUnitRegistry([...$this->departments(), $unit]);
        AdminAuditLog::record('organization_unit.created', $setting, ['unit' => $unit]);

        return redirect()->route('admin.users.index')->with('success', 'Unit created successfully.');
    }

    public function updateUnit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_unit' => ['required', 'string', 'max:100'],
            'new_name' => ['required', 'string', 'max:100'],
        ]);
        $units = $this->departments();
        $currentUnit = $this->findUnit($data['current_unit'], $units);
        $newName = preg_replace('/\s+/u', ' ', trim($data['new_name'])) ?? trim($data['new_name']);

        if ($currentUnit === null) {
            throw ValidationException::withMessages(['unit_manage' => 'This unit no longer exists. Refresh the page and try again.']);
        }

        if (collect($units)->contains(fn (string $unit): bool => $this->unitKey($unit) === $this->unitKey($newName) && $this->unitKey($unit) !== $this->unitKey($currentUnit))) {
            throw ValidationException::withMessages(['unit_manage' => 'Another unit already has that name.']);
        }

        $assignedUsers = User::query()->where('department', $currentUnit)->count();

        $setting = DB::transaction(function () use ($currentUnit, $newName, $units, $assignedUsers): AppSetting {
            if ($this->unitKey($currentUnit) !== $this->unitKey($newName) || $currentUnit !== $newName) {
                User::query()->where('department', $currentUnit)->update(['department' => $newName]);
            }

            $updatedUnits = array_map(
                fn (string $unit): string => $this->unitKey($unit) === $this->unitKey($currentUnit) ? $newName : $unit,
                $units,
            );
            $setting = $this->saveUnitRegistry($updatedUnits);
            AdminAuditLog::record('organization_unit.updated', $setting, [
                'from' => $currentUnit,
                'to' => $newName,
                'assigned_users' => $assignedUsers,
            ]);

            return $setting;
        });

        return redirect()->route('admin.users.index')->with('success', 'Unit updated successfully.');
    }

    public function destroyUnit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'unit_name' => ['required', 'string', 'max:100'],
        ]);
        $units = $this->departments();
        $unit = $this->findUnit($data['unit_name'], $units);

        if ($unit === null) {
            throw ValidationException::withMessages(['unit_manage' => 'This unit no longer exists. Refresh the page and try again.']);
        }

        $assignedUsers = DB::transaction(function () use ($unit, $units): int {
            $assignedUsers = User::query()->where('department', $unit)->lockForUpdate()->count();
            if ($assignedUsers > 0) {
                return $assignedUsers;
            }

            $setting = $this->saveUnitRegistry(array_values(array_filter(
                $units,
                fn (string $candidate): bool => $this->unitKey($candidate) !== $this->unitKey($unit),
            )));
            AdminAuditLog::record('organization_unit.deleted', $setting, ['unit' => $unit]);

            return 0;
        });

        if ($assignedUsers > 0) {
            return redirect()->route('admin.users.index')->withErrors([
                'unit_manage' => "Cannot delete {$unit}: {$assignedUsers} user(s) are still assigned. Reassign those users first.",
            ]);
        }

        return redirect()->route('admin.users.index')->with('success', 'Unit deleted successfully.');
    }

    public function create()
    {
        return view('admin.users.create', [
            'departments' => $this->departments(),
        ]);
    }

    public function store(Request $request)
    {
        $this->normaliseCreditQuota($request);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:admin,staff,teacher,student',
            'department' => ['nullable', 'string', 'max:100', Rule::in($this->departments())],
            'employee_id' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'token_quota_limit' => 'nullable|integer|min:1|max:1000000000',
        ]);

        $data['password'] = Hash::make($data['password']);
        // New accounts are active by default; the checkbox is checked on the form.
        $data['is_active'] = $request->has('is_active')
            ? $request->boolean('is_active')
            : true;

        $user = User::create($data);
        AdminAuditLog::record('user.created', $user, ['role' => $user->role]);

        return redirect()->route('admin.users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', [
            'user' => $user,
            'departments' => $this->departments(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->normaliseCreditQuota($request);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'required|in:admin,staff,teacher,student',
            'department' => ['nullable', 'string', 'max:100', Rule::in($this->departments())],
            'employee_id' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'token_quota_limit' => 'nullable|integer|min:1|max:1000000000',
        ]);

        // An unchecked HTML checkbox is omitted from the request. On an edit, that
        // omission intentionally means the administrator chose to deactivate it.
        $isActive = $request->boolean('is_active');

        if ($request->filled('password')) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        // Prevent self-demotion
        if ($user->id === auth()->id() && $data['role'] !== 'admin') {
            return back()->withErrors(['role' => 'Cannot change your own admin role.']);
        }

        if ($user->id === auth()->id() && ! $isActive) {
            return back()->withErrors(['is_active' => 'You cannot deactivate your own account.']);
        }

        $data['is_active'] = $isActive;

        $user->update($data);
        AdminAuditLog::record('user.updated', $user, ['role' => $user->role, 'is_active' => $user->is_active, 'token_quota_limit' => $user->token_quota_limit]);

        return redirect()->route('admin.users.index')->with('success', 'User updated successfully.');
    }

    /** Apply a custom monthly quota or restore the phase default for selected users. */
    public function updateTokenQuota(Request $request): RedirectResponse
    {
        $this->normaliseCreditQuota($request);
        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'action' => ['required', 'in:set,reset'],
            'token_quota_limit' => ['nullable', 'integer', 'min:1', 'max:1000000000'],
        ]);
        if ($data['action'] === 'set' && empty($data['token_quota_limit'])) {
            return back()->withErrors(['credit_quota_limit' => 'Enter a credit quota to apply.'])->withInput();
        }

        $limit = $data['action'] === 'set' ? (int) $data['token_quota_limit'] : null;
        $selectedUsers = User::query()->whereIn('id', $data['user_ids'])->get();
        foreach ($selectedUsers as $user) {
            $user->update(['token_quota_limit' => $limit]);
            AdminAuditLog::record('user.token_quota_updated', $user, [
                'token_quota_limit' => $limit,
                'mode' => $limit === null ? 'default' : 'custom',
                'bulk_count' => $selectedUsers->count(),
            ]);
        }

        $message = $limit === null
            ? 'Restored the default credit quota for '.$selectedUsers->count().' user(s).'
            : 'Set '.ChatModelService::formatCredits($limit).' monthly credits for '.$selectedUsers->count().' user(s).';

        return redirect()->route('admin.users.index')->with('success', $message);
    }

    private function normaliseCreditQuota(Request $request): void
    {
        if (! $request->exists('credit_quota_limit')) {
            return;
        }
        $data = $request->validate([
            'credit_quota_limit' => ['nullable', 'numeric', 'decimal:0,3', 'min:0.001', 'max:1000000'],
        ]);
        // Keep existing integer storage and legacy API compatibility; one unit
        // is 0.001 credit. Existing overrides need no data rewrite.
        $request->merge(['token_quota_limit' => isset($data['credit_quota_limit'])
            ? (int) round((float) $data['credit_quota_limit'] * ChatModelService::UNITS_PER_CREDIT)
            : null]);
    }

    public function destroy(User $user)
    {
        // Prevent self-delete
        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'Cannot delete your own account.']);
        }

        $dependencies = $this->ownedDataCounts($user);
        if ($dependencies !== []) {
            $summary = collect($dependencies)
                ->map(fn (int $count, string $label) => "{$count} {$label}")
                ->implode(', ');

            return redirect()->route('admin.users.index')->withErrors([
                'user' => "Cannot permanently delete this user because they still own {$summary}. Set the account to Inactive instead.",
            ]);
        }

        AdminAuditLog::record('user.deleted', $user, ['email' => $user->email]);
        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'User deleted successfully.');
    }

    /**
     * @return array<int, string>
     */
    private function departments(): array
    {
        $decoded = json_decode(AppSetting::value(self::ORGANIZATION_UNITS_SETTING, '[]') ?? '[]', true);
        if (is_array($decoded) && ($decoded['version'] ?? null) === 1 && is_array($decoded['units'] ?? null)) {
            return $this->normaliseUnits($decoded['units']);
        }

        $legacyUnits = is_array($decoded) ? $decoded : [];
        $usedByUsers = User::query()
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->distinct()
            ->pluck('department')
            ->all();

        return $this->normaliseUnits([...self::DEFAULT_UNITS, ...$legacyUnits, ...$usedByUsers]);
    }

    /** @param array<int, string> $units */
    private function saveUnitRegistry(array $units): AppSetting
    {
        return AppSetting::updateOrCreate(
            ['key' => self::ORGANIZATION_UNITS_SETTING],
            ['value' => json_encode([
                'version' => 1,
                'units' => $this->normaliseUnits($units),
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)],
        );
    }

    /** @param array<int, string> $units */
    private function findUnit(string $name, array $units): ?string
    {
        foreach ($units as $unit) {
            if ($this->unitKey($unit) === $this->unitKey($name)) {
                return $unit;
            }
        }

        return null;
    }

    private function unitKey(string $unit): string
    {
        $normalised = preg_replace('/\s+/u', ' ', trim($unit)) ?? trim($unit);

        return mb_strtolower($normalised);
    }

    /** @param array<int, mixed> $units
     * @return array<int, string>
     */
    private function normaliseUnits(array $units): array
    {
        $unique = [];
        foreach ($units as $unit) {
            if (! is_string($unit) || trim($unit) === '') {
                continue;
            }
            $unit = preg_replace('/\s+/u', ' ', trim($unit)) ?? trim($unit);
            $key = mb_strtolower($unit);
            $unique[$key] ??= $unit;
        }

        $normalised = array_values($unique);
        usort($normalised, 'strnatcasecmp');

        return $normalised;
    }

    /**
     * @return array<string, int>
     */
    private function ownedDataCounts(User $user): array
    {
        $counts = [
            'agents' => $user->agents()->count(),
            'conversations' => $user->conversations()->count(),
            'workflows' => $user->workflows()->count(),
            'artifacts' => $user->aiArtifacts()->count(),
            'email drafts' => $user->emailDrafts()->count(),
            'usage records' => $user->usageLogs()->count(),
            'showcase posts' => $user->showcasePosts()->count(),
            'library prompts' => $user->promptLibraryPrompts()->count(),
            'support requests' => $user->supportTickets()->count(),
        ];

        return array_filter($counts);
    }
}

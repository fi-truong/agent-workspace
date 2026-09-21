<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
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

        $sort = $request->get('sort', 'newest');
        match ($sort) {
            'oldest' => $query->oldest(),
            'alpha' => $query->orderBy('name'),
            default => $query->latest(),
        };

        $users = $query->paginate(15)->withQueryString();
        $roles = ['admin', 'staff', 'teacher', 'student'];
        $departments = User::distinct()->pluck('department')->filter()->all();

        $activeStatuses = ['1' => 'Active', '0' => 'Inactive'];

        return view('admin.users.index', compact('users', 'roles', 'departments', 'activeStatuses'));
    }

    public function create()
    {
        return view('admin.users.create', [
            'departments' => $this->departments(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:admin,staff,teacher,student',
            'department' => 'nullable|string|max:100',
            'employee_id' => 'nullable|string|max:50',
            'is_active' => 'boolean',
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
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'required|in:admin,staff,teacher,student',
            'department' => 'nullable|string|max:100',
            'employee_id' => 'nullable|string|max:50',
            'is_active' => 'boolean',
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
        AdminAuditLog::record('user.updated', $user, ['role' => $user->role, 'is_active' => $user->is_active]);

        return redirect()->route('admin.users.index')->with('success', 'User updated successfully.');
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
        return User::query()
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->distinct()
            ->orderBy('department')
            ->pluck('department')
            ->all();
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

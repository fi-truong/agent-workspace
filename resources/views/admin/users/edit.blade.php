@extends('layouts.admin')

@section('page-title', 'Edit User')
@section('page-desc', 'Chỉnh sửa: ' . $user->name)

@section('content')
<form action="{{ route('admin.users.update', $user) }}" method="POST" class="table-section" style="max-width:600px;">
    @csrf
    @method('PUT')
    <div style="padding:24px;display:flex;flex-direction:column;gap:20px;">
        <div class="form-group">
            <label>Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-input" placeholder="Full name" required value="{{ old('name', $user->name) }}">
        </div>
        <div class="form-group">
            <label>Email <span class="text-danger">*</span></label>
            <input type="email" name="email" class="form-input" placeholder="email@lsts.edu.vn" required value="{{ old('email', $user->email) }}">
        </div>
        <div class="form-group">
            <label>New Password (leave blank to keep current)</label>
            <input type="password" name="password" class="form-input" placeholder="Min 8 characters" minlength="8">
        </div>
        <div class="form-group">
            <label>Confirm New Password</label>
            <input type="password" name="password_confirmation" class="form-input" placeholder="Confirm password" minlength="8">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
            <div class="form-group">
                <label>Role <span class="text-danger">*</span></label>
                <select name="role" class="form-select" required>
                    <option value="admin" {{ old('role', $user->role) == 'admin' ? 'selected' : '' }}>Admin</option>
                    <option value="staff" {{ old('role', $user->role) == 'staff' ? 'selected' : '' }}>Staff</option>
                    <option value="teacher" {{ old('role', $user->role) == 'teacher' ? 'selected' : '' }}>Teacher</option>
                    <option value="student" {{ old('role', $user->role) == 'student' ? 'selected' : '' }}>Student</option>
                </select>
                @if($user->id === auth()->id())
                <p class="form-hint text-warning">Cannot change your own admin role.</p>
                @endif
            </div>
            <div class="form-group">
                <label>Department</label>
                <select name="department" class="form-select">
                    <option value="">— Select Department —</option>
                    @foreach($departments as $d)
                    <option value="{{ $d }}" {{ (old('department', $user->department) ?? '') == $d ? 'selected' : '' }}>{{ $d }}</option>
                    @endforeach
                    <option value="CIEC" {{ (old('department', $user->department) ?? '') == 'CIEC' ? 'selected' : '' }}>CIEC</option>
                    <option value="IT" {{ (old('department', $user->department) ?? '') == 'IT' ? 'selected' : '' }}>IT</option>
                    <option value="Academic" {{ (old('department', $user->department) ?? '') == 'Academic' ? 'selected' : '' }}>Academic</option>
                    <option value="Admin" {{ (old('department', $user->department) ?? '') == 'Admin' ? 'selected' : '' }}>Admin</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label>Employee ID</label>
            <input type="text" name="employee_id" class="form-input" placeholder="Employee/Student ID" value="{{ old('employee_id', $user->employee_id) }}">
        </div>
        <div style="display:flex;gap:12px;justify-content:flex-end;padding-top:16px;border-top:1px solid var(--line);">
            <a href="{{ route('admin.users.index') }}" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary">Update User</button>
        </div>
    </div>
</form>
@endsection
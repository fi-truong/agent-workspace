@extends('layouts.admin')

@section('page-title', 'Create User')
@section('page-desc', 'Add a new user account')

@section('content')
<form action="{{ route('admin.users.store') }}" method="POST" class="table-section" style="max-width:600px;">
    @csrf
    <div style="padding:24px;display:flex;flex-direction:column;gap:20px;">
        @include('admin.partials.validation-summary')
        <div class="form-group">
            <label>Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-input" placeholder="Full name" required value="{{ old('name') }}">
            @include('admin.partials.field-error', ['field' => 'name'])
        </div>
        <div class="form-group">
            <label>Email <span class="text-danger">*</span></label>
            <input type="email" name="email" class="form-input" placeholder="email@lsts.edu.vn" required value="{{ old('email') }}">
            @include('admin.partials.field-error', ['field' => 'email'])
        </div>
        <div class="form-group">
            <label>Password <span class="text-danger">*</span></label>
            <input type="password" name="password" class="form-input" placeholder="Min 8 characters" required minlength="8">
            @include('admin.partials.field-error', ['field' => 'password'])
        </div>
        <div class="form-group">
            <label>Confirm Password <span class="text-danger">*</span></label>
            <input type="password" name="password_confirmation" class="form-input" placeholder="Confirm password" required minlength="8">
            @include('admin.partials.field-error', ['field' => 'password_confirmation'])
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
            <div class="form-group">
                <label>Role <span class="text-danger">*</span></label>
                <select name="role" class="form-select" required>
                    <option value="admin" {{ old('role') == 'admin' ? 'selected' : '' }}>Admin</option>
                    <option value="staff" {{ old('role') == 'staff' ? 'selected' : '' }}>Staff</option>
                    <option value="teacher" {{ old('role') == 'teacher' ? 'selected' : '' }}>Teacher</option>
                    <option value="student" {{ old('role') == 'student' ? 'selected' : '' }}>Student</option>
                </select>
                @include('admin.partials.field-error', ['field' => 'role'])
            </div>
            <div class="form-group">
                <label>Department</label>
                <select name="department" class="form-select">
                    <option value="">— Select Department —</option>
                    @foreach($departments as $d)
                    <option value="{{ $d }}" {{ old('department') == $d ? 'selected' : '' }}>{{ $d }}</option>
                    @endforeach
                    <option value="CIEC" {{ old('department') == 'CIEC' ? 'selected' : '' }}>CIEC</option>
                    <option value="IT" {{ old('department') == 'IT' ? 'selected' : '' }}>IT</option>
                    <option value="Academic" {{ old('department') == 'Academic' ? 'selected' : '' }}>Academic</option>
                    <option value="Admin" {{ old('department') == 'Admin' ? 'selected' : '' }}>Admin</option>
                </select>
                @include('admin.partials.field-error', ['field' => 'department'])
            </div>
        </div>
        <div class="form-group">
            <label>Employee ID</label>
            <input type="text" name="employee_id" class="form-input" placeholder="Employee/Student ID" value="{{ old('employee_id') }}">
            @include('admin.partials.field-error', ['field' => 'employee_id'])
        </div>
        <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                Active account
            </label>
            <p class="form-hint">Inactive users cannot sign in or access the admin panel.</p>
        </div>
        <div style="display:flex;gap:12px;justify-content:flex-end;padding-top:16px;border-top:1px solid var(--line);">
            <a href="{{ route('admin.users.index') }}" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary">Create User</button>
        </div>
    </div>
</form>
@endsection

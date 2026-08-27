@extends('layouts.admin')

@section('page-title', 'Create User')
@section('page-desc', 'Thêm người dùng mới')

@section('content')
<form action="{{ route('admin.users.store') }}" method="POST" class="table-section" style="max-width:600px;">
    @csrf
    <div style="padding:24px;display:flex;flex-direction:column;gap:20px;">
        <div class="form-group">
            <label>Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-input" placeholder="Full name" required value="{{ old('name') }}">
        </div>
        <div class="form-group">
            <label>Email <span class="text-danger">*</span></label>
            <input type="email" name="email" class="form-input" placeholder="email@lsts.edu.vn" required value="{{ old('email') }}">
        </div>
        <div class="form-group">
            <label>Password <span class="text-danger">*</span></label>
            <input type="password" name="password" class="form-input" placeholder="Min 8 characters" required minlength="8">
        </div>
        <div class="form-group">
            <label>Confirm Password <span class="text-danger">*</span></label>
            <input type="password" name="password_confirmation" class="form-input" placeholder="Confirm password" required minlength="8">
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
            </div>
        </div>
        <div class="form-group">
            <label>Employee ID</label>
            <input type="text" name="employee_id" class="form-input" placeholder="Employee/Student ID" value="{{ old('employee_id') }}">
        </div>
        <div style="display:flex;gap:12px;justify-content:flex-end;padding-top:16px;border-top:1px solid var(--line);">
            <a href="{{ route('admin.users.index') }}" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary">Create User</button>
        </div>
    </div>
</form>
@endsection
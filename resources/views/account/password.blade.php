@extends('layouts.ai-plus')

@section('title', 'Change Password — AI+')

@section('breadcrumb', 'Change Password')

@section('content')
<div class="account-page">
  <div class="account-card">
    <h1 class="account-title">Change Password</h1>
    <p class="account-sub">Enter your current password, then choose a new one.</p>

    <form id="password-form" method="POST">
      @csrf
      <div class="form-group">
        <label for="current_password">Current Password</label>
        <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
      </div>
      <div class="form-group">
        <label for="new_password">New Password</label>
        <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">
        <small class="form-hint">At least 8 characters.</small>
      </div>
      <div class="form-group">
        <label for="new_password_confirmation">Confirm New Password</label>
        <input type="password" id="new_password_confirmation" name="new_password_confirmation" required minlength="8" autocomplete="new-password">
      </div>

      <div class="form-error" id="form-error" style="display:none;"></div>

      <div class="account-actions">
        <button type="submit" class="btn btn-primary" id="password-submit">Update Password</button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('styles')
<style>
.account-page { max-width: 480px; margin: 0 auto; padding: 40px 24px; }
.account-card { background: var(--card-bg); border: 1px solid var(--line); border-radius: 16px; padding: 28px 32px; }
.account-title { font-family: 'Fraunces', serif; font-size: 26px; color: var(--navy); margin: 0 0 6px; }
.account-sub { color: var(--ink-soft); margin: 0 0 24px; font-size: 14px; }
#password-form { display: flex; flex-direction: column; gap: 16px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group label { font-weight: 500; color: var(--ink); font-size: 14px; }
.form-group input { padding: 12px 14px; border: 1px solid var(--line); border-radius: 10px; font-family: inherit; font-size: 14px; color: var(--ink); background: var(--card-bg); }
.form-group input:focus { outline: none; border-color: var(--navy); box-shadow: 0 0 0 3px rgba(31,56,100,0.1); }
.form-hint { font-size: 12px; color: var(--ink-soft); }
.form-error { background: #FDF3E0; color: #9A6B1F; border: 1px solid #E5C88A; padding: 10px 14px; border-radius: 8px; font-size: 13px; }
.account-actions { display: flex; justify-content: flex-end; }
</style>
@endpush

@push('scripts')
<script>
document.getElementById('password-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const errBox = document.getElementById('form-error');
  errBox.style.display = 'none';

  const form = e.target;
  const formData = new FormData(form);
  const submit = document.getElementById('password-submit');
  submit.disabled = true;
  submit.textContent = 'Updating...';

  try {
    const res = await fetch('/account/password', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
      },
      body: formData,
    });
    let data = {};
    try { data = await res.json(); } catch (_) {}

    if (res.ok) {
      errBox.textContent = '✅ ' + (data.message || 'Password updated successfully.');
      errBox.style.background = '#E7F4EC';
      errBox.style.color = '#1E7B4C';
      errBox.style.borderColor = '#A8D8B9';
      errBox.style.display = 'block';
      form.reset();
    } else {
      const messages = data.errors ? Object.values(data.errors).flat().join(' • ') : (data.message || 'Failed to update password.');
      errBox.textContent = '⚠️ ' + messages;
      errBox.style.background = '#FDF3E0';
      errBox.style.color = '#9A6B1F';
      errBox.style.borderColor = '#E5C88A';
      errBox.style.display = 'block';
    }
  } catch (_) {
    errBox.textContent = '⚠️ Network error. Please check your connection and try again.';
    errBox.style.background = '#FDF3E0';
    errBox.style.color = '#9A6B1F';
    errBox.style.borderColor = '#E5C88A';
    errBox.style.display = 'block';
  } finally {
    submit.disabled = false;
    submit.textContent = 'Update Password';
  }
});
</script>
@endpush

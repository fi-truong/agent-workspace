<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - AI+ Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Fonts are self-hosted via @font-face in ai-plus.css -->
    <link rel="stylesheet" href="{{ asset('css/ai-plus.css') }}">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Inter',sans-serif;background:#F0EDE6;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
        .login-card{background:#fff;border:1px solid #D9E8E3;border-radius:16px;padding:40px;width:100%;max-width:400px;box-shadow:0 4px 24px rgba(22,38,63,0.08)}
        .logo{font-family:'Fraunces',serif;font-size:28px;font-weight:600;color:#1F5147;text-align:center;margin-bottom:8px}
        .subtitle{text-align:center;color:#4F6A63;font-size:14px;margin-bottom:32px}
        .form-group{margin-bottom:20px}
        label{display:block;font-size:13px;font-weight:500;color:#1B2E2A;margin-bottom:6px}
        input{width:100%;padding:12px 14px;border:1px solid #D9E8E3;border-radius:8px;font-size:14px;font-family:inherit;background:#F4FBF9;transition:border-color .15s}
        input:focus{outline:none;border-color:#1F5147}
        .btn{width:100%;padding:12px;background:#1F5147;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;transition:background .15s}
        .btn:hover{background:#163B34}
        .links{display:flex;justify-content:space-between;margin-top:20px;font-size:13px}
        .links a{color:#1F5147;text-decoration:none}
        .links a:hover{text-decoration:underline}
        .error{background:#FDE8E8;color:#C0392B;padding:12px;border-radius:8px;margin-bottom:20px;font-size:13px}
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo">AI+ Admin</div>
        <p class="subtitle">Đăng nhập để quản trị hệ thống</p>

        @if($errors->any())
        <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login.local') }}">
            @csrf
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
            </div>
            <div class="form-group">
                <label>Mật khẩu</label>
                <input type="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn">Đăng nhập</button>
        </form>

        <div class="links">
            <a href="{{ route('ai-plus.index') }}">← Về AI+</a>
            <a href="{{ route('auth.microsoft.redirect') }}" class="sso-link">Đăng nhập Microsoft SSO</a>
        </div>
    </div>
</body>
</html>
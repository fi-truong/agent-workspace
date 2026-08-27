<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - AI+ Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Inter',sans-serif;background:#F0EDE6;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
        .login-card{background:#fff;border:1px solid #E1DACB;border-radius:16px;padding:40px;width:100%;max-width:400px;box-shadow:0 4px 24px rgba(22,38,63,0.08)}
        .logo{font-family:'Fraunces',serif;font-size:28px;font-weight:600;color:#1F3864;text-align:center;margin-bottom:8px}
        .subtitle{text-align:center;color:#5B6B7C;font-size:14px;margin-bottom:32px}
        .form-group{margin-bottom:20px}
        label{display:block;font-size:13px;font-weight:500;color:#22303F;margin-bottom:6px}
        input{width:100%;padding:12px 14px;border:1px solid #E1DACB;border-radius:8px;font-size:14px;font-family:inherit;background:#F6F3EC;transition:border-color .15s}
        input:focus{outline:none;border-color:#1F3864}
        .btn{width:100%;padding:12px;background:#1F3864;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;transition:background .15s}
        .btn:hover{background:#16263F}
        .links{display:flex;justify-content:space-between;margin-top:20px;font-size:13px}
        .links a{color:#1F3864;text-decoration:none}
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
            <a href="{{ route('login') }}">SSO Microsoft</a>
        </div>
    </div>
</body>
</html>
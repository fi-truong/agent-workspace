<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Access Pending — AI+</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="{{ asset('css/ai-plus.css') }}">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Inter',sans-serif;background:var(--body-bg);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;color:var(--text-main)}
        .card{background:var(--surface);border:1px solid var(--surface-border);border-radius:16px;padding:48px 40px;width:100%;max-width:460px;text-align:center;box-shadow:0 4px 24px rgba(22,38,63,0.08)}
        .logo{font-family:'Fraunces',serif;font-size:28px;font-weight:600;color:var(--navy);margin-bottom:6px}
        .icon{font-size:52px;margin:20px 0 16px}
        h1{font-family:'Fraunces',serif;font-size:22px;font-weight:600;color:var(--text-main);margin-bottom:10px}
        p{color:var(--text-soft);font-size:14px;line-height:1.6;margin-bottom:8px}
        .btn{display:inline-block;margin-top:22px;padding:12px 26px;background:var(--navy);color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;transition:background .15s}
        .btn:hover{background:var(--navy-deep)}
        .hint{margin-top:18px;font-family:'IBM Plex Mono',monospace;font-size:11px;color:var(--text-soft);letter-spacing:.04em}
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">AI+</div>
        <div class="icon">🔒</div>
        <h1>Truy cập đang chờ phê duyệt</h1>
        <p>Tài khoản của bạn chưa được cấp quyền sử dụng AI+, hoặc đã bị tạm khóa.</p>
        <p>Chỉ người dùng được đăng ký và phê duyệt mới có thể truy cập hệ thống này.</p>
        <p>Vui lòng liên hệ bộ phận IT hoặc CIEC để được hỗ trợ.</p>
        <a href="{{ route('ai-plus.index') }}" class="btn">Về trang AI+</a>
        <div class="hint">ACCESS PENDING · Contact IT / CIEC</div>
    </div>
</body>
</html>

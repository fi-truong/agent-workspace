<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Access Pending — LSTS AI+</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.favicon')
    @include('partials.lsts-typography')
    <link rel="stylesheet" href="{{ asset('css/ai-plus.css') }}?v={{ filemtime(public_path('css/ai-plus.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/lsts-ai-plus.css') }}?v={{ filemtime(public_path('css/lsts-ai-plus.css')) }}">
</head>
<body class="lsts-ai-ui lsts-ai-access">
    <main class="lsts-access-card">
        <div class="lsts-wordmark">LSTS AI+</div>
        <h1>Truy cập đang chờ phê duyệt</h1>
        <p>Tài khoản của bạn chưa được cấp quyền sử dụng LSTS AI+, hoặc đã bị tạm khóa.</p>
        <p>Chỉ người dùng được đăng ký và phê duyệt mới có thể truy cập hệ thống này.</p>
        <p>Vui lòng liên hệ bộ phận IT hoặc CIEC để được hỗ trợ.</p>
        <a href="{{ route('ai-plus.index') }}" class="lsts-primary-link">Về trang LSTS AI+</a>
    </main>
</body>
</html>

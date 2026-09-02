<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>@yield('title', 'AI+ - LSTS Staff Portal')</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script>
  // Áp dụng theme đã lưu NGAY LẬP TỨC, tránh nháy màu sai lúc đầu load trang
  (function(){
    var saved = localStorage.getItem('aiplus-theme');
    if (saved === 'teal') {
      document.documentElement.setAttribute('data-theme', 'teal');
    }
  })();
</script>
<!-- Fonts are self-hosted via @font-face in ai-plus.css -->
<link rel="stylesheet" href="{{ asset('css/ai-plus.css') }}">
@stack('styles')
<meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body>

@hasSection('breadcrumb')
<div class="ai-plus-topbar">
  <div class="wrap">
    <a href="{{ route('ai-plus.index') }}" class="back-link">← Back to AI+</a>
    <span class="crumb-current">@yield('breadcrumb')</span>
    @auth
    <span class="user-badge" style="margin-left:auto;">
      Viewing as: {{ $currentUser->name ?? '' }}
    </span>
    @endauth
  </div>
</div>
@endif

@yield('content')

@include('partials.theme-switcher')
@stack('scripts')
</body>
</html>

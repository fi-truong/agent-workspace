<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>@yield('title', 'LSTS AI+')</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
@include('partials.favicon')
<script>
  // Áp dụng theme đã lưu NGAY LẬP TỨC, tránh nháy màu sai lúc đầu load trang
  (function(){
    var saved = localStorage.getItem('aiplus-theme');
    if (['teal', 'orange', 'burgundy'].includes(saved)) {
      document.documentElement.setAttribute('data-theme', saved);
    }
  })();
</script>
<!-- Fonts are self-hosted via @font-face in ai-plus.css -->
<link rel="stylesheet" href="{{ asset('css/ai-plus.css') }}?v={{ filemtime(public_path('css/ai-plus.css')) }}">
@stack('styles')
<link rel="stylesheet" href="{{ asset('css/lsts-ai-plus.css') }}?v={{ filemtime(public_path('css/lsts-ai-plus.css')) }}">
<meta name="csrf-token" content="{{ csrf_token() }}">
@include('partials.session-timeout-monitor')
</head>
<body class="lsts-ai-ui @yield('screen-class')">
<script>
  window.addEventListener('pageshow', function (event) {
    if (event.persisted) window.location.reload();
  });
</script>

@hasSection('breadcrumb')
<div class="ai-plus-topbar">
  <div class="wrap">
    <a href="{{ route('ai-plus.index') }}" class="back-link lsts-wordmark" aria-label="Back to LSTS AI+">LSTS AI+</a>
    <span class="crumb-current">@yield('breadcrumb')</span>
    @auth
    <div class="user-badge-wrap" style="margin-left:auto;">
      <a href="{{ route('account.password') }}" class="user-badge user-badge-link" title="View account & change password">
        Viewing as: {{ $currentUser->name ?? '' }}
      </a>
      @if(auth()->user()?->role === 'admin')
      <a href="{{ route('admin.dashboard') }}" class="admin-panel-link">Admin Panel</a>
      @endif
      <form method="POST" action="{{ route('logout') }}" class="header-logout-form">
        @csrf
        <button type="submit" class="header-logout-btn" title="Logout">Logout</button>
      </form>
    </div>
    @endauth
  </div>
</div>
@endif

@yield('content')

@include('partials.theme-switcher')
<script src="{{ asset('js/web-dialogs.js') }}?v={{ filemtime(public_path('js/web-dialogs.js')) }}"></script>
@stack('scripts')
</body>
</html>

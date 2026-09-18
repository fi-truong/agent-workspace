<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>@yield('title', 'AI+ - LSTS Staff Portal')</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
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
@vite('resources/css/app.css')
<link rel="stylesheet" href="{{ asset('css/ai-plus.css') }}">
@stack('styles')
</head>
<body>
<script>
  window.addEventListener('pageshow', function (event) {
    if (event.persisted) window.location.reload();
  });
</script>

  <div class="crumbbar">
    <div class="wrap">
      <div class="crumb-trail">
        <a href="{{ route('ai-plus.index') }}" class="crumb-link">LSTS Staff Portal</a> <span class="crumb-sep">/</span> <b>AI+</b>
      </div>
      <div class="role-pill role-pill-account">
        @auth
        <a href="{{ route('account.password') }}" title="View account & change password" class="role-pill-link">
          <span class="dot"></span>Viewing as: {{ $currentUser->name ?? 'Teacher / Staff' }}
        </a>
        @if(auth()->user()?->role === 'admin')
        <a href="{{ route('admin.dashboard') }}" class="admin-panel-link">Admin Panel</a>
        @endif
        <form method="POST" action="{{ route('logout') }}" class="header-logout-form">
          @csrf
          <button type="submit" class="header-logout-btn">Logout</button>
        </form>
        @else
        <span class="dot"></span>Viewing as: Teacher / Staff
        @endauth
      </div>
    </div>
  </div>

  @yield('content')

  <footer>
    <div class="wrap" style="display:flex; justify-content:space-between; width:100%; flex-wrap:wrap; gap:8px;">
      <div><b>CIEC</b> — Center of Innovation, Entrepreneurship and Creativity</div>
      <div>Lawrence S. Ting School · lsts.edu.vn</div>
    </div>
  </footer>

  @include('partials.theme-switcher')
  @stack('scripts')
</body>
</html>

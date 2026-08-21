<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>@yield('title', 'AI+ - LSTS Staff Portal')</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
@vite('resources/css/app.css')
<link rel="stylesheet" href="{{ asset('css/ai-plus.css') }}">
@stack('styles')
</head>
<body>

  <div class="crumbbar">
    <div class="wrap">
      <div class="crumb-trail">
        <a href="{{ route('ai-plus.index') }}" class="crumb-link">LSTS Staff Portal</a> <span class="crumb-sep">/</span> <b>AI+</b>
      </div>
      <div class="role-pill"><span class="dot"></span>Viewing as: {{ $currentUser->name ?? 'Teacher / Staff' }}</div>
    </div>
  </div>

  @yield('content')

  <footer>
    <div class="wrap" style="display:flex; justify-content:space-between; width:100%; flex-wrap:wrap; gap:8px;">
      <div><b>CIEC</b> — Center of Innovation, Entrepreneurship and Creativity</div>
      <div>Lawrence S. Ting School · lsts.edu.vn</div>
    </div>
  </footer>

</body>
</html>

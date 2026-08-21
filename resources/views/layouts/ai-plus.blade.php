<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>@yield('title', 'AI+ - LSTS Staff Portal')</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
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
@stack('scripts')
</body>
</html>
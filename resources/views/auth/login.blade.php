<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign in — LSTS AI+</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.favicon')
    @include('partials.lsts-typography')
    <link rel="stylesheet" href="{{ asset('css/ai-plus.css') }}?v={{ filemtime(public_path('css/ai-plus.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/lsts-ai-plus.css') }}?v={{ filemtime(public_path('css/lsts-ai-plus.css')) }}">
</head>
<body class="lsts-ai-ui lsts-ai-access">
    <main class="login-card lsts-access-card">
        <div class="lsts-wordmark">LSTS AI+</div>
        <h1>Sign in</h1>
        <p class="subtitle">Access your workspace</p>

        @if(session('status'))
        <div class="error">{{ session('status') }}</div>
        @endif

        @if(request()->boolean('session_expired'))
        <div class="error" role="status">Your LSTS AI+ session expired after a period of inactivity. Please sign in again.</div>
        @endif

        @if($errors->any())
        <div class="error">{{ $errors->first() }}</div>
        @endif

        <a href="{{ route('auth.microsoft.redirect') }}" class="sso-link lsts-primary-link">Sign in with Microsoft</a>
        <p class="lsts-auth-divider">or sign in with email</p>

        <form method="POST" action="{{ route('login.local') }}">
            @csrf
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-secondary">Sign in</button>
        </form>

        <div class="links">
            <a href="{{ route('ai-plus.index') }}">← Back to LSTS AI+</a>
        </div>
    </main>
</body>
</html>

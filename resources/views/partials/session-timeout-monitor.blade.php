<meta name="ai-plus-session-timeout-ms" content="{{ max(1, (int) config('session.lifetime', 120)) * 60 * 1000 }}">
<meta name="ai-plus-login-url" content="{{ route('login.local.form') }}">
<script src="{{ asset('js/session-timeout.js') }}?v={{ filemtime(public_path('js/session-timeout.js')) }}" defer></script>

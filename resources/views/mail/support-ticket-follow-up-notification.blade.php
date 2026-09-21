<!doctype html>
<html lang="en">
<body style="margin:0;background:#f5f7f6;padding:24px;font-family:Arial,sans-serif;color:#1f2937;">
  <main style="max-width:640px;margin:auto;background:#fff;border:1px solid #dbe4df;border-radius:12px;padding:28px;">
    <h1 style="font-size:20px;margin:0 0 16px;color:#155e4d;">New support ticket follow-up</h1>
    <p><strong>{{ $ticket->name }}</strong> added a message to ticket #{{ $ticket->id }} — {{ $ticket->subject }}.</p>
    <div style="white-space:pre-wrap;border-left:4px solid #21a179;background:#f1faf6;padding:14px 16px;margin:20px 0;">{{ $reply->body }}</div>
    <p><a href="{{ $adminUrl }}" style="color:#155e4d;font-weight:bold;">Open ticket in Admin</a></p>
  </main>
</body>
</html>

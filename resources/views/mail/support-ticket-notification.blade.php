<h2>New AI+ Support Ticket</h2>

<p><strong>Ticket ID:</strong> #{{ $ticket->id }}</p>
<p><strong>Type:</strong> {{ $ticket->type }}</p>
<p><strong>Priority:</strong> {{ ucfirst($ticket->priority ?? 'medium') }}</p>
<p><strong>From:</strong> {{ $ticket->name }} ({{ $ticket->email }})</p>
<p><strong>Subject:</strong> {{ $ticket->subject }}</p>

<hr>

<p><strong>Details</strong></p>
<p>{!! nl2br(e($ticket->details)) !!}</p>

<hr>

<p><a href="{{ $adminUrl }}">Open this ticket in AI+ Admin</a></p>

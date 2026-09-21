<?php

use App\Mail\SupportTicketFollowUpNotification;
use App\Mail\SupportTicketNotification;
use App\Mail\SupportTicketReceipt;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('submitting support creates a ticket and sends formatted notification emails', function () {
    Mail::fake();
    config(['support.notification_recipient' => 'support@example.test']);
    $user = User::factory()->create(['name' => 'Support User', 'email' => 'support.user@example.test']);

    $this->actingAs($user)
        ->postJson(route('ai-plus.support.store'), [
            'name' => 'Support User',
            'email' => 'support.user@example.test',
            'type' => 'Technical Issue / Bug Report',
            'subject' => 'Unable to upload a PDF',
            'details' => 'The upload fails after selecting a PDF document.',
            'priority' => 'high',
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    $ticket = SupportTicket::firstOrFail();
    expect($ticket->user_id)->toBe($user->id);
    expect($ticket->name)->toBe('Support User')
        ->and($ticket->email)->toBe('support.user@example.test');

    Mail::assertSent(SupportTicketNotification::class, function (SupportTicketNotification $mail) use ($ticket) {
        return $mail->ticket->is($ticket)
            && $mail->hasTo('support@example.test')
            && str_contains($mail->envelope()->subject, "#{$ticket->id}");
    });
    Mail::assertSent(SupportTicketReceipt::class, function (SupportTicketReceipt $mail) use ($ticket) {
        return $mail->ticket->is($ticket)
            && $mail->hasTo('support.user@example.test');
    });

    expect((new SupportTicketNotification($ticket))->render())
        ->toContain('New AI+ Support Ticket')
        ->toContain('Unable to upload a PDF')
        ->toContain(route('admin.tickets.show', $ticket));
    expect((new SupportTicketReceipt($ticket))->render())
        ->toContain('Thank you for contacting AI+ Support')
        ->toContain('Support User');
});

test('users can view only their own support request history and replies', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $ticket = SupportTicket::create([
        'user_id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'type' => 'Question / How-To',
        'priority' => 'medium',
        'subject' => 'Need help with Agent Workspace',
        'details' => 'I need help with this workspace.',
        'status' => 'in_progress',
    ]);
    $otherTicket = SupportTicket::create([
        'user_id' => $otherUser->id,
        'name' => $otherUser->name,
        'email' => $otherUser->email,
        'type' => 'Other',
        'priority' => 'low',
        'subject' => 'Private request',
        'details' => 'This request belongs to another user.',
    ]);
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $reply = SupportTicketReply::create([
        'support_ticket_id' => $ticket->id,
        'author_id' => $admin->id,
        'body' => 'Please refresh the page and try again.',
        'sent_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('ai-plus.support.requests.index'))
        ->assertOk()
        ->assertSee('Need help with Agent Workspace')
        ->assertDontSee('Private request')
        ->assertSee('1 new');

    $this->get(route('ai-plus.support.requests.show', $ticket))
        ->assertOk()
        ->assertSee('Please refresh the page and try again.');
    expect($reply->fresh()->read_at)->not->toBeNull();

    $this->get(route('ai-plus.support.requests.show', $otherTicket))->assertForbidden();
});

test('support reply history survives deletion of the replying staff account', function () {
    $requester = User::factory()->create();
    $staff = User::factory()->create(['role' => 'staff']);
    $ticket = SupportTicket::create([
        'user_id' => $requester->id,
        'name' => $requester->name,
        'email' => $requester->email,
        'type' => 'Other',
        'priority' => 'low',
        'subject' => 'Historical reply',
        'details' => 'Keep this reply available after staff changes.',
    ]);
    $reply = SupportTicketReply::create([
        'support_ticket_id' => $ticket->id,
        'author_id' => $staff->id,
        'body' => 'This response should remain visible.',
    ]);

    $staff->delete();

    expect($reply->fresh()->author_id)->toBeNull();
    $this->actingAs($requester)
        ->get(route('ai-plus.support.requests.show', $ticket))
        ->assertOk()
        ->assertSee('This response should remain visible.');
});

test('users can follow up on their own resolved ticket and reopen it', function () {
    Mail::fake();
    config(['support.notification_recipient' => 'support@example.test']);
    $user = User::factory()->create();
    $ticket = SupportTicket::create([
        'user_id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'type' => 'Technical Issue / Bug Report',
        'priority' => 'medium',
        'subject' => 'Still unable to upload',
        'details' => 'The upload did not work.',
        'status' => 'resolved',
        'resolved_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('ai-plus.support.requests.replies.store', $ticket), [
            'body' => 'I tried again today and the issue is still happening.',
        ])
        ->assertRedirect();

    $reply = SupportTicketReply::firstOrFail();
    expect($reply->author_id)->toBe($user->id)
        ->and($reply->body)->toContain('still happening');
    $this->assertDatabaseHas('support_tickets', ['id' => $ticket->id, 'status' => 'pending', 'resolved_at' => null]);
    Mail::assertSent(SupportTicketFollowUpNotification::class, fn (SupportTicketFollowUpNotification $mail) => $mail->reply->is($reply)
        && $mail->hasTo('support@example.test'));
});

test('users cannot add a follow-up to another user ticket', function () {
    $ticket = SupportTicket::create([
        'user_id' => User::factory()->create()->id,
        'name' => 'Another User',
        'email' => 'another@example.test',
        'type' => 'Other',
        'priority' => 'low',
        'subject' => 'Private ticket',
        'details' => 'This ticket must not be changed by others.',
    ]);

    $this->actingAs(User::factory()->create())
        ->post(route('ai-plus.support.requests.replies.store', $ticket), ['body' => 'Not allowed'])
        ->assertForbidden();
});

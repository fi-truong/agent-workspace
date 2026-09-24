<?php

use App\Mail\SupportTicketReplyMail;
use App\Models\AppSetting;
use App\Models\AiSafetyEvent;
use App\Models\ShowcasePost;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\UsageLog;
use App\Models\User;
use App\Services\TokenQuotaService;
use Illuminate\Support\Facades\Mail;

test('guests are redirected away from the admin panel', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login.local.form'));
});

test('non admin users cannot access admin routes', function () {
    $user = User::factory()->create(['role' => 'staff', 'is_active' => true]);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('inactive administrators cannot access admin routes', function () {
    $user = User::factory()->create(['role' => 'admin', 'is_active' => false]);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('login.local.form'));

    $this->assertGuest();
});

test('administrators can access the dashboard', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Recent Admin Activity');
});

test('administrators can review current-month token usage by user', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $activeUser = User::factory()->create(['name' => 'High Usage User']);
    UsageLog::create([
        'user_id' => $activeUser->id,
        'activity_title' => 'Chat',
        'source' => 'agent_workspace',
        'prompt_tokens' => 600,
        'completion_tokens' => 400,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.usage.index'))
        ->assertOk()
        ->assertSee('High Usage User')
        ->assertSee('1,000');
});

test('administrators can enable image generation in Agent Workspace', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

    $this->actingAs($admin)
        ->put(route('admin.ai-image.update'), [
            'enabled' => '1',
            'flare_enabled' => '1',
            'sunburst_enabled' => '1',
            'default_model' => 'gpt-image-2.5-flare',
        ])
        ->assertRedirect(route('admin.ai-image.index'));

    expect(AppSetting::boolean('ai_plus_image_generation_enabled'))->toBeTrue()
        ->and(AppSetting::boolean('ai_plus_image_flare_enabled'))->toBeTrue()
        ->and(AppSetting::boolean('ai_plus_image_sunburst_enabled'))->toBeTrue()
        ->and(AppSetting::query()->where('key', 'ai_plus_image_default_model')->value('value'))->toBe('gpt-image-2.5-flare');
});

test('administrators can review and toggle work-use monitoring without prompt contents', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $staff = User::factory()->create(['name' => 'Monitoring User']);
    AiSafetyEvent::create([
        'user_id' => $staff->id,
        'feature' => 'chat',
        'classification' => 'personal_or_unrelated',
        'action' => 'allowed',
        'moderation_flagged' => false,
        'metadata' => ['input_length' => 42, 'mode' => 'monitor_only'],
    ]);

    $this->actingAs($admin)
        ->get(route('admin.work-use.index'))
        ->assertOk()
        ->assertSee('Work-Use Monitoring')
        ->assertSee('Monitoring User')
        ->assertSee('Personal Or Unrelated');

    $this->put(route('admin.work-use.update'), ['enabled' => '0'])
        ->assertRedirect(route('admin.work-use.index'));

    expect(AppSetting::boolean('ai_plus_work_use_monitoring_enabled', true))->toBeFalse();
});

test('administrators can see Image Studio usage split by model and user', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $creator = User::factory()->create(['name' => 'Image Creator']);
    UsageLog::create([
        'user_id' => $creator->id,
        'activity_title' => 'Image: Library',
        'source' => 'agent_workspace',
        'model' => 'gpt-image-2.5-flare',
        'prompt_tokens' => 12,
        'completion_tokens' => 34,
    ]);
    UsageLog::create([
        'user_id' => $creator->id,
        'activity_title' => 'Image edit: Poster',
        'source' => 'agent_workspace',
        'model' => 'gpt-image-2.5-sunburst',
        'prompt_tokens' => 20,
        'completion_tokens' => 50,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.usage.index'))
        ->assertOk()
        ->assertSee('Image generation by model')
        ->assertSee('Image Creator')
        ->assertSee('2 images')
        ->assertSee('46 tokens')
        ->assertSee('70 tokens');
});

test('administrators can create an active user with profile fields', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'New Teacher',
            'email' => 'new.teacher@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'teacher',
            'department' => 'Academic',
            'employee_id' => 'T-100',
            'is_active' => '1',
        ])
        ->assertRedirect(route('admin.users.index'));

    $this->assertDatabaseHas('users', [
        'email' => 'new.teacher@example.test',
        'role' => 'teacher',
        'department' => 'Academic',
        'employee_id' => 'T-100',
        'is_active' => 1,
    ]);
});

test('administrators can set and reset a custom monthly quota for selected users', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $first = User::factory()->create(['name' => 'Quota One']);
    $second = User::factory()->create(['name' => 'Quota Two']);

    $this->actingAs($admin)
        ->post(route('admin.users.token-quota.update'), [
            'user_ids' => [$first->id, $second->id],
            'action' => 'set',
            'token_quota_limit' => 10_000_000,
        ])
        ->assertRedirect(route('admin.users.index'));

    expect($first->fresh()->token_quota_limit)->toBe(10_000_000)
        ->and($second->fresh()->token_quota_limit)->toBe(10_000_000)
        ->and(app(TokenQuotaService::class)->summary($first->fresh())['limit'])->toBe(10_000_000);

    $this->post(route('admin.users.token-quota.update'), [
        'user_ids' => [$first->id, $second->id],
        'action' => 'reset',
    ])->assertRedirect(route('admin.users.index'));

    expect($first->fresh()->token_quota_limit)->toBeNull()
        ->and($second->fresh()->token_quota_limit)->toBeNull();
});

test('the user list selects token quota recipients with checkboxes', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $user = User::factory()->create(['name' => 'Checkbox Recipient']);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee('No users selected. Tick users in the list below.')
        ->assertSee('tokenQuotaBulkForm', false)
        ->assertSee('token-quota-user-checkbox', false)
        ->assertSee('Checkbox Recipient');
});

test('administrators can open the user create and edit forms with department choices', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $user = User::factory()->create(['department' => 'CIEC']);

    $this->actingAs($admin)
        ->get(route('admin.users.create'))
        ->assertOk()
        ->assertSee('Create User')
        ->assertSee('CIEC');

    $this->get(route('admin.users.edit', $user))
        ->assertOk()
        ->assertSee('Edit User')
        ->assertSee('CIEC');
});

test('the user list uses the Admin delete dialog instead of a browser confirmation', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $user = User::factory()->create(['name' => 'Delete Dialog User']);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee('deleteUserModal')
        ->assertSee('Delete permanently')
        ->assertDontSee('return confirm(', false);
});

test('the user list disables deletion up front when a user owns workspace data', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $user = User::factory()->create(['name' => 'Protected List User']);
    $user->agents()->create(['title' => 'Protected Agent']);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee('Protected List User')
        ->assertSee('delete-user-disabled', false)
        ->assertSee('Cannot delete: this user still has Agents.', false)
        ->assertSee('disabled', false);
});

test('the user list displays the users last sign-in time or Never', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $signedInUser = User::factory()->create(['last_login_at' => now()->setTime(9, 30)]);
    User::factory()->create(['last_login_at' => null]);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee('Last sign-in')
        ->assertSee($signedInUser->last_login_at->format('d M Y, H:i'))
        ->assertSee('Never');
});

test('admin forms show validation errors in a summary and beside the invalid field', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

    $this->actingAs($admin)
        ->from(route('admin.users.create'))
        ->post(route('admin.users.store'), [
            'name' => 'Incomplete User',
            'role' => 'staff',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])
        ->assertRedirect(route('admin.users.create'))
        ->assertSessionHasErrors(['email', 'password']);

    $this->withViewErrors(['email' => 'The email field is required.'])
        ->view('admin.users.create', ['departments' => []])
        ->assertSee('Please review the highlighted fields.')
        ->assertSee('admin-field-error', false)
        ->assertSee('The email field is required.');
});

test('administrators can update another users account details', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $user = User::factory()->create([
        'name' => 'Original User',
        'email' => 'original@example.test',
        'role' => 'student',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $user), [
            'name' => 'Updated User',
            'email' => 'updated@example.test',
            'role' => 'staff',
            'department' => 'CIEC',
            'employee_id' => 'S-200',
            'is_active' => '0',
        ])
        ->assertRedirect(route('admin.users.index'));

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Updated User',
        'email' => 'updated@example.test',
        'role' => 'staff',
        'department' => 'CIEC',
        'employee_id' => 'S-200',
        'is_active' => 0,
    ]);
});

test('administrators can deactivate another user by unchecking the account checkbox', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $user = User::factory()->create(['role' => 'student', 'is_active' => true]);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'department' => $user->department,
            'employee_id' => $user->employee_id,
        ])
        ->assertRedirect(route('admin.users.index'));

    expect($user->fresh()->is_active)->toBeFalse();
});

test('administrators cannot delete a user who still owns workspace data', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $user = User::factory()->create();
    $user->agents()->create(['title' => 'Protected Agent']);

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $user))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHasErrors('user');

    $this->assertDatabaseHas('users', ['id' => $user->id]);
    $this->assertDatabaseHas('agents', ['user_id' => $user->id, 'title' => 'Protected Agent']);
});

test('administrators can delete a user with no related data', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $user))
        ->assertRedirect(route('admin.users.index'));

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

test('ticket search uses the details field and notes have a dedicated route', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $ticket = SupportTicket::create([
        'name' => 'Reporter',
        'email' => 'reporter@example.test',
        'type' => 'Technical Issue / Bug Report',
        'priority' => 'high',
        'subject' => 'Upload failure',
        'details' => 'The report contains a unique details phrase.',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.tickets.index', ['search' => 'unique details phrase']))
        ->assertOk()
        ->assertSee('Upload failure');

    $this->actingAs($admin)
        ->patch(route('admin.tickets.notes', $ticket), ['admin_notes' => 'Investigating the issue.'])
        ->assertRedirect();

    $this->assertDatabaseHas('support_tickets', ['id' => $ticket->id, 'admin_notes' => 'Investigating the issue.']);
});

test('administrators can reply to a support ticket and mark it resolved', function () {
    Mail::fake();
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $ticket = SupportTicket::create([
        'name' => 'Reporter',
        'email' => 'reporter@example.test',
        'type' => 'Technical Issue / Bug Report',
        'priority' => 'medium',
        'subject' => 'Upload failure',
        'details' => 'The report cannot be uploaded.',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.tickets.replies.store', $ticket), [
            'body' => 'We have fixed the issue. Please try again.',
            'resolve' => '1',
        ])
        ->assertRedirect();

    $reply = SupportTicketReply::firstOrFail();
    expect($reply->author_id)->toBe($admin->id)
        ->and($reply->sent_at)->not->toBeNull();
    $this->assertDatabaseHas('support_tickets', ['id' => $ticket->id, 'status' => 'resolved']);
    Mail::assertSent(SupportTicketReplyMail::class, fn (SupportTicketReplyMail $mail) => $mail->reply->is($reply)
        && $mail->hasTo('reporter@example.test'));
});

test('a ticket reply requires meaningful content', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $ticket = SupportTicket::create([
        'name' => 'Reporter',
        'email' => 'reporter@example.test',
        'type' => 'Question / How-To',
        'priority' => 'low',
        'subject' => 'Question',
        'details' => 'I need help with this feature.',
    ]);

    $this->actingAs($admin)
        ->from(route('admin.tickets.show', $ticket))
        ->post(route('admin.tickets.replies.store', $ticket), ['body' => 'No'])
        ->assertRedirect(route('admin.tickets.show', $ticket))
        ->assertSessionHasErrors('body');

    expect(SupportTicketReply::count())->toBe(0);
});

test('new requester follow-ups are highlighted for admins and marked read when opened', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $requester = User::factory()->create();
    $ticket = SupportTicket::create([
        'user_id' => $requester->id,
        'name' => $requester->name,
        'email' => $requester->email,
        'type' => 'Technical Issue / Bug Report',
        'priority' => 'medium',
        'subject' => 'Follow-up required',
        'details' => 'The original issue is still open.',
    ]);
    $followUp = SupportTicketReply::create([
        'support_ticket_id' => $ticket->id,
        'author_id' => $requester->id,
        'body' => 'I have more details to add.',
        'sent_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.tickets.index'))
        ->assertOk()
        ->assertSee('New reply');

    $this->get(route('admin.tickets.show', $ticket))->assertOk();

    expect($followUp->fresh()->admin_read_at)->not->toBeNull();
});

test('administrators can filter tickets that need a reply', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $requester = User::factory()->create();
    $needsReply = SupportTicket::create([
        'user_id' => $requester->id,
        'name' => $requester->name,
        'email' => $requester->email,
        'type' => 'Other',
        'priority' => 'medium',
        'subject' => 'Needs a response',
        'details' => 'A follow-up was sent.',
    ]);
    $other = SupportTicket::create([
        'user_id' => User::factory()->create()->id,
        'name' => 'Other Reporter',
        'email' => 'other@example.test',
        'type' => 'Other',
        'priority' => 'low',
        'subject' => 'No follow-up',
        'details' => 'No additional message.',
    ]);
    SupportTicketReply::create([
        'support_ticket_id' => $needsReply->id,
        'author_id' => $requester->id,
        'body' => 'Please provide an update.',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.tickets.index', ['needs_reply' => 1]))
        ->assertOk()
        ->assertSee('Needs a response')
        ->assertDontSee('No follow-up');
});

test('administrators can review a pending showcase', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $author = User::factory()->create();
    $showcase = ShowcasePost::create([
        'author_id' => $author->id,
        'department' => 'Academic',
        'title' => 'Pending showcase',
        'description' => 'A submission awaiting review.',
        'status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.showcases.update', $showcase), [
            'title' => $showcase->title,
            'description' => $showcase->description,
            'department' => $showcase->department,
            'status' => 'published',
        ])
        ->assertRedirect(route('admin.showcases.index'));

    $this->assertDatabaseHas('showcase_posts', ['id' => $showcase->id, 'status' => 'published']);
});

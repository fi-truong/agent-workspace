<?php

use App\Models\ShowcasePost;
use App\Models\SupportTicket;
use App\Models\User;

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
        ->assertForbidden();
});

test('administrators can access the dashboard', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Recent Admin Activity');
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

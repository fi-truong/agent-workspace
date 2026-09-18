<?php

use App\Models\User;

test('logout always redirects to the local login page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('login.local.form'));

    $this->assertGuest();
});

test('an expired logout form redirects to the local login page instead of showing page expired', function () {
    $this->withMiddleware()
        ->post(route('logout'), [], ['X-CSRF-TOKEN' => 'expired-token'])
        ->assertRedirect(route('login.local.form'));
});

test('authenticated pages cannot be cached after logout', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private');
});

test('agent workspace requires authentication', function () {
    $this->get(route('ai-plus.agent-workspace.index'))
        ->assertRedirect(route('login.local.form'));

    $this->post(route('ai-plus.agent-workspace.send'), ['message' => 'Hello'])
        ->assertRedirect(route('login.local.form'));
});

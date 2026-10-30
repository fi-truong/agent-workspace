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

test('AI+ workspace redirects to login after the configured idle session lifetime', function () {
    config(['session.lifetime' => 120]);

    $this->actingAs(User::factory()->create())
        ->get(route('ai-plus.agent-workspace.index'))
        ->assertOk()
        ->assertSee('name="ai-plus-session-timeout-ms" content="7200000"', false)
        ->assertSee('session-timeout.js', false);
});

test('the login page explains when an AI+ session expired', function () {
    $this->get(route('login.local.form', ['session_expired' => 1]))
        ->assertOk()
        ->assertSee('Your AI+ session expired after a period of inactivity. Please sign in again.');
});

test('agent workspace requires authentication', function () {
    $this->get(route('ai-plus.agent-workspace.index'))
        ->assertRedirect(route('login.local.form'));

    $this->post(route('ai-plus.agent-workspace.send'), ['message' => 'Hello'])
        ->assertRedirect(route('login.local.form'));
});

test('the AI Plus homepage requires authentication', function () {
    $this->get(route('ai-plus.index'))
        ->assertRedirect(route('login.local.form'));
});

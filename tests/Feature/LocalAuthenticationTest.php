<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('the local login screen is available', function () {
    $this->get(route('login.local.form'))
        ->assertOk();
});

test('an active user can sign in through local login and reaches AI Plus', function () {
    $user = User::factory()->create([
        'email' => 'staff@example.test',
        'password' => Hash::make('password123'),
        'is_active' => true,
    ]);

    $this->post(route('login.local'), [
        'email' => $user->email,
        'password' => 'password123',
    ])->assertRedirect(route('ai-plus.index'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_login_at)->not->toBeNull();
});

test('inactive users and invalid credentials cannot sign in through local login', function () {
    $inactiveUser = User::factory()->create([
        'email' => 'inactive@example.test',
        'password' => Hash::make('password123'),
        'is_active' => false,
    ]);

    $this->from(route('login.local.form'))
        ->post(route('login.local'), ['email' => $inactiveUser->email, 'password' => 'password123'])
        ->assertRedirect(route('login.local.form'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('an active session is ended immediately when an administrator deactivates the account', function () {
    $user = User::factory()->create(['is_active' => false]);

    $this->actingAs($user)
        ->get(route('ai-plus.index'))
        ->assertRedirect(route('login.local.form'))
        ->assertSessionHas('status', 'Your account has been deactivated. Please contact an administrator.');

    $this->assertGuest();
});

test('an authenticated user can change their local password', function () {
    $user = User::factory()->create(['password' => Hash::make('old-password')]);

    $this->actingAs($user)
        ->postJson(route('account.password.update'), [
            'current_password' => 'old-password',
            'new_password' => 'new-password',
            'new_password_confirmation' => 'new-password',
        ])
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
});

test('a user cannot change their local password without the current password', function () {
    $user = User::factory()->create(['password' => Hash::make('old-password')]);

    $this->actingAs($user)
        ->postJson(route('account.password.update'), [
            'current_password' => 'wrong-password',
            'new_password' => 'new-password',
            'new_password_confirmation' => 'new-password',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');

    expect(Hash::check('old-password', $user->fresh()->password))->toBeTrue();
});

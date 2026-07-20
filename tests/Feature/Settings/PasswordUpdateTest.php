<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('password update page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('user-password.edit'));

    $response->assertOk();
});

test('password can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('user-password.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('user-password.edit'));

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('user-password.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect(route('user-password.edit'));
});

test('recently authenticated oauth users can set their first password', function () {
    $user = User::factory()->create([
        'password_login_enabled' => false,
    ]);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->from(route('user-password.edit'))
        ->put(route('user-password.update'), [
            'current_password' => '',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('user-password.edit'));

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue()
        ->and($user->password_login_enabled)->toBeTrue();
});

test('stale oauth sessions cannot set a password', function () {
    $user = User::factory()->create([
        'password_login_enabled' => false,
    ]);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => 0])
        ->from(route('user-password.edit'))
        ->put(route('user-password.update'), [
            'current_password' => '',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasErrors('current_password')
        ->assertRedirect(route('user-password.edit'));

    expect($user->refresh()->password_login_enabled)->toBeFalse();
});

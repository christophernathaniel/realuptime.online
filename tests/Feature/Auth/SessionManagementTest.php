<?php

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the session management screen with the current session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/settings/sessions')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/sessions')
            ->where('sessions.0.isCurrent', true));
});

it('can revoke another tracked session', function () {
    $user = User::factory()->create();
    $session = UserSession::query()->create([
        'user_id' => $user->id,
        'session_id' => 'remote-session',
        'ip_address' => '127.0.0.2',
        'user_agent' => 'Mozilla/5.0 Chrome/123.0',
        'last_path' => 'dashboard',
        'last_active_at' => now()->subMinutes(5),
    ]);

    $this->actingAs($user)
        ->delete("/settings/sessions/{$session->id}")
        ->assertRedirect();

    expect($session->fresh()->revoked_at)->not->toBeNull();
});

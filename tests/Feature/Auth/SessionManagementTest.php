<?php

use App\Models\User;
use App\Models\UserSession;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withSession(['auth.password_confirmed_at' => time()]);
});

it('renders the session management screen with the current session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/settings/sessions')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/sessions')
            ->where('sessions.0.isCurrent', true)
            ->missing('sessions.0.sessionId'));
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

it('throttles tracked session writes between authenticated requests', function () {
    config()->set('realuptime.session_tracking.refresh_seconds', 300);

    $startedAt = CarbonImmutable::parse('2026-03-11 10:00:00');
    CarbonImmutable::setTestNow($startedAt);

    $user = User::factory()->create();

    $this->actingAs($user);

    $this->get('/monitors')->assertOk();

    $trackedSession = UserSession::query()->firstOrFail();
    $initialLastActiveAt = $trackedSession->last_active_at?->timestamp;

    CarbonImmutable::setTestNow($startedAt->addMinute());

    $this->get('/monitors')->assertOk();

    $trackedSession->refresh();

    expect(UserSession::query()->count())->toBe(1);
    expect($trackedSession->last_active_at?->timestamp)->toBe($initialLastActiveAt);

    CarbonImmutable::setTestNow();
});

it('never reactivates a revoked session while refreshing its activity', function () {
    config()->set('realuptime.session_tracking.verify_seconds', 600);
    config()->set('realuptime.session_tracking.refresh_seconds', 60);

    $startedAt = CarbonImmutable::parse('2026-03-11 10:00:00');
    CarbonImmutable::setTestNow($startedAt);

    $user = User::factory()->create();

    $this->actingAs($user)->get('/monitors')->assertOk();

    $trackedSession = UserSession::query()->firstOrFail();
    $trackedSession->forceFill(['revoked_at' => $startedAt->addMinute()])->save();

    CarbonImmutable::setTestNow($startedAt->addMinutes(2));

    $this->get('/monitors')->assertOk();

    expect($trackedSession->fresh()->revoked_at)->not->toBeNull();

    CarbonImmutable::setTestNow();
});

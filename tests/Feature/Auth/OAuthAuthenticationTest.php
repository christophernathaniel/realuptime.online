<?php

use App\Models\ConnectedAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.google.client_id', 'google-client');
    config()->set('services.google.client_secret', 'google-secret');
    config()->set('services.google.redirect', 'http://localhost/auth/google/callback');
    config()->set('services.github.client_id', 'github-client');
    config()->set('services.github.client_secret', 'github-secret');
    config()->set('services.github.redirect', 'http://localhost/auth/github/callback');
});

it('authenticates users through oauth and creates a linked account', function () {
    $socialiteUser = (new SocialiteUser)
        ->map([
            'id' => 'google-123',
            'name' => 'OAuth User',
            'email' => 'oauth@example.com',
            'avatar' => 'https://example.com/avatar.png',
        ])
        ->setToken(Str::random(20))
        ->setRefreshToken(Str::random(20))
        ->setExpiresIn(3600);

    Socialite::shouldReceive('driver->user')->once()->andReturn($socialiteUser);

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticated();

    $user = User::query()->where('email', 'oauth@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->password_login_enabled)->toBeFalse();

    $this->assertDatabaseHas('connected_accounts', [
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'google-123',
        'provider_email' => 'oauth@example.com',
    ]);
});

it('links an oauth provider to an authenticated account', function () {
    $user = User::factory()->create();

    $socialiteUser = (new SocialiteUser)
        ->map([
            'id' => 'github-456',
            'name' => 'Linked User',
            'email' => 'linked@example.com',
            'avatar' => 'https://example.com/avatar.png',
        ])
        ->setToken(Str::random(20));

    Socialite::shouldReceive('driver->user')->once()->andReturn($socialiteUser);

    $this->actingAs($user)
        ->withSession([
            'oauth.intent' => 'link',
            'oauth.user_id' => $user->id,
        ])
        ->get('/auth/github/callback')
        ->assertRedirect(route('profile.edit', absolute: false));

    $this->assertDatabaseHas('connected_accounts', [
        'user_id' => $user->id,
        'provider' => 'github',
        'provider_id' => 'github-456',
    ]);
});

it('prevents disconnecting the last oauth provider when password login is disabled', function () {
    $user = User::factory()->create([
        'password_login_enabled' => false,
    ]);

    ConnectedAccount::query()->create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'google-123',
        'provider_email' => $user->email,
    ]);

    $this->actingAs($user)
        ->delete('/settings/oauth/google')
        ->assertRedirect();

    $this->assertDatabaseHas('connected_accounts', [
        'user_id' => $user->id,
        'provider' => 'google',
    ]);
});

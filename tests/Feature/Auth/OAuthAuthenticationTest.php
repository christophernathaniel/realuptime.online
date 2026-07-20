<?php

use App\Models\ConnectedAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
    $accessToken = 'oauth-access-token-secret';
    $refreshToken = 'oauth-refresh-token-secret';
    $socialiteUser = (new SocialiteUser)
        ->map([
            'id' => 'google-123',
            'name' => 'OAuth User',
            'email' => 'oauth@example.com',
            'avatar' => 'https://example.com/avatar.png',
        ])
        ->setToken($accessToken)
        ->setRefreshToken($refreshToken)
        ->setExpiresIn(3600);

    Socialite::shouldReceive('driver->user')->once()->andReturn($socialiteUser);

    $response = $this
        ->withSession(['oauth.provider' => 'google'])
        ->get('/auth/google/callback');

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

    $account = ConnectedAccount::query()->firstOrFail();
    $rawAccount = DB::table('connected_accounts')->first();

    expect($account->token)->toBe($accessToken)
        ->and($account->refresh_token)->toBe($refreshToken)
        ->and($rawAccount->token)->not->toContain($accessToken)
        ->and($rawAccount->refresh_token)->not->toContain($refreshToken)
        ->and($account->toArray())->not->toHaveKeys(['token', 'refresh_token']);
});

it('does not auto-link an unrecognized oauth identity to an existing email account', function () {
    $user = User::factory()->create([
        'email' => 'existing@example.com',
    ]);
    $socialiteUser = (new SocialiteUser)
        ->map([
            'id' => 'google-unrecognized',
            'name' => 'Different Identity',
            'email' => 'EXISTING@example.com',
        ])
        ->setToken(Str::random(20));

    Socialite::shouldReceive('driver->user')->once()->andReturn($socialiteUser);

    $this->withSession(['oauth.provider' => 'google'])
        ->get('/auth/google/callback')
        ->assertRedirect(route('login', absolute: false))
        ->assertSessionHas('error');

    $this->assertGuest();
    $this->assertDatabaseMissing('connected_accounts', [
        'user_id' => $user->id,
        'provider' => 'google',
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
            'oauth.provider' => 'github',
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

it('rejects an oauth callback for a provider that did not initiate the flow', function () {
    Socialite::shouldReceive('driver->user')->never();

    $this->withSession([
        'oauth.intent' => 'login',
        'oauth.provider' => 'google',
    ])->get('/auth/github/callback')
        ->assertRedirect(route('login', absolute: false))
        ->assertSessionHas('error');

    $this->assertGuest();
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
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete('/settings/oauth/google')
        ->assertRedirect();

    $this->assertDatabaseHas('connected_accounts', [
        'user_id' => $user->id,
        'provider' => 'google',
    ]);
});

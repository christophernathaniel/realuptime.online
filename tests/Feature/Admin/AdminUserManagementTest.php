<?php

use App\Models\User;
use App\Models\WorkspaceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('registers self-serve accounts as non-admin users by default', function () {
    $this->post('/register', [
        'name' => 'Standard User',
        'email' => 'standard@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'standard@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user?->is_admin)->toBeFalse();
});

it('does not elevate invited users to admin when they accept workspace access', function () {
    $owner = User::factory()->premium()->create();
    $invitee = User::factory()->create([
        'email' => 'invitee@example.com',
        'is_admin' => false,
    ]);

    $this->actingAs($owner)
        ->post('/team-members/invitations', [
            'email' => $invitee->email,
        ])
        ->assertRedirect();

    $membership = WorkspaceMembership::query()->first();

    expect($membership)->not->toBeNull();

    $this->actingAs($invitee)
        ->get("/workspace-invitations/{$membership->token}")
        ->assertRedirect('/team-members');

    expect($invitee->refresh()->is_admin)->toBeFalse();
});

it('restricts admin user management to platform admins', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/users')
        ->assertForbidden();
});

it('lets admins monitor, create, promote, and delete users', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin@example.com',
    ]);
    $user = User::factory()->create([
        'email' => 'member@example.com',
    ]);

    $this->actingAs($admin)
        ->get('/admin/users')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users')
            ->where('summary.users', 2)
            ->where('summary.admins', 1));

    $this->actingAs($admin)
        ->post('/admin/users', [
            'name' => 'Created User',
            'email' => 'created@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertRedirect();

    $createdUser = User::query()->where('email', 'created@example.com')->first();

    expect($createdUser)->not->toBeNull();
    expect($createdUser?->is_admin)->toBeFalse();

    $this->actingAs($admin)
        ->patch("/admin/users/{$user->id}", [
            'is_admin' => true,
        ])
        ->assertRedirect();

    expect($user->refresh()->is_admin)->toBeTrue();

    $this->actingAs($admin)
        ->delete("/admin/users/{$user->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('users', [
        'id' => $user->id,
    ]);
});

it('lets admins override membership plans for users', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create([
        'email' => 'member@example.com',
    ]);

    $this->actingAs($admin)
        ->patch("/admin/users/{$user->id}/membership", [
            'admin_plan_override' => 'premium',
        ])
        ->assertRedirect();

    expect($user->refresh()->admin_plan_override)->toBe('premium');
    expect($user->membershipPlan()->value)->toBe('premium');

    $this->actingAs($admin)
        ->patch("/admin/users/{$user->id}/membership", [
            'admin_plan_override' => null,
        ])
        ->assertRedirect();

    expect($user->refresh()->admin_plan_override)->toBeNull();
    expect($user->membershipPlan()->value)->toBe('free');
});

it('can grant admin access through the admin-user artisan command', function () {
    $user = User::factory()->create([
        'email' => 'ops@example.com',
    ]);

    $this->artisan('realuptime:admin-user', [
        'email' => $user->email,
    ])->assertExitCode(0);

    expect($user->refresh()->is_admin)->toBeTrue();
});

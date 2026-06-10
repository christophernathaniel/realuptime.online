<?php

use App\Models\ApiToken;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\NotificationContact;
use App\Models\NotificationLog;
use App\Models\StatusPage;
use App\Models\UserSession;
use App\Models\WorkspaceIntegration;
use App\Models\User;
use App\Models\WorkspaceMembership;
use Illuminate\Support\Facades\DB;
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

    $this->actingAs($user)
        ->get('/admin/users/'.$user->id)
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
            ->where('summary.admins', 1)
            ->where('users.total', 2));

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

it('lets admins inspect a detailed support view for an account', function () {
    config()->set('membership.plans.premium.stripe_price_id', 'price_premium');

    $admin = User::factory()->admin()->create();
    $user = User::factory()->create([
        'email' => 'member@example.com',
        'pm_type' => 'visa',
        'pm_last_four' => '4242',
    ]);
    $member = User::factory()->create([
        'email' => 'teammate@example.com',
    ]);

    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Primary API',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_DOWN,
        'target' => 'https://example.com/health',
        'interval_seconds' => 60,
        'timeout_seconds' => 15,
        'retry_limit' => 2,
        'region' => 'Europe',
        'last_checked_at' => now()->subMinute(),
        'last_response_time_ms' => 812,
        'last_http_status' => 500,
        'last_error_message' => 'Expected HTTP 200 but received 500.',
    ]);

    $incident = Incident::query()->create([
        'monitor_id' => $monitor->id,
        'started_at' => now()->subMinutes(8),
        'reason' => 'Expected HTTP 200 but received 500.',
    ]);

    $statusPage = StatusPage::query()->create([
        'user_id' => $user->id,
        'name' => 'Production',
        'slug' => 'production',
        'headline' => 'Production systems',
        'published' => true,
    ]);
    $statusPage->monitors()->attach($monitor->id, ['sort_order' => 1]);

    $contact = NotificationContact::query()->create([
        'user_id' => $user->id,
        'name' => 'Operations',
        'email' => 'ops@example.com',
        'enabled' => true,
        'is_primary' => true,
    ]);
    $contact->monitors()->attach($monitor->id);

    WorkspaceIntegration::query()->create([
        'user_id' => $user->id,
        'provider' => WorkspaceIntegration::PROVIDER_WEBHOOK,
        'name' => 'Slack Workflow',
        'status' => WorkspaceIntegration::STATUS_ACTIVE,
        'config' => ['webhook_url' => 'https://example.test/webhooks/workflow'],
        'scopes' => ['monitor.down'],
        'last_tested_at' => now()->subHour(),
    ]);

    ApiToken::query()->create([
        'user_id' => $user->id,
        'name' => 'Primary automation',
        'token_hash' => hash('sha256', 'secret'),
        'last_used_at' => now()->subMinutes(5),
    ]);

    UserSession::query()->create([
        'user_id' => $user->id,
        'session_id' => 'sess_123',
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Firefox',
        'last_path' => '/monitors',
        'last_active_at' => now()->subMinutes(2),
    ]);

    WorkspaceMembership::query()->create([
        'owner_user_id' => $user->id,
        'member_user_id' => $member->id,
        'invited_by_user_id' => $user->id,
        'invited_email' => $member->email,
        'token' => 'accepted-token',
        'invited_at' => now()->subDay(),
        'accepted_at' => now()->subHours(20),
    ]);

    WorkspaceMembership::query()->create([
        'owner_user_id' => $user->id,
        'invited_by_user_id' => $user->id,
        'invited_email' => 'pending@example.com',
        'token' => 'pending-token',
        'invited_at' => now()->subHours(6),
    ]);

    NotificationLog::query()->create([
        'monitor_id' => $monitor->id,
        'incident_id' => $incident->id,
        'notification_contact_id' => $contact->id,
        'channel' => 'email',
        'type' => 'downtime_alert',
        'subject' => 'Primary API is down',
        'status' => 'sent',
        'sent_at' => now()->subMinutes(7),
    ]);

    DB::table('subscriptions')->insert([
        'user_id' => $user->id,
        'type' => 'default',
        'stripe_id' => 'sub_123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_premium',
        'quantity' => 1,
        'trial_ends_at' => null,
        'ends_at' => null,
        'created_at' => now()->subMonth(),
        'updated_at' => now()->subDay(),
    ]);

    $this->actingAs($admin)
        ->get('/admin/users/'.$user->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/user-show')
            ->where('account.email', 'member@example.com')
            ->where('account.membershipPlanLabel', 'Premium')
            ->where('billing.currentSubscription.status', 'active')
            ->where('billing.invoiceStatus', 'none')
            ->where('billing.paymentMethodLabel', 'Visa ending in 4242')
            ->where('usage.monitors', 1)
            ->where('monitors.0.name', 'Primary API')
            ->where('statusPages.0.name', 'Production')
            ->where('contacts.0.name', 'Operations')
            ->where('integrations.0.name', 'Slack Workflow')
            ->where('apiTokens.0.name', 'Primary automation')
            ->where('team.0.status', 'Accepted')
            ->where('recentIncidents.0.reason', 'Expected HTTP 200 but received 500.')
            ->where('recentNotifications.0.subject', 'Primary API is down'));
});

it('lets admins grant, extend, and clear courtesy membership extensions', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create([
        'email' => 'member@example.com',
    ]);

    $this->actingAs($admin)
        ->patch("/admin/users/{$user->id}/support-extension", [
            'plan' => 'premium',
        ])
        ->assertRedirect();

    $user->refresh();
    $firstExpiry = $user->support_plan_expires_at;

    expect($user->support_plan_extension)->toBe('premium');
    expect($user->membershipPlan()->value)->toBe('premium');
    expect($firstExpiry)->not->toBeNull();

    $this->actingAs($admin)
        ->patch("/admin/users/{$user->id}/support-extension", [
            'plan' => 'premium',
        ])
        ->assertRedirect();

    $user->refresh();

    expect($user->support_plan_expires_at?->greaterThan($firstExpiry))->toBeTrue();

    $this->actingAs($admin)
        ->delete("/admin/users/{$user->id}/support-extension")
        ->assertRedirect();

    $user->refresh();

    expect($user->support_plan_extension)->toBeNull();
    expect($user->support_plan_expires_at)->toBeNull();
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

<?php

use App\Models\ApiToken;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\NotificationContact;
use App\Models\NotificationLog;
use App\Models\StatusPage;
use App\Models\User;
use App\Models\UserSession;
use App\Models\WorkspaceIntegration;
use App\Models\WorkspaceMembership;
use App\Services\Billing\SubscriptionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('realuptime.admin.main_admin_email', 'admin@example.com');
    $this->withSession(['auth.password_confirmed_at' => time()]);
});

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
        ->post("/workspace-invitations/{$membership->token}")
        ->assertRedirect('/team-members');

    expect($invitee->refresh()->is_admin)->toBeFalse();
});

it('restricts admin user management to platform admins', function () {
    $user = User::factory()->create();
    $legacyAdmin = User::factory()->admin()->create([
        'email' => 'legacy-admin@example.com',
    ]);

    $this->actingAs($user)
        ->get('/admin/users')
        ->assertForbidden();

    $this->actingAs($user)
        ->get('/admin/users/'.$user->id)
        ->assertForbidden();

    $this->actingAs($legacyAdmin)
        ->get('/admin/users')
        ->assertForbidden();
});

it('requires recent password confirmation for admin account changes', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin@example.com',
        'password_login_enabled' => true,
    ]);
    $user = User::factory()->create([
        'name' => 'Original Name',
    ]);

    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => 0])
        ->patch("/admin/users/{$user->id}", [
            'name' => 'Changed Without Confirmation',
            'email' => $user->email,
            'email_verified' => true,
            'password_login_enabled' => true,
            'password' => '',
            'password_confirmation' => '',
        ])
        ->assertRedirect(route('password.confirm'));

    expect($user->fresh()->name)->toBe('Original Name');
});

it('lets the main admin monitor, create, update, and delete users', function () {
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
            'name' => 'Updated Member',
            'email' => 'updated-member@example.com',
            'email_verified' => true,
            'password_login_enabled' => true,
            'password' => 'updated-password',
            'password_confirmation' => 'updated-password',
        ])
        ->assertRedirect();

    $user->refresh();

    expect($user->name)->toBe('Updated Member');
    expect($user->email)->toBe('updated-member@example.com');
    expect($user->email_verified_at)->not->toBeNull();
    expect($user->is_admin)->toBeFalse();

    $this->actingAs($admin)
        ->delete("/admin/users/{$user->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('users', [
        'id' => $user->id,
    ]);
});

it('prevents the main admin from removing its own verified password login', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin@example.com',
        'password_login_enabled' => true,
    ]);

    $payload = [
        'name' => $admin->name,
        'email' => $admin->email,
        'email_verified' => false,
        'password_login_enabled' => true,
        'password' => '',
        'password_confirmation' => '',
    ];

    $this->actingAs($admin)
        ->patch("/admin/users/{$admin->id}", $payload)
        ->assertSessionHas('error', 'The main admin must remain verified with password login enabled.');

    $payload['email_verified'] = true;
    $payload['password_login_enabled'] = false;

    $this->actingAs($admin)
        ->patch("/admin/users/{$admin->id}", $payload)
        ->assertSessionHas('error', 'The main admin must remain verified with password login enabled.');

    expect($admin->refresh()->email_verified_at)->not->toBeNull()
        ->and($admin->password_login_enabled)->toBeTrue();
});

it('lets admins override membership plans for users', function () {
    $admin = User::factory()->admin()->create(['email' => 'admin@example.com']);
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

    $admin = User::factory()->admin()->create(['email' => 'admin@example.com']);
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
            ->where('account.lastActiveLabel', '2m ago')
            ->where('billing.currentSubscription.status', 'active')
            ->where('billing.paymentMethodLabel', 'Visa ending in 4242')
            ->where('usage.monitors', 1)
            ->where('monitors.0.name', 'Primary API')
            ->where('statusPages.0.name', 'Production')
            ->where('contacts.0.name', 'Operations')
            ->where('integrations.0.name', 'Slack Workflow')
            ->where('apiTokens.0.name', 'Primary automation')
            ->where('team.0.status', 'Accepted')
            ->where('recentIncidents.0.reason', 'Expected HTTP 200 but received 500.')
            ->where('recentNotifications.0.subject', 'Primary API is down')
            ->missing('stripeInvoices'));
});

it('shows only the reactivation action during a Stripe grace period', function () {
    config()->set('cashier.key', 'pk_test_example');
    config()->set('cashier.secret', 'sk_test_example');
    config()->set('cashier.webhook.secret', 'whsec_example');
    config()->set('membership.plans.premium.stripe_price_id', 'price_premium');
    config()->set('membership.plans.ultra.stripe_price_id', 'price_ultra');

    $admin = User::factory()->admin()->create(['email' => 'admin@example.com']);
    $user = User::factory()->create([
        'stripe_id' => 'cus_grace_period',
        'pm_type' => 'visa',
        'pm_last_four' => '4242',
    ]);

    DB::table('subscriptions')->insert([
        'user_id' => $user->id,
        'type' => 'default',
        'stripe_id' => 'sub_grace_period',
        'stripe_status' => 'active',
        'stripe_price' => 'price_premium',
        'quantity' => 1,
        'trial_ends_at' => null,
        'ends_at' => now()->addWeek(),
        'created_at' => now()->subMonth(),
        'updated_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get('/admin/users/'.$user->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('billing.currentSubscription.onGracePeriod', true)
            ->where('billing.canChangePlan', false)
            ->where('billing.canCancel', false)
            ->where('billing.canReactivate', true));
});

it('caps expensive account detail collections', function () {
    $admin = User::factory()->admin()->create(['email' => 'admin@example.com']);
    $user = User::factory()->create();

    foreach (range(1, 55) as $index) {
        Monitor::query()->create([
            'user_id' => $user->id,
            'name' => sprintf('Monitor %02d', $index),
            'type' => Monitor::TYPE_HTTP,
            'status' => Monitor::STATUS_UP,
            'target' => "https://example.com/{$index}",
            'interval_seconds' => 300,
            'timeout_seconds' => 15,
            'retry_limit' => 1,
            'region' => 'Europe',
        ]);
    }

    $this->actingAs($admin)
        ->get('/admin/users/'.$user->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('monitors', 50)
            ->where('collectionMeta.monitors.shown', 50)
            ->where('collectionMeta.monitors.total', 55)
            ->where('collectionMeta.monitors.truncated', true));
});

it('lets admins grant, extend, and clear courtesy membership extensions', function () {
    $admin = User::factory()->admin()->create(['email' => 'admin@example.com']);
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
    config()->set('realuptime.admin.main_admin_email', 'ops@example.com');

    $user = User::factory()->create([
        'email' => 'ops@example.com',
    ]);

    $this->artisan('realuptime:admin-user', [
        'email' => $user->email,
    ])->assertExitCode(0);

    expect($user->refresh()->is_admin)->toBeTrue();
});

it('routes subscription lifecycle actions through the billing manager', function () {
    config()->set('cashier.key', 'pk_test_example');
    config()->set('cashier.secret', 'sk_test_example');
    config()->set('cashier.webhook.secret', 'whsec_example');
    config()->set('membership.plans.premium.stripe_price_id', 'price_premium');

    $admin = User::factory()->admin()->create(['email' => 'admin@example.com']);
    $user = User::factory()->create(['email' => 'member@example.com']);
    $manager = Mockery::mock(SubscriptionManager::class);
    $manager->shouldReceive('changePlan')->once()->withArgs(fn (User $subject, $plan) => $subject->is($user) && $plan->value === 'premium');
    $manager->shouldReceive('cancel')->once()->withArgs(fn (User $subject) => $subject->is($user));
    $manager->shouldReceive('reactivate')->once()->withArgs(fn (User $subject, $plan) => $subject->is($user) && $plan->value === 'premium');
    app()->instance(SubscriptionManager::class, $manager);

    $this->actingAs($admin)
        ->patch("/admin/users/{$user->id}/subscription", ['plan' => 'premium'])
        ->assertRedirect();

    $this->actingAs($admin)
        ->delete("/admin/users/{$user->id}/subscription")
        ->assertRedirect();

    $this->actingAs($admin)
        ->post("/admin/users/{$user->id}/subscription/reactivate", ['plan' => 'premium'])
        ->assertRedirect();
});

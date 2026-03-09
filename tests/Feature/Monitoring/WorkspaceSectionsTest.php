<?php

use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use App\Models\NotificationContact;
use App\Models\StatusPage;
use App\Models\User;
use App\Notifications\MaintenanceWindowNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('renders the completed workspace section pages', function () {
    $user = User::factory()->premium()->create();

    $this->actingAs($user)
        ->get('/status-pages')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('monitoring/status-pages'));

    $this->actingAs($user)
        ->get('/maintenance')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('monitoring/maintenance'));

    $this->actingAs($user)
        ->get('/integrations')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('monitoring/integrations'));

    $this->actingAs($user)
        ->get('/team-members')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('monitoring/team'));
});

it('locks advanced workspace sections for free users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/status-pages')
        ->assertRedirect('/settings/membership');

    $this->actingAs($user)
        ->get('/maintenance')
        ->assertRedirect('/settings/membership');

    $this->actingAs($user)
        ->get('/integrations')
        ->assertRedirect('/settings/membership');

    $this->actingAs($user)
        ->get('/team-members')
        ->assertRedirect('/settings/membership');
});

it('creates a public status page and renders it without authentication', function () {
    $user = User::factory()->premium()->create();
    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Primary API',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com/health',
        'request_method' => 'GET',
        'interval_seconds' => 300,
        'timeout_seconds' => 30,
        'retry_limit' => 2,
        'region' => 'North America',
        'last_checked_at' => now()->subMinute(),
        'last_status_changed_at' => now()->subMinutes(5),
    ]);
    $monitor->checkResults()->create([
        'status' => 'up',
        'checked_at' => now()->subMinute(),
        'attempts' => 1,
        'response_time_ms' => 280,
        'http_status_code' => 200,
    ]);

    $this->actingAs($user)
        ->post('/status-pages', [
            'name' => 'Primary status',
            'slug' => 'primary-status',
            'headline' => 'Primary status',
            'description' => 'Public uptime feed',
            'published' => true,
            'monitor_ids' => [$monitor->id],
        ])
        ->assertRedirect('/status-pages');

    $statusPage = StatusPage::query()->first();

    expect($statusPage)->not->toBeNull();

    $this->get("/status/{$user->public_status_key}/primary-status")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('monitoring/public-status')
            ->where('statusPage.name', 'Primary status'));
});

it('scopes public status pages by user so matching slugs can coexist', function () {
    $firstUser = User::factory()->premium()->create();
    $secondUser = User::factory()->premium()->create();

    $firstMonitor = Monitor::query()->create([
        'user_id' => $firstUser->id,
        'name' => 'First API',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://first.example.com/health',
        'request_method' => 'GET',
        'interval_seconds' => 300,
        'timeout_seconds' => 30,
        'retry_limit' => 2,
        'region' => 'North America',
    ]);

    $secondMonitor = Monitor::query()->create([
        'user_id' => $secondUser->id,
        'name' => 'Second API',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://second.example.com/health',
        'request_method' => 'GET',
        'interval_seconds' => 300,
        'timeout_seconds' => 30,
        'retry_limit' => 2,
        'region' => 'Europe',
    ]);

    $this->actingAs($firstUser)
        ->post('/status-pages', [
            'name' => 'Primary status',
            'slug' => 'primary-status',
            'headline' => 'First primary status',
            'description' => 'First public uptime feed',
            'published' => true,
            'monitor_ids' => [$firstMonitor->id],
        ])
        ->assertRedirect('/status-pages');

    $this->actingAs($secondUser)
        ->post('/status-pages', [
            'name' => 'Primary status',
            'slug' => 'primary-status',
            'headline' => 'Second primary status',
            'description' => 'Second public uptime feed',
            'published' => true,
            'monitor_ids' => [$secondMonitor->id],
        ])
        ->assertRedirect('/status-pages');

    $this->get("/status/{$firstUser->public_status_key}/primary-status")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('monitoring/public-status')
            ->where('statusPage.headline', 'First primary status'));

    $this->get("/status/{$secondUser->public_status_key}/primary-status")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('monitoring/public-status')
            ->where('statusPage.headline', 'Second primary status'));
});

it('creates maintenance windows for selected monitors', function () {
    $user = User::factory()->premium()->create();
    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Primary API',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com/health',
        'request_method' => 'GET',
        'interval_seconds' => 300,
        'timeout_seconds' => 30,
        'retry_limit' => 2,
        'region' => 'North America',
    ]);

    $startsAt = now()->addHours(2)->format('Y-m-d\TH:i');
    $endsAt = now()->addHours(3)->format('Y-m-d\TH:i');

    $this->actingAs($user)
        ->post('/maintenance-windows', [
            'title' => 'Database maintenance',
            'message' => 'Rolling schema changes',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'notify_contacts' => true,
            'monitor_ids' => [$monitor->id],
        ])
        ->assertRedirect();

    $window = MaintenanceWindow::query()->first();

    expect($window)->not->toBeNull();
    expect($window->monitors()->pluck('monitors.id')->all())->toBe([$monitor->id]);
});

it('prefills the focused monitor on the maintenance page', function () {
    $user = User::factory()->premium()->create();
    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Primary API',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com/health',
        'request_method' => 'GET',
        'interval_seconds' => 300,
        'timeout_seconds' => 30,
        'retry_limit' => 2,
        'region' => 'North America',
    ]);

    $this->actingAs($user)
        ->get("/maintenance?monitor_id={$monitor->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('monitoring/maintenance')
            ->where('focusMonitor.id', $monitor->id)
            ->where('focusMonitor.name', 'Primary API')
            ->where('formDefaults.monitor_ids.0', $monitor->id));
});

it('emails contacts when a maintenance window is scheduled with notifications enabled', function () {
    Notification::fake();

    $user = User::factory()->premium()->create();
    $contact = NotificationContact::query()->create([
        'user_id' => $user->id,
        'name' => 'Ops',
        'email' => 'ops@example.com',
        'enabled' => true,
        'is_primary' => true,
    ]);
    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Primary API',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com/health',
        'request_method' => 'GET',
        'interval_seconds' => 300,
        'timeout_seconds' => 30,
        'retry_limit' => 2,
        'region' => 'North America',
    ]);
    $monitor->notificationContacts()->attach($contact);

    $this->actingAs($user)
        ->post('/maintenance-windows', [
            'title' => 'Database maintenance',
            'message' => 'Rolling schema changes',
            'starts_at' => now()->addHours(2)->format('Y-m-d\TH:i'),
            'ends_at' => now()->addHours(3)->format('Y-m-d\TH:i'),
            'notify_contacts' => true,
            'monitor_ids' => [$monitor->id],
        ])
        ->assertRedirect();

    Notification::assertSentOnDemand(MaintenanceWindowNotification::class);

    $this->assertDatabaseHas('notification_logs', [
        'monitor_id' => $monitor->id,
        'notification_contact_id' => $contact->id,
        'type' => 'maintenance',
        'status' => 'sent',
    ]);
});

it('creates notification contacts from the integrations section', function () {
    $user = User::factory()->premium()->create();

    $this->actingAs($user)
        ->post('/notification-contacts', [
            'name' => 'Ops',
            'email' => 'ops@example.com',
            'enabled' => true,
            'is_primary' => true,
        ])
        ->assertRedirect();

    $contact = NotificationContact::query()->first();

    expect($contact)->not->toBeNull();
    expect($contact->is_primary)->toBeTrue();
});

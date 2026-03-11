<?php

use App\Models\Capability;
use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use App\Models\NotificationContact;
use App\Models\StatusPage;
use App\Models\User;
use App\Models\WorkspaceIntegration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

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
    $capability = Capability::query()->create([
        'user_id' => $user->id,
        'name' => 'Sign in',
        'slug' => 'sign-in',
    ]);
    $monitor->capabilities()->attach($capability->id);
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
            ->where('statusPage.name', 'Primary status')
            ->where('statusPage.capabilities.0.name', 'Sign in'));
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

it('paginates status pages and windows the monitor browser', function () {
    $user = User::factory()->premium()->create();
    $monitors = collect(range(1, 30))->map(function (int $index) use ($user) {
        return Monitor::query()->create([
            'user_id' => $user->id,
            'name' => sprintf('Check %03d', $index),
            'type' => Monitor::TYPE_HTTP,
            'status' => Monitor::STATUS_UP,
            'target' => sprintf('https://example.com/%03d', $index),
            'request_method' => 'GET',
            'interval_seconds' => 300,
            'timeout_seconds' => 30,
            'retry_limit' => 2,
            'region' => 'North America',
        ]);
    });

    foreach (range(1, 7) as $index) {
        $statusPage = StatusPage::query()->create([
            'user_id' => $user->id,
            'name' => sprintf('Status %02d', $index),
            'slug' => sprintf('status-%02d', $index),
            'headline' => sprintf('Status %02d', $index),
            'description' => 'Windowed status page',
            'published' => $index % 2 === 0,
        ]);

        $statusPage->monitors()->attach(
            $monitors->slice(($index - 1) * 2, 2)->pluck('id')->values()->all(),
        );
    }

    $this->actingAs($user)
        ->get('/status-pages')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('monitoring/status-pages')
            ->has('pages.data', 6)
            ->where('pages.total', 7)
            ->where('pages.perPage', 6)
            ->where('monitorOptionResults.total', 30)
            ->where('monitorOptionResults.perPage', 24)
            ->has('monitorOptions', 24));

    $this->actingAs($user)
        ->get('/status-pages?monitor_query=Check%2002')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('monitoring/status-pages')
            ->where('monitorOptionQuery', 'Check 02')
            ->where('monitorOptionResults.total', 10));
});

it('paginates maintenance history and windows upcoming maintenance lists', function () {
    $user = User::factory()->premium()->create();
    $monitors = collect(range(1, 30))->map(function (int $index) use ($user) {
        return Monitor::query()->create([
            'user_id' => $user->id,
            'name' => sprintf('Window check %03d', $index),
            'type' => Monitor::TYPE_HTTP,
            'status' => Monitor::STATUS_UP,
            'target' => sprintf('https://example.com/window-%03d', $index),
            'request_method' => 'GET',
            'interval_seconds' => 300,
            'timeout_seconds' => 30,
            'retry_limit' => 2,
            'region' => 'North America',
        ]);
    });

    foreach (range(1, 2) as $index) {
        $window = MaintenanceWindow::query()->create([
            'user_id' => $user->id,
            'title' => sprintf('Active %02d', $index),
            'message' => 'Planned work',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'status' => MaintenanceWindow::STATUS_SCHEDULED,
            'notify_contacts' => false,
        ]);
        $window->monitors()->attach($monitors[$index - 1]->id);
    }

    foreach (range(1, 7) as $index) {
        $window = MaintenanceWindow::query()->create([
            'user_id' => $user->id,
            'title' => sprintf('Upcoming %02d', $index),
            'message' => 'Planned work',
            'starts_at' => now()->addDays($index),
            'ends_at' => now()->addDays($index)->addHour(),
            'status' => MaintenanceWindow::STATUS_SCHEDULED,
            'notify_contacts' => false,
        ]);
        $window->monitors()->attach($monitors[$index + 2]->id);
    }

    foreach (range(1, 12) as $index) {
        $window = MaintenanceWindow::query()->create([
            'user_id' => $user->id,
            'title' => sprintf('Completed %02d', $index),
            'message' => 'Completed work',
            'starts_at' => now()->subDays($index + 2),
            'ends_at' => now()->subDays($index + 2)->addHour(),
            'status' => MaintenanceWindow::STATUS_SCHEDULED,
            'notify_contacts' => false,
        ]);
        $window->monitors()->attach($monitors[$index + 9]->id);
    }

    $this->actingAs($user)
        ->get('/maintenance')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('monitoring/maintenance')
            ->has('active', 2)
            ->has('upcoming', 6)
            ->has('history.data', 10)
            ->where('summary.active', 2)
            ->where('summary.upcoming', 7)
            ->where('summary.history', 12)
            ->where('history.total', 12)
            ->where('monitorOptionResults.total', 30)
            ->where('monitorOptionResults.perPage', 24));

    $this->actingAs($user)
        ->get('/maintenance?history_page=2')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('monitoring/maintenance')
            ->where('history.currentPage', 2)
            ->has('history.data', 2));
});

it('records maintenance windows without sending maintenance emails', function () {
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

    Notification::assertNothingSent();
    $this->assertDatabaseMissing('notification_logs', [
        'monitor_id' => $monitor->id,
        'notification_contact_id' => $contact->id,
        'type' => 'maintenance',
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

it('creates webhook workspace integrations from the integrations section', function () {
    $user = User::factory()->premium()->create();
    $webhookUrl = 'https://workflows.example.test/realuptime-alerts';

    $this->actingAs($user)
        ->post('/workspace-integrations', [
            'provider' => WorkspaceIntegration::PROVIDER_WEBHOOK,
            'name' => 'Ops Workflow',
            'webhook_url' => $webhookUrl,
            'enabled' => true,
            'events' => ['monitor.down', 'monitor.recovered'],
        ])
        ->assertRedirect();

    /** @var WorkspaceIntegration|null $integration */
    $integration = WorkspaceIntegration::query()->first();
    $rawConfig = DB::table('workspace_integrations')->value('config');

    expect($integration)->not->toBeNull();
    expect($integration?->user_id)->toBe($user->id);
    expect($integration?->provider)->toBe(WorkspaceIntegration::PROVIDER_WEBHOOK);
    expect($integration?->status)->toBe(WorkspaceIntegration::STATUS_ACTIVE);
    expect($integration?->name)->toBe('Ops Workflow');
    expect($integration?->config['webhook_url'] ?? null)->toBe($webhookUrl);
    expect($integration?->scopes)->toBe(['monitor.down', 'monitor.recovered']);
    expect($rawConfig)->not->toContain($webhookUrl);
});

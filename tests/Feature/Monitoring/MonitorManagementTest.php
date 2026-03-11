<?php

use App\Models\Capability;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\NotificationContact;
use App\Models\User;
use App\Models\WorkspaceMembership;
use App\Notifications\MonitorAlertNotification;
use App\Services\Monitoring\DomainMetadataResolver;
use App\Services\Monitoring\MonitorPresenter;
use App\Services\Monitoring\MonitorRunner;
use App\Services\Monitoring\TlsMetadataResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('renders the monitors index for authenticated users', function () {
    $user = User::factory()->create();

    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'API health',
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
        'response_time_ms' => 320,
        'http_status_code' => 200,
    ]);

    $this->actingAs($user)
        ->get('/monitors')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('monitors/index')
            ->where('monitors.0.name', 'API health'));
});

it('defers capability insights on the monitors index', function () {
    $user = User::factory()->premium()->create();
    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Checkout API',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com/checkout',
        'request_method' => 'GET',
        'interval_seconds' => 300,
        'timeout_seconds' => 30,
        'retry_limit' => 2,
        'region' => 'North America',
    ]);
    $capability = Capability::query()->create([
        'user_id' => $user->id,
        'name' => 'Checkout',
        'slug' => 'checkout',
    ]);
    $monitor->capabilities()->attach($capability);

    $this->actingAs($user)
        ->get('/monitors')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('monitors/index')
            ->missing('capabilities'))
        ->assertViewHas('page.deferredProps.monitor-insights', fn (array $props) => $props === ['capabilities']);
});

it('keeps the monitors index presenter query count effectively flat as monitor volume grows', function () {
    CarbonImmutable::setTestNow('2026-03-10 12:00:00');

    $seedUser = function (int $monitorCount): User {
        $user = User::factory()->create();

        foreach (range(1, $monitorCount) as $index) {
            $monitor = Monitor::query()->create([
                'user_id' => $user->id,
                'name' => "API {$index}",
                'type' => Monitor::TYPE_HTTP,
                'status' => Monitor::STATUS_UP,
                'target' => "https://example.com/health/{$index}",
                'request_method' => 'GET',
                'interval_seconds' => 300,
                'timeout_seconds' => 30,
                'retry_limit' => 2,
                'region' => 'North America',
                'last_checked_at' => now()->subMinutes($index),
                'last_status_changed_at' => now()->subHours(4),
            ]);

            $monitor->forceFill([
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subMinutes($index),
            ])->save();

            foreach ([now()->subHours(12), now()->subHours(1), now()->subMinutes($index)] as $checkedAt) {
                $monitor->checkResults()->create([
                    'status' => 'up',
                    'checked_at' => $checkedAt,
                    'attempts' => 1,
                    'response_time_ms' => 200 + $index,
                    'http_status_code' => 200,
                ]);
            }
        }

        return $user;
    };

    $singleMonitorUser = $seedUser(1);
    $threeMonitorUser = $seedUser(3);

    DB::flushQueryLog();
    DB::enableQueryLog();

    app(MonitorPresenter::class)->index($singleMonitorUser);

    $singleMonitorQueryCount = count(DB::getQueryLog());

    DB::flushQueryLog();

    app(MonitorPresenter::class)->index($threeMonitorUser);

    $threeMonitorQueryCount = count(DB::getQueryLog());

    DB::disableQueryLog();
    CarbonImmutable::setTestNow();

    expect($threeMonitorQueryCount - $singleMonitorQueryCount)->toBeLessThanOrEqual(1);
    expect($threeMonitorQueryCount)->toBeLessThanOrEqual(10);
});

it('defers monitor history and capability insights on the detail page', function () {
    $user = User::factory()->premium()->create();
    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Website',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com',
        'request_method' => 'GET',
        'interval_seconds' => 300,
        'timeout_seconds' => 30,
        'retry_limit' => 2,
        'region' => 'Europe',
        'last_checked_at' => now()->subMinute(),
        'last_status_changed_at' => now()->subMinutes(5),
    ]);
    $monitor->checkResults()->create([
        'status' => 'up',
        'checked_at' => now()->subMinute(),
        'attempts' => 1,
        'response_time_ms' => 220,
        'http_status_code' => 200,
    ]);
    $capability = Capability::query()->create([
        'user_id' => $user->id,
        'name' => 'Checkout',
        'slug' => 'checkout',
    ]);
    $monitor->capabilities()->attach($capability);

    $this->actingAs($user)
        ->get("/monitors/{$monitor->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('monitors/show')
            ->where('monitor.name', 'Website')
            ->where('monitor.region', 'Europe')
            ->missingAll('monitorHistory', 'monitorCapabilities'))
        ->assertViewHas('page.deferredProps.monitor-insights', fn (array $props) => $props === ['monitorHistory', 'monitorCapabilities']);
});

it('keeps dashboard uptime percentages aligned with downtime duration when healthy ping results are sampled', function () {
    CarbonImmutable::setTestNow('2026-03-10 12:00:00');

    $user = User::factory()->create();

    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Edge ping',
        'type' => Monitor::TYPE_PING,
        'status' => Monitor::STATUS_UP,
        'target' => '1.1.1.1',
        'interval_seconds' => 60,
        'timeout_seconds' => 5,
        'retry_limit' => 0,
        'packet_count' => 1,
        'region' => 'North America',
        'last_checked_at' => now()->subMinute(),
        'last_status_changed_at' => now()->subHours(2),
    ]);
    $monitor->forceFill([
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subMinute(),
    ])->save();

    $monitor->checkResults()->create([
        'status' => 'up',
        'checked_at' => now()->subMinute(),
        'attempts' => 1,
        'response_time_ms' => 18,
        'meta' => ['raw_output' => 'simulated'],
    ]);

    Incident::query()->create([
        'monitor_id' => $monitor->id,
        'started_at' => now()->subHours(3)->subMinutes(15),
        'resolved_at' => now()->subHours(3),
        'duration_seconds' => 15 * 60,
        'type' => Incident::TYPE_DOWNTIME,
        'severity' => Incident::SEVERITY_MAJOR,
        'reason' => 'Packet loss',
    ]);

    $dashboard = app(MonitorPresenter::class)->index($user);
    $detail = app(MonitorPresenter::class)->show($monitor->fresh());

    expect($dashboard['monitors'][0]['uptimePercentLabel'])->toBe('98.96%');
    expect($dashboard['last24Hours']['uptimeLabel'])->toBe('98.96%');
    expect($detail['monitor']['last24Stats']['uptimeLabel'])->toBe('98.96%');
    expect($detail['monitor']['last24Stats']['downtimeLabel'])->toBe('15m down');
    expect(collect($dashboard['monitors'][0]['bars'])->filter(fn (string $bar) => $bar === 'down'))->toHaveCount(1);

    CarbonImmutable::setTestNow();
});

it('stores a new monitor and attaches selected email contacts', function () {
    $user = User::factory()->premium()->create();
    $contact = NotificationContact::query()->create([
        'user_id' => $user->id,
        'name' => 'Ops',
        'email' => 'ops@example.com',
        'enabled' => true,
        'is_primary' => true,
    ]);

    $this->actingAs($user)
        ->post('/monitors', [
            'name' => 'Main website',
            'type' => 'http',
            'target' => 'https://example.com',
            'request_method' => 'GET',
            'interval_seconds' => 300,
            'timeout_seconds' => 30,
            'retry_limit' => 2,
            'follow_redirects' => true,
            'expected_status_code' => 200,
            'region' => 'North America',
            'contact_ids' => [$contact->id],
        ])
        ->assertRedirect();

    $monitor = Monitor::query()->first();

    expect($monitor)->not->toBeNull();
    expect($monitor->notificationContacts()->pluck('notification_contacts.id')->all())->toBe([$contact->id]);
});

it('creates capability mappings from capability labels on paid workspaces', function () {
    $user = User::factory()->premium()->create();

    $this->actingAs($user)
        ->post('/monitors', [
            'name' => 'Primary API',
            'type' => 'http',
            'target' => 'https://example.com',
            'request_method' => 'GET',
            'interval_seconds' => 300,
            'timeout_seconds' => 30,
            'retry_limit' => 2,
            'follow_redirects' => true,
            'expected_status_code' => 200,
            'region' => 'North America',
            'capability_names' => "Sign in\nCheckout",
        ])
        ->assertRedirect();

    $monitor = Monitor::query()->firstOrFail();

    expect(Capability::query()->where('user_id', $user->id)->pluck('name')->sort()->values()->all())
        ->toBe(['Checkout', 'Sign in']);
    expect($monitor->capabilities()->pluck('capabilities.name')->sort()->values()->all())
        ->toBe(['Checkout', 'Sign in']);
});

it('prevents free workspaces from creating more than ten monitors', function () {
    $user = User::factory()->create();

    foreach (range(1, 10) as $index) {
        Monitor::query()->create([
            'user_id' => $user->id,
            'name' => "Monitor {$index}",
            'type' => Monitor::TYPE_HTTP,
            'status' => Monitor::STATUS_UP,
            'target' => "https://example.com/{$index}",
            'request_method' => 'GET',
            'interval_seconds' => 300,
            'timeout_seconds' => 30,
            'retry_limit' => 2,
            'region' => 'North America',
        ]);
    }

    $this->actingAs($user)
        ->get('/monitors/create')
        ->assertRedirect('/settings/membership');

    $this->actingAs($user)
        ->post('/monitors', [
            'name' => 'Overflow monitor',
            'type' => 'http',
            'target' => 'https://example.com/overflow',
            'request_method' => 'GET',
            'interval_seconds' => 300,
            'timeout_seconds' => 30,
            'retry_limit' => 2,
            'follow_redirects' => true,
            'expected_status_code' => 200,
            'region' => 'North America',
        ])
        ->assertRedirect('/settings/membership');

    $this->assertDatabaseMissing('monitors', [
        'user_id' => $user->id,
        'name' => 'Overflow monitor',
    ]);
});

it('prevents free workspaces from saving intervals faster than five minutes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/monitors', [
            'name' => 'Too fast',
            'type' => 'http',
            'target' => 'https://example.com',
            'request_method' => 'GET',
            'interval_seconds' => 30,
            'timeout_seconds' => 30,
            'retry_limit' => 2,
            'follow_redirects' => true,
            'expected_status_code' => 200,
            'region' => 'North America',
        ])
        ->assertSessionHasErrors('interval_seconds');
});

it('allows paid workspaces to save 60 second intervals', function () {
    $user = User::factory()->premium()->create();

    $this->actingAs($user)
        ->post('/monitors', [
            'name' => 'Realtime API',
            'type' => 'http',
            'target' => 'https://example.com',
            'request_method' => 'GET',
            'interval_seconds' => 60,
            'timeout_seconds' => 30,
            'retry_limit' => 2,
            'follow_redirects' => true,
            'expected_status_code' => 200,
            'region' => 'North America',
        ])
        ->assertRedirect();

    expect(Monitor::query()->first()?->interval_seconds)->toBe(60);
});

it('locks free workspaces to the standard check profile', function () {
    $user = User::factory()->create();
    $contact = NotificationContact::query()->create([
        'user_id' => $user->id,
        'name' => 'Ops',
        'email' => 'ops@example.com',
        'enabled' => true,
        'is_primary' => true,
    ]);

    $this->actingAs($user)
        ->post('/monitors', [
            'name' => 'Free website',
            'type' => 'http',
            'target' => 'https://example.com/free',
            'request_method' => 'POST',
            'interval_seconds' => 300,
            'timeout_seconds' => 9,
            'retry_limit' => 5,
            'follow_redirects' => false,
            'expected_status_code' => 204,
            'latency_threshold_ms' => 9999,
            'degraded_consecutive_checks' => 7,
            'critical_alert_after_minutes' => 120,
            'region' => 'Europe',
            'contact_ids' => [$contact->id],
        ])
        ->assertRedirect();

    $monitor = Monitor::query()->where('user_id', $user->id)->where('name', 'Free website')->first();

    expect($monitor)->not->toBeNull();
    expect($monitor->request_method)->toBe('GET');
    expect($monitor->timeout_seconds)->toBe(30);
    expect($monitor->retry_limit)->toBe(2);
    expect($monitor->follow_redirects)->toBeTrue();
    expect($monitor->expected_status_code)->toBe(200);
    expect($monitor->region)->toBe('North America');
    expect($monitor->critical_alert_after_minutes)->toBe(30);
    expect($monitor->notificationContacts()->count())->toBe(1);
});

it('runs port monitors against a tcp target', function () {
    $user = User::factory()->premium()->create();
    $socket = tmpfile();

    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Database port',
        'type' => Monitor::TYPE_PORT,
        'status' => Monitor::STATUS_UP,
        'target' => '127.0.0.1:5432',
        'interval_seconds' => 30,
        'timeout_seconds' => 5,
        'retry_limit' => 0,
        'latency_threshold_ms' => 5000,
        'degraded_consecutive_checks' => 3,
        'critical_alert_after_minutes' => 30,
        'region' => 'North America',
        'last_status_changed_at' => now()->subMinutes(10),
    ]);

    $this->partialMock(MonitorRunner::class, function ($mock) use ($socket) {
        $mock->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('openTcpSocket')
            ->once()
            ->with('127.0.0.1', 5432, 5, \Mockery::any(), \Mockery::any())
            ->andReturn($socket);
    });

    $outcome = app(MonitorRunner::class)->runMonitor($monitor->fresh(['notificationContacts', 'user']));

    expect($outcome->status)->toBe('up');
    expect($monitor->fresh()->last_response_time_ms)->not->toBeNull();
});

it('defaults ping monitors to a single icmp packet when no packet count is provided', function () {
    $user = User::factory()->premium()->create();

    $this->actingAs($user)
        ->post('/monitors', [
            'name' => 'Single packet ping',
            'type' => 'ping',
            'target' => '1.1.1.1',
            'interval_seconds' => 60,
            'timeout_seconds' => 5,
            'retry_limit' => 1,
            'region' => 'North America',
        ])
        ->assertRedirect();

    expect(Monitor::query()->first()?->packet_count)->toBe(1);
});

it('samples healthy ping results instead of storing every successful run', function () {
    $user = User::factory()->premium()->create();
    $startedAt = CarbonImmutable::parse('2026-03-09 12:00:00');

    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Sampled ping target',
        'type' => Monitor::TYPE_PING,
        'status' => Monitor::STATUS_UP,
        'target' => '1.1.1.1',
        'interval_seconds' => 60,
        'timeout_seconds' => 5,
        'retry_limit' => 0,
        'packet_count' => 1,
        'region' => 'North America',
        'last_status_changed_at' => $startedAt->subMinutes(10),
    ]);

    Process::fake([
        '*' => Process::result('round-trip min/avg/max/stddev = 8.0/12.0/16.0/1.0 ms'),
    ]);

    $runner = app(MonitorRunner::class);

    $runner->runMonitor($monitor->fresh(['notificationContacts', 'user']), $startedAt);
    $runner->runMonitor($monitor->fresh(['notificationContacts', 'user']), $startedAt->addMinute());
    $runner->runMonitor($monitor->fresh(['notificationContacts', 'user']), $startedAt->addMinutes(2));

    $monitor = $monitor->fresh();

    expect($monitor->checkResults()->count())->toBe(1);
    expect($monitor->last_checked_at?->equalTo($startedAt->addMinutes(2)))->toBeTrue();
    expect($monitor->last_result_stored_at?->equalTo($startedAt))->toBeTrue();
    expect($monitor->next_check_at?->equalTo($startedAt->addMinutes(3)))->toBeTrue();
});

it('stores downtime webhook urls for paid workspaces and blocks them for free workspaces', function () {
    $freeUser = User::factory()->create();

    $this->actingAs($freeUser)
        ->post('/monitors', [
            'name' => 'Free webhook monitor',
            'type' => 'http',
            'target' => 'https://example.com/free-webhook',
            'request_method' => 'GET',
            'interval_seconds' => 300,
            'timeout_seconds' => 30,
            'retry_limit' => 2,
            'follow_redirects' => true,
            'expected_status_code' => 200,
            'region' => 'North America',
            'downtime_webhook_urls' => "https://hooks.example.com/free\nhttps://ops.example.com/free",
        ])
        ->assertSessionHasErrors('downtime_webhook_urls');

    $premiumUser = User::factory()->premium()->create();

    $this->actingAs($premiumUser)
        ->post('/monitors', [
            'name' => 'Premium webhook monitor',
            'type' => 'http',
            'target' => 'https://example.com/premium-webhook',
            'request_method' => 'GET',
            'interval_seconds' => 60,
            'timeout_seconds' => 30,
            'retry_limit' => 2,
            'follow_redirects' => true,
            'expected_status_code' => 200,
            'region' => 'North America',
            'downtime_webhook_urls' => "https://hooks.example.com/premium\nhttps://ops.example.com/premium",
        ])
        ->assertRedirect();

    $monitor = Monitor::query()
        ->where('user_id', $premiumUser->id)
        ->where('name', 'Premium webhook monitor')
        ->first();

    expect($monitor?->downtime_webhook_urls)->toBe([
        'https://hooks.example.com/premium',
        'https://ops.example.com/premium',
    ]);
});

it('requires platform admins to respect workspace monitor quotas and interval limits', function () {
    $admin = User::factory()->admin()->create();
    $workspaceOwner = User::factory()->create();

    WorkspaceMembership::query()->create([
        'owner_user_id' => $workspaceOwner->id,
        'member_user_id' => $admin->id,
        'invited_by_user_id' => $workspaceOwner->id,
        'invited_email' => $admin->email,
        'token' => (string) Str::uuid(),
        'invited_at' => now(),
        'accepted_at' => now(),
    ]);

    Monitor::query()->create([
        'user_id' => $workspaceOwner->id,
        'name' => 'Existing workspace monitor',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com/existing',
        'request_method' => 'GET',
        'interval_seconds' => 300,
        'timeout_seconds' => 30,
        'retry_limit' => 2,
        'region' => 'North America',
    ]);

    $this->actingAs($admin)
        ->withSession(['workspace_owner_id' => $workspaceOwner->id])
        ->from('/monitors/create')
        ->post('/monitors', [
            'name' => 'Admin fast monitor',
            'type' => 'http',
            'target' => 'https://example.com/admin-fast',
            'request_method' => 'GET',
            'interval_seconds' => 30,
            'timeout_seconds' => 30,
            'retry_limit' => 2,
            'follow_redirects' => true,
            'expected_status_code' => 200,
            'region' => 'North America',
        ])
        ->assertRedirect('/monitors/create')
        ->assertSessionHasErrors('interval_seconds');

    $this->assertDatabaseMissing('monitors', [
        'user_id' => $workspaceOwner->id,
        'name' => 'Admin fast monitor',
    ]);

    foreach (range(1, 9) as $index) {
        Monitor::query()->create([
            'user_id' => $workspaceOwner->id,
            'name' => "Workspace Monitor overflow {$index}",
            'type' => Monitor::TYPE_HTTP,
            'status' => Monitor::STATUS_UP,
            'target' => "https://example.com/{$index}",
            'request_method' => 'GET',
            'interval_seconds' => 300,
            'timeout_seconds' => 30,
            'retry_limit' => 2,
            'region' => 'North America',
        ]);
    }

    $this->actingAs($admin)
        ->withSession(['workspace_owner_id' => $workspaceOwner->id])
        ->get('/monitors/create')
        ->assertRedirect('/monitors');

    $this->actingAs($admin)
        ->withSession(['workspace_owner_id' => $workspaceOwner->id])
        ->post('/monitors', [
            'name' => 'Admin quota monitor',
            'type' => 'http',
            'target' => 'https://example.com/admin-quota',
            'request_method' => 'GET',
            'interval_seconds' => 300,
            'timeout_seconds' => 30,
            'retry_limit' => 2,
            'follow_redirects' => true,
            'expected_status_code' => 200,
            'region' => 'North America',
        ])
        ->assertRedirect('/monitors');

    $this->assertDatabaseMissing('monitors', [
        'user_id' => $workspaceOwner->id,
        'name' => 'Admin quota monitor',
    ]);
});

it('renders the workspace plan on the monitors index page for shared free workspaces', function () {
    $admin = User::factory()->admin()->create();
    $workspaceOwner = User::factory()->create();

    WorkspaceMembership::query()->create([
        'owner_user_id' => $workspaceOwner->id,
        'member_user_id' => $admin->id,
        'invited_by_user_id' => $workspaceOwner->id,
        'invited_email' => $admin->email,
        'token' => (string) Str::uuid(),
        'invited_at' => now(),
        'accepted_at' => now(),
    ]);

    foreach (range(1, 10) as $index) {
        Monitor::query()->create([
            'user_id' => $workspaceOwner->id,
            'name' => "Workspace Monitor {$index}",
            'type' => Monitor::TYPE_HTTP,
            'status' => Monitor::STATUS_UP,
            'target' => "https://example.com/{$index}",
            'request_method' => 'GET',
            'interval_seconds' => 300,
            'timeout_seconds' => 30,
            'retry_limit' => 2,
            'region' => 'North America',
        ]);
    }

    $this->actingAs($admin)
        ->withSession(['workspace_owner_id' => $workspaceOwner->id])
        ->get('/monitors')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('monitors/index')
            ->where('summary.canCreate', false)
            ->where('summary.usageLabel', 'Using 10 of 10 monitors on the Free plan.'));
});

it('clamps free workspace execution cadence to the plan minimum interval', function () {
    $checkedAt = CarbonImmutable::parse('2026-03-07 12:00:00');
    $user = User::factory()->create();
    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Legacy fast monitor',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com/health',
        'request_method' => 'GET',
        'interval_seconds' => 30,
        'timeout_seconds' => 30,
        'retry_limit' => 0,
        'region' => 'North America',
        'last_status_changed_at' => $checkedAt->subMinutes(10),
    ]);

    Http::fake([
        'https://example.com/health' => Http::response('ok', 200),
    ]);

    app()->instance(DomainMetadataResolver::class, new class extends DomainMetadataResolver
    {
        public function resolve(?string $host, int $timeoutSeconds = 10): ?array
        {
            return null;
        }
    });

    app()->instance(TlsMetadataResolver::class, new class extends TlsMetadataResolver
    {
        public function resolve(string $host, int $timeoutSeconds = 10): ?array
        {
            return null;
        }
    });

    app(MonitorRunner::class)->runMonitor($monitor->fresh(['notificationContacts', 'user']), $checkedAt);

    expect($monitor->fresh()->next_check_at?->equalTo($checkedAt->addMinutes(5)))->toBeTrue();
});

it('ignores legacy admin monitor interval overrides during execution', function () {
    $checkedAt = CarbonImmutable::parse('2026-03-07 12:00:00');
    $user = User::factory()->create();
    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Admin-managed fast monitor',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com/health',
        'request_method' => 'GET',
        'interval_seconds' => 30,
        'admin_interval_override' => true,
        'timeout_seconds' => 30,
        'retry_limit' => 0,
        'region' => 'North America',
        'last_status_changed_at' => $checkedAt->subMinutes(10),
    ]);

    Http::fake([
        'https://example.com/health' => Http::response('ok', 200),
    ]);

    app()->instance(DomainMetadataResolver::class, new class extends DomainMetadataResolver
    {
        public function resolve(?string $host, int $timeoutSeconds = 10): ?array
        {
            return null;
        }
    });

    app()->instance(TlsMetadataResolver::class, new class extends TlsMetadataResolver
    {
        public function resolve(string $host, int $timeoutSeconds = 10): ?array
        {
            return null;
        }
    });

    app(MonitorRunner::class)->runMonitor($monitor->fresh(['notificationContacts', 'user']), $checkedAt);

    expect($monitor->fresh()->next_check_at?->equalTo($checkedAt->addMinutes(5)))->toBeTrue();
});

it('runs synthetic transaction monitors across multiple http steps', function () {
    $user = User::factory()->create();

    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Synthetic login flow',
        'type' => Monitor::TYPE_SYNTHETIC,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com',
        'interval_seconds' => 300,
        'timeout_seconds' => 30,
        'retry_limit' => 0,
        'follow_redirects' => true,
        'custom_headers' => ['X-Monitor' => 'RealUptime'],
        'latency_threshold_ms' => 5000,
        'degraded_consecutive_checks' => 3,
        'critical_alert_after_minutes' => 30,
        'region' => 'North America',
        'last_status_changed_at' => now()->subMinutes(10),
        'synthetic_steps' => [
            [
                'name' => 'Load login page',
                'method' => 'GET',
                'url' => '/login',
                'expected_status_code' => 200,
                'expected_keyword' => 'Sign in',
            ],
            [
                'name' => 'Submit credentials',
                'method' => 'POST',
                'url' => '/session',
                'body' => [
                    'email' => 'demo@example.com',
                    'password' => 'secret',
                ],
                'expected_status_code' => 200,
                'expected_keyword' => 'Dashboard',
            ],
        ],
    ]);

    Http::fake([
        'https://example.com/login' => Http::response('<html>Sign in</html>', 200),
        'https://example.com/session' => Http::response('<html>Dashboard</html>', 200),
    ]);

    app()->instance(DomainMetadataResolver::class, new class extends DomainMetadataResolver
    {
        public function resolve(?string $host, int $timeoutSeconds = 10): ?array
        {
            return null;
        }
    });

    app()->instance(TlsMetadataResolver::class, new class extends TlsMetadataResolver
    {
        public function resolve(string $host, int $timeoutSeconds = 10): ?array
        {
            return null;
        }
    });

    $outcome = app(MonitorRunner::class)->runMonitor($monitor->fresh(['notificationContacts', 'user']));
    $checkResult = $monitor->fresh()->checkResults()->latest('checked_at')->first();

    expect($outcome->status)->toBe('up');
    expect($checkResult)->not->toBeNull();
    expect($checkResult->status)->toBe('up');
    expect($checkResult->http_status_code)->toBe(200);
    expect(data_get($checkResult->meta, 'transaction_step_count'))->toBe(2);
    expect(data_get($checkResult->meta, 'transaction_steps.0.name'))->toBe('Load login page');
    expect(data_get($checkResult->meta, 'transaction_steps.1.name'))->toBe('Submit credentials');
});

it('sends a test notification and records it in the notification log', function () {
    Notification::fake();

    $user = User::factory()->create();
    $contact = NotificationContact::query()->create([
        'user_id' => $user->id,
        'name' => 'Ops',
        'email' => 'ops@example.com',
        'enabled' => true,
        'is_primary' => true,
    ]);
    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Main website',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com',
        'request_method' => 'GET',
        'interval_seconds' => 300,
        'timeout_seconds' => 30,
        'retry_limit' => 2,
        'region' => 'North America',
    ]);
    $monitor->notificationContacts()->attach($contact);

    $this->actingAs($user)
        ->post("/monitors/{$monitor->id}/test-notification")
        ->assertRedirect();

    Notification::assertSentOnDemand(MonitorAlertNotification::class);

    $this->assertDatabaseHas('notification_logs', [
        'monitor_id' => $monitor->id,
        'notification_contact_id' => $contact->id,
        'type' => 'test',
        'status' => 'sent',
    ]);
});

it('filters response time data by the requested range on the monitor detail page', function () {
    $user = User::factory()->create();

    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'API latency',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com/health',
        'request_method' => 'GET',
        'interval_seconds' => 300,
        'timeout_seconds' => 30,
        'retry_limit' => 2,
        'region' => 'North America',
        'last_checked_at' => now()->subMinute(),
        'last_status_changed_at' => now()->subHours(2),
    ]);

    $monitor->checkResults()->createMany([
        [
            'status' => 'up',
            'checked_at' => now()->subDays(5),
            'attempts' => 1,
            'response_time_ms' => 110,
            'http_status_code' => 200,
        ],
        [
            'status' => 'up',
            'checked_at' => now()->subDays(1),
            'attempts' => 1,
            'response_time_ms' => 210,
            'http_status_code' => 200,
        ],
        [
            'status' => 'down',
            'checked_at' => now()->subHours(3),
            'attempts' => 1,
            'http_status_code' => 500,
            'error_type' => 'invalid_status',
            'error_message' => 'Expected HTTP 200 but received 500.',
        ],
        [
            'status' => 'up',
            'checked_at' => now()->subDays(45),
            'attempts' => 1,
            'response_time_ms' => 910,
            'http_status_code' => 200,
        ],
    ]);

    $props = app(MonitorPresenter::class)->show($monitor->fresh(), 'week');

    expect($props['monitor']['responseTimeRange'])->toBe('week');
    expect($props['monitor']['responseTimeRangeLabel'])->toBe('Last week');
    expect($props['monitor']['last7Bars'])->toHaveCount(28);
    expect($props['monitor']['responseTimeStats']['minimum'])->toBe(110);
    expect($props['monitor']['responseTimeStats']['median'])->toBe(160);
    expect($props['monitor']['responseTimeStats']['maximum'])->toBe(210);
    expect($props['monitor']['responseTimeStats']['average'])->toBe(160);
    expect($props['monitor']['responseTimeStats']['p95'])->toBe(210);
    expect($props['monitor']['responseTimeStats']['downtimeLabel'])->toBe('5m down');
    expect($props['monitor']['responseTimeSignals']['sampleCount'])->toBe(3);
    expect($props['monitor']['responseTimeSignals']['failedChecks'])->toBe(1);
    expect($props['monitor']['responseTimeSignals']['slowChecks'])->toBe(0);
    expect($props['monitor']['responseTimeSignals']['successRate'])->toBe(66.67);
    expect($props['monitor']['responseTimeRangeOptions'])->toHaveCount(6);
    expect($props['monitor']['responseTimeChart'])->toHaveCount(3);
    expect($props['monitor']['responseTimeChart'][2]['status'])->toBe('down');
});

it('defaults the latency profile range to the last day', function () {
    $user = User::factory()->create();

    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'API latency',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com/health',
        'request_method' => 'GET',
        'interval_seconds' => 300,
        'timeout_seconds' => 30,
        'retry_limit' => 2,
        'region' => 'North America',
        'last_checked_at' => now()->subMinute(),
        'last_status_changed_at' => now()->subHours(2),
    ]);

    $props = app(MonitorPresenter::class)->show($monitor->fresh());

    expect($props['monitor']['responseTimeRange'])->toBe('day');
    expect($props['monitor']['responseTimeRangeLabel'])->toBe('Last day');
});

it('keeps the default monitor detail presenter query count bounded', function () {
    CarbonImmutable::setTestNow('2026-03-10 12:00:00');

    $user = User::factory()->create();

    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'API latency',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com/health',
        'request_method' => 'GET',
        'interval_seconds' => 300,
        'timeout_seconds' => 30,
        'retry_limit' => 2,
        'region' => 'North America',
        'last_checked_at' => now()->subMinute(),
        'last_status_changed_at' => now()->subHours(2),
    ]);

    $monitor->forceFill([
        'created_at' => now()->subDays(45),
        'updated_at' => now()->subMinute(),
    ])->save();

    foreach ([now()->subDays(6), now()->subDay(), now()->subHour(), now()->subMinute()] as $checkedAt) {
        $monitor->checkResults()->create([
            'status' => 'up',
            'checked_at' => $checkedAt,
            'attempts' => 1,
            'response_time_ms' => 180,
            'http_status_code' => 200,
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    app(MonitorPresenter::class)->show($monitor->fresh());

    $queryCount = count(DB::getQueryLog());

    DB::disableQueryLog();
    CarbonImmutable::setTestNow();

    expect($queryCount)->toBeLessThanOrEqual(16);
});

it('supports changing response time granularity on the monitor detail page', function () {
    $user = User::factory()->create();
    $base = now()->startOfDay();

    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'API latency',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com/health',
        'request_method' => 'GET',
        'interval_seconds' => 300,
        'timeout_seconds' => 30,
        'retry_limit' => 2,
        'region' => 'North America',
        'last_checked_at' => now()->subMinute(),
        'last_status_changed_at' => now()->subHours(2),
    ]);

    $monitor->checkResults()->createMany([
        [
            'status' => 'up',
            'checked_at' => $base->copy()->subDays(2)->addHour(),
            'attempts' => 1,
            'response_time_ms' => 110,
            'http_status_code' => 200,
        ],
        [
            'status' => 'up',
            'checked_at' => $base->copy()->subDays(2)->addHours(10),
            'attempts' => 1,
            'response_time_ms' => 210,
            'http_status_code' => 200,
        ],
        [
            'status' => 'down',
            'checked_at' => $base->copy()->subDay()->addHours(3),
            'attempts' => 1,
            'http_status_code' => 500,
            'error_type' => 'invalid_status',
            'error_message' => 'Expected HTTP 200 but received 500.',
        ],
        [
            'status' => 'up',
            'checked_at' => $base->copy()->addHours(8),
            'attempts' => 1,
            'response_time_ms' => 410,
            'http_status_code' => 200,
        ],
    ]);

    $props = app(MonitorPresenter::class)->show($monitor->fresh(), 'week', '1d');

    expect($props['monitor']['responseTimeRange'])->toBe('week');
    expect($props['monitor']['responseTimeGranularity'])->toBe('1d');
    expect($props['monitor']['responseTimeGranularityLabel'])->toBe('1 day');
    expect($props['monitor']['responseTimeGranularityOptions'])->toBe([
        ['value' => 'auto', 'label' => 'Auto'],
        ['value' => '1h', 'label' => '1 hour'],
        ['value' => '6h', 'label' => '6 hours'],
        ['value' => '1d', 'label' => '1 day'],
    ]);
    expect($props['monitor']['responseTimeChart'])->toHaveCount(3);
    expect($props['monitor']['responseTimeChart'][0]['value'])->toBe(160);
    expect($props['monitor']['responseTimeChart'][1]['status'])->toBe('down');
    expect($props['monitor']['responseTimeChart'][2]['value'])->toBe(410);
    expect($props['monitor']['responseTimeStats']['median'])->toBe(210);
    expect($props['monitor']['responseTimeSignals']['sampleCount'])->toBe(4);
    expect($props['monitor']['responseTimeSignals']['failedChecks'])->toBe(1);
    expect($props['monitor']['responseTimeSignals']['slowChecks'])->toBe(0);
    expect($props['monitor']['responseTimeSignals']['successRate'])->toBe(75.0);
});

it('offers a 30 second response time granularity for the last hour view', function () {
    $user = User::factory()->create();

    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Realtime API latency',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com/health',
        'request_method' => 'GET',
        'interval_seconds' => 30,
        'timeout_seconds' => 30,
        'retry_limit' => 2,
        'region' => 'North America',
        'last_checked_at' => now()->subSeconds(20),
        'last_status_changed_at' => now()->subHours(2),
    ]);

    $monitor->checkResults()->createMany([
        [
            'status' => 'up',
            'checked_at' => now()->subSeconds(50),
            'attempts' => 1,
            'response_time_ms' => 140,
            'http_status_code' => 200,
        ],
        [
            'status' => 'up',
            'checked_at' => now()->subSeconds(20),
            'attempts' => 1,
            'response_time_ms' => 180,
            'http_status_code' => 200,
        ],
    ]);

    $props = app(MonitorPresenter::class)->show($monitor->fresh(), 'hour', '30s');

    expect($props['monitor']['responseTimeGranularity'])->toBe('30s');
    expect($props['monitor']['responseTimeGranularityLabel'])->toBe('30 seconds');
    expect($props['monitor']['responseTimeGranularityOptions'])->toBe([
        ['value' => 'auto', 'label' => 'Auto'],
        ['value' => '30s', 'label' => '30 seconds'],
        ['value' => '1m', 'label' => '1 minute'],
        ['value' => '5m', 'label' => '5 minutes'],
        ['value' => '15m', 'label' => '15 minutes'],
    ]);
});

it('renders region and domain ssl metadata on the monitor detail page', function () {
    $user = User::factory()->create();

    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Website',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com',
        'request_method' => 'GET',
        'interval_seconds' => 300,
        'timeout_seconds' => 30,
        'retry_limit' => 2,
        'region' => 'Europe',
        'domain_expires_at' => now()->addMonths(6),
        'domain_registrar' => 'Cloudflare Registrar',
        'ssl_expires_at' => now()->addDays(90),
        'ssl_issuer' => 'Let’s Encrypt',
    ]);

    $this->actingAs($user)
        ->get("/monitors/{$monitor->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('monitors/show')
            ->where('monitor.region', 'Europe')
            ->where('monitor.domainSsl.domainRegistrar', 'Cloudflare Registrar')
            ->where('monitor.domainSsl.issuer', 'Let’s Encrypt'));
});

it('resolves domain metadata through rdap', function () {
    Http::fake([
        'https://data.iana.org/rdap/dns.json' => Http::response([
            'services' => [
                [['uk'], ['https://rdap.nic.uk/']],
            ],
        ]),
        'https://rdap.nic.uk/domain/example.co.uk' => Http::response([
            'events' => [
                [
                    'eventAction' => 'expiration',
                    'eventDate' => '2027-07-01T00:00:00Z',
                ],
            ],
            'entities' => [
                [
                    'roles' => ['registrar'],
                    'vcardArray' => [
                        'vcard',
                        [
                            ['fn', [], 'text', 'Test Registrar Ltd'],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $metadata = app(DomainMetadataResolver::class)->resolve('status.api.example.co.uk');

    expect($metadata)->not->toBeNull();
    expect($metadata['domain'])->toBe('example.co.uk');
    expect($metadata['registrar'])->toBe('Test Registrar Ltd');
    expect($metadata['expires_at']?->toDateString())->toBe('2027-07-01');
});

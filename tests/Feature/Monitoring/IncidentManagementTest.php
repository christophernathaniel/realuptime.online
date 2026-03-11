<?php

use App\Models\Capability;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\NotificationContact;
use App\Models\NotificationLog;
use App\Models\User;
use App\Models\WorkspaceIntegration;
use App\Services\Monitoring\DomainMetadataResolver;
use App\Services\Monitoring\MonitorMetadataRefresher;
use App\Services\Monitoring\MonitorRunner;
use App\Services\Monitoring\TlsMetadataResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;

uses(RefreshDatabase::class);

it('renders affected capabilities on the incident detail page', function () {
    $user = User::factory()->premium()->create();
    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Primary API',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_DOWN,
        'target' => 'https://example.com/health',
        'request_method' => 'GET',
        'interval_seconds' => 300,
        'timeout_seconds' => 30,
        'retry_limit' => 0,
        'expected_status_code' => 200,
        'region' => 'North America',
    ]);

    $capability = Capability::query()->create([
        'user_id' => $user->id,
        'name' => 'Checkout',
        'slug' => 'checkout',
    ]);
    $monitor->capabilities()->attach($capability->id);

    $incident = Incident::query()->create([
        'monitor_id' => $monitor->id,
        'started_at' => now()->subMinutes(10),
        'type' => Incident::TYPE_DOWNTIME,
        'severity' => Incident::SEVERITY_MAJOR,
        'reason' => 'Primary API is unavailable.',
    ]);

    $this->actingAs($user)
        ->get("/incidents/{$incident->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('incidents/show')
            ->where('incident.capabilities.0.name', 'Checkout'));
});

it('escalates downtime incidents to critical after the configured threshold', function () {
    Notification::fake();
    Http::fake([
        'https://example.com/health' => Http::response('error', 500),
    ]);

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
        'target' => 'https://example.com/health',
        'request_method' => 'GET',
        'interval_seconds' => 300,
        'timeout_seconds' => 30,
        'retry_limit' => 0,
        'expected_status_code' => 200,
        'critical_alert_after_minutes' => 30,
        'region' => 'North America',
    ]);
    $monitor->notificationContacts()->attach($contact);

    $runner = app(MonitorRunner::class);
    $startedAt = CarbonImmutable::parse('2026-03-06 10:00:00');

    $runner->runMonitor($monitor->fresh(['notificationContacts', 'user']), $startedAt);
    $runner->runMonitor($monitor->fresh(['notificationContacts', 'user']), $startedAt->addMinutes(31));

    $incident = Incident::query()->where('monitor_id', $monitor->id)->latest('started_at')->first();

    expect($incident)->not->toBeNull();
    expect($incident->severity)->toBe(Incident::SEVERITY_CRITICAL);
    expect($incident->critical_alert_sent_at)->not->toBeNull();

    $this->assertDatabaseHas('notification_logs', [
        'monitor_id' => $monitor->id,
        'type' => 'down',
        'status' => 'sent',
    ]);
});

it('opens degraded performance incidents after sustained slow checks and resolves them when latency recovers', function () {
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
        'name' => 'Latency host',
        'type' => Monitor::TYPE_PING,
        'status' => Monitor::STATUS_UP,
        'target' => 'example.com',
        'interval_seconds' => 300,
        'timeout_seconds' => 30,
        'retry_limit' => 0,
        'packet_count' => 3,
        'latency_threshold_ms' => 100,
        'degraded_consecutive_checks' => 2,
        'region' => 'North America',
    ]);
    $monitor->notificationContacts()->attach($contact);

    Process::fake([
        '*' => Process::sequence([
            Process::result('round-trip min/avg/max/stddev = 10.0/180.0/220.0/1.0 ms'),
            Process::result('round-trip min/avg/max/stddev = 12.0/190.0/230.0/1.0 ms'),
            Process::result('round-trip min/avg/max/stddev = 5.0/25.0/40.0/1.0 ms'),
        ]),
    ]);

    $runner = app(MonitorRunner::class);
    $startedAt = CarbonImmutable::parse('2026-03-06 11:00:00');

    $runner->runMonitor($monitor->fresh(['notificationContacts', 'user']), $startedAt);
    $runner->runMonitor($monitor->fresh(['notificationContacts', 'user']), $startedAt->addMinutes(5));

    $incident = Incident::query()
        ->where('monitor_id', $monitor->id)
        ->where('type', Incident::TYPE_DEGRADED_PERFORMANCE)
        ->whereNull('resolved_at')
        ->first();

    expect($incident)->not->toBeNull();
    expect($incident->severity)->toBe(Incident::SEVERITY_WARNING);

    $runner->runMonitor($monitor->fresh(['notificationContacts', 'user']), $startedAt->addMinutes(10));

    $resolvedIncident = $incident->fresh();

    expect($resolvedIncident?->resolved_at)->not->toBeNull();
});

it('opens ssl and domain expiry incidents when expiry thresholds are breached', function () {
    Notification::fake();
    Http::fake([
        'https://example.com' => Http::response('ok', 200),
    ]);

    app()->instance(DomainMetadataResolver::class, new class extends DomainMetadataResolver
    {
        public function resolve(?string $host, int $timeoutSeconds = 10): ?array
        {
            return [
                'domain' => 'example.com',
                'expires_at' => CarbonImmutable::parse('2026-03-20 00:00:00'),
                'registrar' => 'Test Registrar',
            ];
        }
    });

    app()->instance(TlsMetadataResolver::class, new class extends TlsMetadataResolver
    {
        public function resolve(string $host, int $timeoutSeconds = 10): ?array
        {
            return [
                'expires_at' => CarbonImmutable::parse('2026-03-18 00:00:00'),
                'issuer' => 'Test CA',
            ];
        }
    });

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
        'name' => 'Expiry website',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com',
        'request_method' => 'GET',
        'interval_seconds' => 300,
        'timeout_seconds' => 30,
        'retry_limit' => 0,
        'expected_status_code' => 200,
        'ssl_threshold_days' => 30,
        'domain_threshold_days' => 30,
        'region' => 'North America',
    ]);
    $monitor->notificationContacts()->attach($contact);

    $checkedAt = CarbonImmutable::parse('2026-03-06 12:00:00');

    app(MonitorRunner::class)->runMonitor(
        $monitor->fresh(['notificationContacts', 'user']),
        $checkedAt,
    );

    app(MonitorMetadataRefresher::class)->refresh($monitor->fresh(), $checkedAt);

    app(MonitorRunner::class)->runMonitor(
        $monitor->fresh(['notificationContacts', 'user']),
        $checkedAt->addMinutes(5),
    );

    $this->assertDatabaseHas('incidents', [
        'monitor_id' => $monitor->id,
        'type' => Incident::TYPE_SSL_EXPIRY,
        'severity' => Incident::SEVERITY_WARNING,
    ]);

    $this->assertDatabaseHas('incidents', [
        'monitor_id' => $monitor->id,
        'type' => Incident::TYPE_DOMAIN_EXPIRY,
        'severity' => Incident::SEVERITY_WARNING,
    ]);
});

it('reuses a single open incident snapshot while resolving monitor incidents', function () {
    Http::fake([
        'https://example.com/health' => Http::response('ok', 200),
    ]);

    $user = User::factory()->create();
    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Main website',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_DOWN,
        'target' => 'https://example.com/health',
        'request_method' => 'GET',
        'interval_seconds' => 300,
        'timeout_seconds' => 30,
        'retry_limit' => 0,
        'expected_status_code' => 200,
        'region' => 'North America',
    ]);

    foreach ([
        Incident::TYPE_DOWNTIME,
        Incident::TYPE_DEGRADED_PERFORMANCE,
        Incident::TYPE_SSL_EXPIRY,
        Incident::TYPE_DOMAIN_EXPIRY,
    ] as $type) {
        Incident::query()->create([
            'monitor_id' => $monitor->id,
            'started_at' => now()->subHour(),
            'type' => $type,
            'severity' => Incident::SEVERITY_WARNING,
            'reason' => sprintf('Open %s incident.', $type),
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    app(MonitorRunner::class)->runMonitor(
        $monitor->fresh(['notificationContacts', 'user']),
        CarbonImmutable::parse('2026-03-11 10:00:00'),
    );

    $openIncidentLookupCount = collect(DB::getQueryLog())
        ->filter(function (array $query): bool {
            $sql = strtolower($query['query']);

            return str_starts_with($sql, 'select')
                && str_contains($sql, 'incidents')
                && str_contains($sql, 'monitor_id')
                && str_contains($sql, 'resolved_at');
        })
        ->count();

    expect($openIncidentLookupCount)->toBe(1);
});

it('sends downtime webhook payloads for paid workspaces', function () {
    Notification::fake();
    Http::fake([
        'https://example.com/webhook-health' => Http::response('error', 500),
        'https://hooks.example.com/realuptime' => Http::response(['received' => true], 202),
    ]);

    $user = User::factory()->premium()->create();
    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Webhook site',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com/webhook-health',
        'request_method' => 'GET',
        'interval_seconds' => 30,
        'timeout_seconds' => 30,
        'retry_limit' => 0,
        'expected_status_code' => 200,
        'downtime_webhook_urls' => ['https://hooks.example.com/realuptime'],
        'region' => 'North America',
    ]);

    app(MonitorRunner::class)->runMonitor(
        $monitor->fresh(['notificationContacts', 'user']),
        CarbonImmutable::parse('2026-03-06 12:30:00'),
    );

    Http::assertSent(fn ($request) => $request->url() === 'https://hooks.example.com/realuptime'
        && data_get($request->data(), 'event') === 'monitor.down'
        && data_get($request->data(), 'monitor.id') === $monitor->id
        && data_get($request->data(), 'incident.reason') === 'Expected HTTP 200 but received 500.');

    $this->assertDatabaseHas('notification_logs', [
        'monitor_id' => $monitor->id,
        'channel' => 'webhook',
        'type' => 'down',
        'status' => 'sent',
    ]);
});

it('sends workflow-friendly downtime payloads for webhook integrations', function () {
    Notification::fake();
    Http::fake([
        'https://example.com/workflow-health' => Http::response('error', 500),
        'https://workflows.example.test/realuptime-alerts' => Http::response('ok', 200),
    ]);

    $user = User::factory()->premium()->create();
    $integration = WorkspaceIntegration::query()->create([
        'user_id' => $user->id,
        'provider' => WorkspaceIntegration::PROVIDER_WEBHOOK,
        'name' => 'Ops Workflow',
        'status' => WorkspaceIntegration::STATUS_ACTIVE,
        'config' => [
            'webhook_url' => 'https://workflows.example.test/realuptime-alerts',
        ],
        'scopes' => ['monitor.down', 'monitor.recovered'],
    ]);
    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Workflow site',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com/workflow-health',
        'request_method' => 'GET',
        'interval_seconds' => 30,
        'timeout_seconds' => 30,
        'retry_limit' => 0,
        'expected_status_code' => 200,
        'region' => 'North America',
    ]);

    app(MonitorRunner::class)->runMonitor(
        $monitor->fresh(['notificationContacts', 'user']),
        CarbonImmutable::parse('2026-03-11 16:00:00'),
    );

    Http::assertSent(function ($request): bool {
        $payload = $request->data();

        return $request->url() === 'https://workflows.example.test/realuptime-alerts'
            && data_get($payload, 'event') === 'monitor.down'
            && data_get($payload, 'event_label') === 'Downtime opened'
            && data_get($payload, 'workspace_name') !== null
            && data_get($payload, 'monitor_name') === 'Workflow site'
            && data_get($payload, 'monitor_target') === 'https://example.com/workflow-health'
            && data_get($payload, 'incident_reason') === 'Expected HTTP 200 but received 500.'
            && data_get($payload, 'incident_url') !== null
            && ! array_key_exists('monitor', $payload)
            && ! array_key_exists('incident', $payload)
            && ! array_key_exists('blocks', $payload);
    });

    $this->assertDatabaseHas('notification_logs', [
        'monitor_id' => $monitor->id,
        'integration_id' => $integration->id,
        'channel' => WorkspaceIntegration::PROVIDER_WEBHOOK,
        'type' => 'down',
        'status' => 'sent',
    ]);

    expect($integration->fresh()?->last_tested_at)->not->toBeNull();
});

it('does not send webhook integrations for free workspaces', function () {
    Notification::fake();
    Http::fake([
        'https://example.com/free-workflow-health' => Http::response('error', 500),
        'https://workflows.example.test/free-realuptime-alerts' => Http::response('ok', 200),
    ]);

    $user = User::factory()->create();
    $integration = WorkspaceIntegration::query()->create([
        'user_id' => $user->id,
        'provider' => WorkspaceIntegration::PROVIDER_WEBHOOK,
        'name' => 'Free Workflow',
        'status' => WorkspaceIntegration::STATUS_ACTIVE,
        'config' => [
            'webhook_url' => 'https://workflows.example.test/free-realuptime-alerts',
        ],
        'scopes' => ['monitor.down', 'monitor.recovered'],
    ]);
    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Free workflow site',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com/free-workflow-health',
        'request_method' => 'GET',
        'interval_seconds' => 300,
        'timeout_seconds' => 30,
        'retry_limit' => 0,
        'expected_status_code' => 200,
        'region' => 'North America',
    ]);

    app(MonitorRunner::class)->runMonitor(
        $monitor->fresh(['notificationContacts', 'user']),
        CarbonImmutable::parse('2026-03-11 16:30:00'),
    );

    Http::assertNotSent(fn ($request) => $request->url() === 'https://workflows.example.test/free-realuptime-alerts');

    $this->assertDatabaseMissing('notification_logs', [
        'monitor_id' => $monitor->id,
        'integration_id' => $integration->id,
        'channel' => WorkspaceIntegration::PROVIDER_WEBHOOK,
        'type' => 'down',
    ]);
});

it('does not send downtime webhook payloads for free workspaces', function () {
    Notification::fake();
    Http::fake([
        'https://example.com/free-webhook-health' => Http::response('error', 500),
        'https://hooks.example.com/free-realuptime' => Http::response(['received' => true], 202),
    ]);

    $user = User::factory()->create();
    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Legacy webhook site',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com/free-webhook-health',
        'request_method' => 'GET',
        'interval_seconds' => 300,
        'timeout_seconds' => 30,
        'retry_limit' => 0,
        'expected_status_code' => 200,
        'downtime_webhook_urls' => ['https://hooks.example.com/free-realuptime'],
        'region' => 'North America',
    ]);

    app(MonitorRunner::class)->runMonitor(
        $monitor->fresh(['notificationContacts', 'user']),
        CarbonImmutable::parse('2026-03-06 12:45:00'),
    );

    Http::assertNotSent(fn ($request) => $request->url() === 'https://hooks.example.com/free-realuptime');

    $this->assertDatabaseMissing('notification_logs', [
        'monitor_id' => $monitor->id,
        'channel' => 'webhook',
        'type' => 'down',
    ]);
});

it('renders and updates the incident detail page', function () {
    $user = User::factory()->premium()->create();
    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Primary API',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_DOWN,
        'target' => 'https://example.com/health',
        'request_method' => 'GET',
        'interval_seconds' => 300,
        'timeout_seconds' => 30,
        'retry_limit' => 2,
        'region' => 'North America',
    ]);

    $lastGood = $monitor->checkResults()->create([
        'status' => 'up',
        'checked_at' => now()->subMinutes(12),
        'attempts' => 1,
        'response_time_ms' => 180,
        'http_status_code' => 200,
    ]);

    $firstFailed = $monitor->checkResults()->create([
        'status' => 'down',
        'checked_at' => now()->subMinutes(10),
        'attempts' => 3,
        'http_status_code' => 500,
        'error_type' => 'invalid_status',
        'error_message' => 'Expected HTTP 200 but received 500.',
        'meta' => [
            'attempt_history' => [
                [
                    'attempt' => 1,
                    'status' => 'down',
                    'checked_at' => now()->subMinutes(10)->toIso8601String(),
                    'http_status_code' => 500,
                    'error_message' => 'Expected HTTP 200 but received 500.',
                ],
                [
                    'attempt' => 2,
                    'status' => 'down',
                    'checked_at' => now()->subMinutes(10)->toIso8601String(),
                    'http_status_code' => 500,
                    'error_message' => 'Expected HTTP 200 but received 500.',
                ],
            ],
        ],
    ]);

    $latest = $monitor->checkResults()->create([
        'status' => 'down',
        'checked_at' => now()->subMinutes(5),
        'attempts' => 1,
        'http_status_code' => 500,
        'error_type' => 'invalid_status',
        'error_message' => 'Expected HTTP 200 but received 500.',
    ]);

    $incident = Incident::query()->create([
        'monitor_id' => $monitor->id,
        'first_check_result_id' => $firstFailed->id,
        'last_good_check_result_id' => $lastGood->id,
        'latest_check_result_id' => $latest->id,
        'started_at' => now()->subMinutes(10),
        'type' => Incident::TYPE_DOWNTIME,
        'severity' => Incident::SEVERITY_MAJOR,
        'reason' => 'Expected HTTP 200 but received 500.',
        'error_type' => 'invalid_status',
        'http_status_code' => 500,
    ]);

    NotificationLog::query()->create([
        'monitor_id' => $monitor->id,
        'incident_id' => $incident->id,
        'channel' => 'email',
        'type' => 'down',
        'subject' => 'Primary API is down',
        'status' => 'sent',
        'sent_at' => now()->subMinutes(9),
        'payload' => ['email' => 'ops@example.com'],
    ]);

    $this->actingAs($user)
        ->get("/incidents/{$incident->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('incidents/show')
            ->where('incident.reason', 'Expected HTTP 200 but received 500.')
            ->where('incident.firstFailedCheck.status', 'Down')
            ->where('incident.lastGoodCheck.status', 'Up')
            ->has('incident.timeline', 3)
            ->has('incident.notificationHistory', 1));

    $this->actingAs($user)
        ->put("/incidents/{$incident->id}", [
            'operator_notes' => 'Escalated to platform team.',
            'root_cause_summary' => 'Upstream load balancer was returning 500s.',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('incidents', [
        'id' => $incident->id,
        'operator_notes' => 'Escalated to platform team.',
        'root_cause_summary' => 'Upstream load balancer was returning 500s.',
    ]);
});

it('deletes incidents from the current workspace and detaches notification logs', function () {
    $user = User::factory()->premium()->create();
    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Primary API',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_DOWN,
        'target' => 'https://example.com/health',
        'request_method' => 'GET',
        'interval_seconds' => 300,
        'timeout_seconds' => 30,
        'retry_limit' => 2,
        'region' => 'North America',
    ]);

    $check = $monitor->checkResults()->create([
        'status' => 'down',
        'checked_at' => now()->subMinutes(5),
        'attempts' => 1,
        'http_status_code' => 500,
        'error_type' => 'invalid_status',
        'error_message' => 'Expected HTTP 200 but received 500.',
    ]);

    $incident = Incident::query()->create([
        'monitor_id' => $monitor->id,
        'first_check_result_id' => $check->id,
        'latest_check_result_id' => $check->id,
        'started_at' => now()->subMinutes(5),
        'type' => Incident::TYPE_DOWNTIME,
        'severity' => Incident::SEVERITY_MAJOR,
        'reason' => 'Expected HTTP 200 but received 500.',
        'error_type' => 'invalid_status',
        'http_status_code' => 500,
    ]);

    $notificationLog = NotificationLog::query()->create([
        'monitor_id' => $monitor->id,
        'incident_id' => $incident->id,
        'channel' => 'email',
        'type' => 'down',
        'subject' => 'Primary API is down',
        'status' => 'sent',
        'sent_at' => now()->subMinutes(4),
        'payload' => ['email' => 'ops@example.com'],
    ]);

    $this->actingAs($user)
        ->delete("/incidents/{$incident->id}")
        ->assertRedirect('/incidents');

    $this->assertDatabaseMissing('incidents', [
        'id' => $incident->id,
    ]);

    $this->assertDatabaseHas('notification_logs', [
        'id' => $notificationLog->id,
        'incident_id' => null,
    ]);
});

it('does not delete incidents outside the current workspace', function () {
    $owner = User::factory()->premium()->create();
    $otherUser = User::factory()->premium()->create();

    $monitor = Monitor::query()->create([
        'user_id' => $owner->id,
        'name' => 'Primary API',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_DOWN,
        'target' => 'https://example.com/health',
        'request_method' => 'GET',
        'interval_seconds' => 300,
        'timeout_seconds' => 30,
        'retry_limit' => 2,
        'region' => 'North America',
    ]);

    $incident = Incident::query()->create([
        'monitor_id' => $monitor->id,
        'started_at' => now()->subMinutes(5),
        'type' => Incident::TYPE_DOWNTIME,
        'severity' => Incident::SEVERITY_MAJOR,
        'reason' => 'Expected HTTP 200 but received 500.',
        'error_type' => 'invalid_status',
        'http_status_code' => 500,
    ]);

    $this->actingAs($otherUser)
        ->delete("/incidents/{$incident->id}")
        ->assertNotFound();

    $this->assertDatabaseHas('incidents', [
        'id' => $incident->id,
    ]);
});

<?php

use App\Jobs\RunMonitorRecoveryConfirmationJob;
use App\Models\CheckResult;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\ProbeConfirmation;
use App\Models\User;
use App\Services\Monitoring\MonitorRunner;
use App\Services\Monitoring\ProbeConfirmationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('accepts configured HTTP status ranges for healthy responses', function () {
    Http::fake([
        'https://example.com/health' => Http::response('redirect', 302),
    ]);

    $user = User::factory()->premium()->create();
    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Redirect check',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com/health',
        'request_method' => 'GET',
        'interval_seconds' => 60,
        'timeout_seconds' => 15,
        'retry_limit' => 0,
        'accepted_http_statuses' => '200-299,301,302',
        'region' => 'North America',
    ]);

    $outcome = app(MonitorRunner::class)->runMonitor($monitor->fresh(['notificationContacts', 'user']));

    expect($outcome->status)->toBe('up');
    expect($monitor->fresh()->last_http_status)->toBe(302);
    expect($monitor->fresh()->last_error_type)->toBeNull();
});

it('classifies cloudflare 520 responses with edge metadata', function () {
    Http::fake([
        'https://example.com/health' => Http::response('origin error', 520, [
            'server' => 'cloudflare',
            'cf-ray' => 'abc123-LHR',
            'cf-cache-status' => 'DYNAMIC',
        ]),
    ]);

    $user = User::factory()->premium()->create();
    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Cloudflare edge check',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com/health',
        'request_method' => 'GET',
        'interval_seconds' => 60,
        'timeout_seconds' => 15,
        'retry_limit' => 0,
        'accepted_http_statuses' => '200-299',
        'region' => 'North America',
    ]);

    app(MonitorRunner::class)->runMonitor($monitor->fresh(['notificationContacts', 'user']));

    $checkResult = CheckResult::query()->latest('id')->first();

    expect($checkResult)->not->toBeNull();
    expect($checkResult->error_type)->toBe('cloudflare_520');
    expect($checkResult->error_message)->toContain('Cloudflare returned HTTP 520');
    expect(data_get($checkResult->meta, 'cdn.provider'))->toBe('cloudflare');
    expect(data_get($checkResult->meta, 'cdn.cf_ray'))->toBe('abc123-LHR');
    expect(data_get($checkResult->meta, 'cdn.cf_cache_status'))->toBe('DYNAMIC');
});

it('records transient edge failures that recover on retry', function () {
    Http::fake([
        'https://example.com/health' => Http::sequence()
            ->push('origin error', 520, [
                'server' => 'cloudflare',
                'cf-ray' => 'abc123-LHR',
                'cf-cache-status' => 'DYNAMIC',
            ])
            ->push('ok', 200, [
                'server' => 'cloudflare',
                'cf-ray' => 'def456-LHR',
                'cf-cache-status' => 'DYNAMIC',
            ]),
    ]);

    $user = User::factory()->premium()->create();
    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Retrying edge check',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com/health',
        'request_method' => 'GET',
        'interval_seconds' => 60,
        'timeout_seconds' => 15,
        'retry_limit' => 1,
        'accepted_http_statuses' => '200-299',
        'region' => 'North America',
    ]);

    $outcome = app(MonitorRunner::class)->runMonitor($monitor->fresh(['notificationContacts', 'user']));

    $checkResult = CheckResult::query()->latest('id')->first();
    $incident = Incident::query()
        ->where('monitor_id', $monitor->id)
        ->where('type', Incident::TYPE_TRANSIENT_FAILURE)
        ->latest('id')
        ->first();

    expect($outcome->status)->toBe('up');
    expect($checkResult)->not->toBeNull();
    expect(data_get($checkResult->meta, 'transient_failure'))->toBeTrue();
    expect($incident)->not->toBeNull();
    expect($incident?->resolved_at)->not->toBeNull();
    expect($incident?->reason)->toContain('Transient Cloudflare 520');
});

it('captures queue lag and requires regional confirmation before resolving cloudflare downtime', function () {
    Queue::fake();
    config()->set('realuptime.probe_regions.use_region_queues', true);
    config()->set('realuptime.confirmations.recovery_enabled', true);

    Http::fake([
        'https://example.com/health' => Http::response('ok', 200, [
            'server' => 'cloudflare',
            'cf-ray' => 'recovery123-LHR',
            'cf-cache-status' => 'DYNAMIC',
        ]),
    ]);

    $user = User::factory()->premium()->create();
    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Recovering edge monitor',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_DOWN,
        'target' => 'https://example.com/health',
        'request_method' => 'GET',
        'interval_seconds' => 60,
        'timeout_seconds' => 15,
        'retry_limit' => 0,
        'accepted_http_statuses' => '200-299',
        'region' => 'North America',
        'last_status_changed_at' => now()->subMinutes(5),
        'last_error_type' => 'cloudflare_520',
        'last_error_message' => 'Cloudflare returned HTTP 520 (unknown origin error).',
    ]);

    $incident = Incident::query()->create([
        'monitor_id' => $monitor->id,
        'started_at' => now()->subMinutes(5),
        'type' => Incident::TYPE_DOWNTIME,
        'severity' => Incident::SEVERITY_MAJOR,
        'reason' => 'Cloudflare returned HTTP 520 (unknown origin error).',
        'error_type' => 'cloudflare_520',
        'http_status_code' => 520,
        'meta' => [
            'cdn' => [
                'provider' => 'cloudflare',
                'edge_detected' => true,
            ],
        ],
    ]);

    app(MonitorRunner::class)->runMonitor(
        $monitor->fresh(['notificationContacts', 'user']),
        CarbonImmutable::parse('2026-03-17 12:00:00'),
        5400,
        'North America',
    );

    $monitor = $monitor->fresh();
    $incident = $incident->fresh();
    $confirmation = ProbeConfirmation::query()->latest('id')->first();
    $primaryCheckResult = $confirmation?->primaryCheckResult;

    expect($monitor->status)->toBe(Monitor::STATUS_DOWN);
    expect($monitor->last_error_type)->toBe('awaiting_recovery_confirmation');
    expect($monitor->last_queue_lag_ms)->toBe(5400);
    expect($monitor->last_probe_region)->toBe('North America');
    expect($incident?->resolved_at)->toBeNull();
    expect($confirmation)->not->toBeNull();
    expect($primaryCheckResult)->not->toBeNull();
    expect(data_get($primaryCheckResult->meta, 'queue_lag_ms'))->toBe(5400);

    expect($confirmation?->confirmation_regions)->toBe(['Europe']);

    $report = app(MonitorRunner::class)->probeMonitor(
        $monitor->fresh(['notificationContacts', 'user']),
        checkedAt: CarbonImmutable::parse('2026-03-17 12:00:01'),
        attemptsOverride: 1,
        queueLagMs: 0,
        probeRegion: 'Europe',
        queueMetadataRefresh: false,
    );

    app(ProbeConfirmationService::class)->recordRecoveryResult(
        $confirmation->fresh(),
        $monitor->fresh(['notificationContacts', 'user']),
        'Europe',
        $report,
    );

    expect($incident->fresh()?->resolved_at)->not->toBeNull();
    expect($monitor->fresh()->status)->toBe(Monitor::STATUS_UP);
});

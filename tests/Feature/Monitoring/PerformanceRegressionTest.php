<?php

use App\Jobs\RefreshMonitorMetadataJob;
use App\Jobs\RunMonitorCheckJob;
use App\Models\Monitor;
use App\Models\User;
use App\Services\Monitoring\MonitorPresenter;
use App\Services\Monitoring\MonitorRunner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('queues an on-demand check from the monitor screen', function () {
    Queue::fake();

    $user = User::factory()->create();
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

    $this->actingAs($user)
        ->post(route('monitors.run-now', $monitor))
        ->assertRedirect()
        ->assertSessionHas('success', 'Queued an on-demand check for Main website.');

    Queue::assertPushed(
        RunMonitorCheckJob::class,
        fn (RunMonitorCheckJob $job) => $job->monitorId === $monitor->id
    );
});

it('queues metadata refresh work after a successful https check', function () {
    Queue::fake();
    Http::fake([
        'https://example.com/health' => Http::response('ok', 200),
    ]);

    $user = User::factory()->premium()->create();
    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Secure API',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com/health',
        'request_method' => 'GET',
        'interval_seconds' => 60,
        'timeout_seconds' => 5,
        'retry_limit' => 1,
        'follow_redirects' => true,
        'region' => 'North America',
        'last_status_changed_at' => now()->subMinutes(10),
    ]);

    $outcome = app(MonitorRunner::class)->runMonitor($monitor->fresh(['notificationContacts', 'user']));

    expect($outcome->status)->toBe('up');

    Queue::assertPushed(
        RefreshMonitorMetadataJob::class,
        fn (RefreshMonitorMetadataJob $job) => $job->monitorId === $monitor->id
    );
});

it('builds monitor detail history from current-monitor aggregate queries', function () {
    CarbonImmutable::setTestNow('2026-03-10 12:00:00');

    $user = User::factory()->create();
    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Primary API',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com/health',
        'request_method' => 'GET',
        'interval_seconds' => 60,
        'timeout_seconds' => 5,
        'retry_limit' => 1,
        'region' => 'Europe',
        'last_checked_at' => now()->subMinute(),
        'last_status_changed_at' => now()->subDay(),
    ]);
    $otherMonitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Noisy API',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_DOWN,
        'target' => 'https://noise.example.com/health',
        'request_method' => 'GET',
        'interval_seconds' => 60,
        'timeout_seconds' => 5,
        'retry_limit' => 1,
        'region' => 'North America',
        'last_checked_at' => now()->subMinute(),
        'last_status_changed_at' => now()->subDay(),
    ]);

    $rows = [];

    foreach (range(0, 143) as $index) {
        $checkedAt = now()->subMinutes(($index * 10) + 1);
        $rows[] = [
            'monitor_id' => $monitor->id,
            'status' => $index % 12 === 0 ? 'down' : 'up',
            'checked_at' => $checkedAt,
            'attempts' => 1,
            'response_time_ms' => 100 + $index,
            'http_status_code' => $index % 12 === 0 ? 500 : 200,
            'meta' => json_encode(['slow' => $index % 15 === 0]),
            'created_at' => $checkedAt,
            'updated_at' => $checkedAt,
        ];
        $rows[] = [
            'monitor_id' => $otherMonitor->id,
            'status' => 'down',
            'checked_at' => $checkedAt,
            'attempts' => 1,
            'response_time_ms' => 9999,
            'http_status_code' => 500,
            'meta' => json_encode(['slow' => true]),
            'created_at' => $checkedAt,
            'updated_at' => $checkedAt,
        ];
    }

    foreach (array_chunk($rows, 100) as $chunk) {
        DB::table('check_results')->insert($chunk);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $history = app(MonitorPresenter::class)
        ->showHistory($monitor->fresh(), 'day', '1h')['monitorHistory'];

    $queries = collect(DB::getQueryLog())->pluck('query')->implode("\n");

    DB::disableQueryLog();
    CarbonImmutable::setTestNow();

    expect($history['responseTimeSignals']['sampleCount'])->toBe(144);
    expect($history['responseTimeSignals']['failedChecks'])->toBe(12);
    expect($history['responseTimeSignals']['slowChecks'])->toBe(10);
    expect($history['responseTimeStats']['average'])->toBe(172);
    expect($history['responseTimeStats']['median'])->toBe(172);
    expect($history['responseTimeStats']['p95'])->toBe(236);
    expect($history['responseTimeChart'])->toHaveCount(24);
    expect($queries)->not->toContain('"response_time_ms", "meta" from "check_results"');
});

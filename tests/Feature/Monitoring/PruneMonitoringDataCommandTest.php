<?php

use App\Models\CheckResult;
use App\Models\CheckResultRollup;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\Monitoring\MonitorPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

afterEach(fn () => CarbonImmutable::setTestNow());

it('runs monitoring data retention automatically as an isolated daily task', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => $event->description === 'realuptime:prune-monitoring-data');

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('15 3 * * *')
        ->and($event->timezone)->toBe(config('app.timezone'))
        ->and($event->onOneServer)->toBeTrue()
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->expiresAt)->toBe(360)
        ->and($event->runInBackground)->toBeTrue()
        ->and($event->evenInMaintenanceMode)->toBeTrue();
});

it('rolls check history through both granularities and expires it after two years', function () {
    $now = CarbonImmutable::parse('2026-07-14 12:00:00');
    CarbonImmutable::setTestNow($now);

    config()->set('realuptime.retention.notification_logs_days', 30);
    config()->set('realuptime.retention.raw_check_results_days', 30);
    config()->set('realuptime.retention.fine_rollup_days', 180);
    config()->set('realuptime.retention.check_history_days', 730);
    config()->set('realuptime.retention.prune_chunk_size', 100);

    $user = User::factory()->create();
    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Primary API',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com/health',
        'request_method' => 'GET',
        'interval_seconds' => 30,
        'timeout_seconds' => 30,
        'retry_limit' => 2,
        'region' => 'North America',
        'last_checked_at' => $now->subMinute(),
    ]);

    foreach (range(1, 3) as $index) {
        $log = NotificationLog::query()->create([
            'monitor_id' => $monitor->id,
            'channel' => 'email',
            'type' => 'down',
            'subject' => sprintf('Old log %d', $index),
            'status' => 'sent',
        ]);

        $log->forceFill([
            'created_at' => $now->subDays(40)->addMinutes($index),
            'updated_at' => $now->subDays(40)->addMinutes($index),
        ])->saveQuietly();
    }

    $recentLog = NotificationLog::query()->create([
        'monitor_id' => $monitor->id,
        'channel' => 'email',
        'type' => 'down',
        'subject' => 'Recent log',
        'status' => 'sent',
    ]);

    $fineStart = $now->subDays(40)->startOfHour();
    insertCheckResults($monitor, $fineStart, 120, 17, 100, 50);

    $dailyStart = $now->subDays(200)->startOfDay();
    insertCheckResults($monitor, $dailyStart, 2880, 1440, 200);

    $incidentResult = CheckResult::query()
        ->where('monitor_id', $monitor->id)
        ->where('checked_at', $fineStart->addSeconds(17 * 30))
        ->firstOrFail();
    $incident = Incident::query()->create([
        'monitor_id' => $monitor->id,
        'first_check_result_id' => $incidentResult->id,
        'latest_check_result_id' => $incidentResult->id,
        'started_at' => $incidentResult->checked_at,
        'resolved_at' => $incidentResult->checked_at->addSeconds(30),
        'duration_seconds' => 30,
        'type' => Incident::TYPE_DOWNTIME,
        'severity' => Incident::SEVERITY_MAJOR,
        'reason' => 'Historical outage.',
    ]);

    $recentResult = CheckResult::query()->create([
        'monitor_id' => $monitor->id,
        'status' => 'up',
        'checked_at' => $now->subDays(5),
        'attempts' => 1,
        'response_time_ms' => 300,
        'http_status_code' => 200,
        'meta' => [],
    ]);
    $expiredResult = CheckResult::query()->create([
        'monitor_id' => $monitor->id,
        'status' => 'down',
        'checked_at' => $now->subDays(731),
        'attempts' => 1,
        'meta' => [],
    ]);
    CheckResultRollup::query()->create([
        'monitor_id' => $monitor->id,
        'granularity_seconds' => CheckResultRollup::GRANULARITY_DAY,
        'bucket_started_at' => $now->subDays(732)->startOfDay(),
        'bucket_ended_at' => $now->subDays(731)->startOfDay(),
        'total_checks' => 2880,
        'up_checks' => 2880,
        'down_checks' => 0,
        'slow_checks' => 0,
        'response_time_samples' => 2880,
        'response_time_sum_ms' => 288000,
        'response_time_min_ms' => 100,
        'response_time_max_ms' => 100,
        'first_checked_at' => $now->subDays(732)->startOfDay(),
        'last_checked_at' => $now->subDays(732)->endOfDay(),
    ]);

    $this->artisan('realuptime:prune-monitoring-data')
        ->expectsOutput('Deleted 3 notification logs. Compacted 3000 check results into 100 15-minute buckets and 96 15-minute buckets into 1 daily buckets. Deleted 1 expired check results and 1 expired rollups.')
        ->assertSuccessful();

    expect(NotificationLog::query()->count())->toBe(1)
        ->and(NotificationLog::query()->whereKey($recentLog->id)->exists())->toBeTrue()
        ->and(CheckResult::query()->count())->toBe(1)
        ->and(CheckResult::query()->whereKey($recentResult->id)->exists())->toBeTrue()
        ->and(CheckResult::query()->whereKey($expiredResult->id)->exists())->toBeFalse();

    $fineRollups = CheckResultRollup::query()
        ->where('granularity_seconds', CheckResultRollup::GRANULARITY_FIFTEEN_MINUTES)
        ->get();
    $dailyRollup = CheckResultRollup::query()
        ->where('granularity_seconds', CheckResultRollup::GRANULARITY_DAY)
        ->sole();
    $failedFineRollup = $fineRollups->firstWhere('down_checks', 1);

    expect($fineRollups)->toHaveCount(4)
        ->and($fineRollups->sum('total_checks'))->toBe(120)
        ->and($fineRollups->sum('down_checks'))->toBe(1)
        ->and((int) $failedFineRollup->total_checks)->toBe(30)
        ->and((int) $failedFineRollup->down_checks)->toBe(1)
        ->and($fineRollups->sum('slow_checks'))->toBe(1)
        ->and($fineRollups->sum('response_time_samples'))->toBe(119)
        ->and((int) $dailyRollup->total_checks)->toBe(2880)
        ->and((int) $dailyRollup->down_checks)->toBe(1)
        ->and((int) $dailyRollup->response_time_samples)->toBe(2879);

    $incident->refresh();

    expect($incident->exists)->toBeTrue()
        ->and($incident->first_check_result_id)->toBeNull()
        ->and($incident->latest_check_result_id)->toBeNull();

    $history = app(MonitorPresenter::class)
        ->showHistory($monitor->fresh(), 'year', '1d')['monitorHistory'];

    expect($history['responseTimeSignals']['sampleCount'])->toBe(3001)
        ->and($history['responseTimeSignals']['failedChecks'])->toBe(2)
        ->and($history['responseTimeSignals']['slowChecks'])->toBe(1)
        ->and($history['responseTimeSignals']['successRate'])->toBe(99.93)
        ->and($history['responseTimeStats']['average'])->toBe(196)
        ->and($history['responseTimeStats']['minimum'])->toBe(100)
        ->and($history['responseTimeStats']['maximum'])->toBe(300)
        ->and(collect($history['responseTimeChart'])->where('status', 'down'))->toHaveCount(2);

    CheckResult::query()->create([
        'monitor_id' => $monitor->id,
        'status' => 'down',
        'checked_at' => $fineStart->addSeconds(10),
        'attempts' => 1,
        'http_status_code' => 500,
        'error_type' => 'invalid_status',
        'meta' => [],
    ]);

    $this->artisan('realuptime:prune-monitoring-data')
        ->expectsOutput('Deleted 0 notification logs. Compacted 1 check results into 1 15-minute buckets and 0 15-minute buckets into 0 daily buckets. Deleted 0 expired check results and 0 expired rollups.')
        ->assertSuccessful();

    expect(CheckResultRollup::query()
        ->where('granularity_seconds', CheckResultRollup::GRANULARITY_FIFTEEN_MINUTES)
        ->sum('total_checks'))->toBe(121)
        ->and(CheckResultRollup::query()
            ->where('granularity_seconds', CheckResultRollup::GRANULARITY_FIFTEEN_MINUTES)
            ->sum('down_checks'))->toBe(2);

    $this->artisan('realuptime:prune-monitoring-data')
        ->expectsOutput('Deleted 0 notification logs. Compacted 0 check results into 0 15-minute buckets and 0 15-minute buckets into 0 daily buckets. Deleted 0 expired check results and 0 expired rollups.')
        ->assertSuccessful();

    expect(CheckResultRollup::query()->count())->toBe(5);

});

function insertCheckResults(
    Monitor $monitor,
    CarbonImmutable $from,
    int $count,
    int $downIndex,
    int $responseTime,
    ?int $slowIndex = null,
): void {
    $now = CarbonImmutable::now();
    $rows = collect(range(0, $count - 1))->map(function (int $index) use (
        $monitor,
        $from,
        $downIndex,
        $responseTime,
        $slowIndex,
        $now,
    ): array {
        $isDown = $index === $downIndex;

        return [
            'monitor_id' => $monitor->id,
            'status' => $isDown ? 'down' : 'up',
            'checked_at' => $from->addSeconds($index * 30),
            'attempts' => 1,
            'response_time_ms' => $isDown ? null : $responseTime,
            'http_status_code' => $isDown ? 500 : 200,
            'error_type' => $isDown ? 'invalid_status' : null,
            'error_message' => $isDown ? 'Expected HTTP 200 but received 500.' : null,
            'keyword_match' => null,
            'meta' => json_encode(['slow' => $index === $slowIndex]),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    });

    foreach ($rows->chunk(500) as $chunk) {
        DB::table('check_results')->insert($chunk->all());
    }
}

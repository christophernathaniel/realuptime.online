<?php

use App\Models\CheckResult;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('prunes notification logs and disposable healthy results in batches', function () {
    config()->set('realuptime.retention.notification_logs_days', 30);
    config()->set('realuptime.retention.healthy_check_results_days', 30);
    config()->set('realuptime.retention.prune_chunk_size', 2);

    $user = User::factory()->create();
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

    $oldLogs = collect(range(1, 3))->map(function (int $index) use ($monitor) {
        $log = NotificationLog::query()->create([
            'monitor_id' => $monitor->id,
            'channel' => 'email',
            'type' => 'down',
            'subject' => sprintf('Old log %d', $index),
            'status' => 'sent',
        ]);

        $log->forceFill([
            'created_at' => now()->subDays(40)->addMinutes($index),
            'updated_at' => now()->subDays(40)->addMinutes($index),
        ])->saveQuietly();

        return $log;
    });

    $recentLog = NotificationLog::query()->create([
        'monitor_id' => $monitor->id,
        'channel' => 'email',
        'type' => 'down',
        'subject' => 'Recent log',
        'status' => 'sent',
    ]);

    $deletableA = CheckResult::query()->create([
        'monitor_id' => $monitor->id,
        'status' => 'up',
        'checked_at' => now()->subDays(40),
        'attempts' => 1,
        'response_time_ms' => 120,
        'http_status_code' => 200,
        'meta' => [],
    ]);
    $deletableB = CheckResult::query()->create([
        'monitor_id' => $monitor->id,
        'status' => 'up',
        'checked_at' => now()->subDays(39),
        'attempts' => 1,
        'response_time_ms' => 130,
        'http_status_code' => 200,
        'meta' => [],
    ]);
    $protected = CheckResult::query()->create([
        'monitor_id' => $monitor->id,
        'status' => 'up',
        'checked_at' => now()->subDays(38),
        'attempts' => 1,
        'response_time_ms' => 140,
        'http_status_code' => 200,
        'meta' => [],
    ]);
    $slow = CheckResult::query()->create([
        'monitor_id' => $monitor->id,
        'status' => 'up',
        'checked_at' => now()->subDays(37),
        'attempts' => 1,
        'response_time_ms' => 900,
        'http_status_code' => 200,
        'meta' => ['slow' => true],
    ]);
    $recent = CheckResult::query()->create([
        'monitor_id' => $monitor->id,
        'status' => 'up',
        'checked_at' => now()->subDays(5),
        'attempts' => 1,
        'response_time_ms' => 150,
        'http_status_code' => 200,
        'meta' => [],
    ]);
    $down = CheckResult::query()->create([
        'monitor_id' => $monitor->id,
        'status' => 'down',
        'checked_at' => now()->subDays(40),
        'attempts' => 1,
        'error_type' => 'timeout',
        'error_message' => 'Timed out',
        'meta' => [],
    ]);

    Incident::query()->create([
        'monitor_id' => $monitor->id,
        'first_check_result_id' => $protected->id,
        'latest_check_result_id' => $protected->id,
        'started_at' => now()->subDays(38),
        'type' => Incident::TYPE_DOWNTIME,
        'severity' => Incident::SEVERITY_MAJOR,
        'reason' => 'Protected check result.',
    ]);

    $this->artisan('realuptime:prune-monitoring-data')
        ->expectsOutput('Deleted 3 notification logs and 2 healthy check results.')
        ->assertSuccessful();

    expect(NotificationLog::query()->whereKey($oldLogs->pluck('id')->all())->count())->toBe(0);
    expect(NotificationLog::query()->whereKey($recentLog->id)->exists())->toBeTrue();

    expect(CheckResult::query()->whereKey($deletableA->id)->exists())->toBeFalse();
    expect(CheckResult::query()->whereKey($deletableB->id)->exists())->toBeFalse();
    expect(CheckResult::query()->whereKey($protected->id)->exists())->toBeTrue();
    expect(CheckResult::query()->whereKey($slow->id)->exists())->toBeTrue();
    expect(CheckResult::query()->whereKey($recent->id)->exists())->toBeTrue();
    expect(CheckResult::query()->whereKey($down->id)->exists())->toBeTrue();
});

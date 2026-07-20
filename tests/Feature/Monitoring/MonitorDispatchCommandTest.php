<?php

use App\Jobs\RunMonitorCheckJob;
use App\Models\Monitor;
use App\Models\User;
use App\Services\Monitoring\MonitorRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('dispatches only due monitors onto the queue', function () {
    Queue::fake();

    $user = User::factory()->create();

    $dueMonitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Due API',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com/health',
        'request_method' => 'GET',
        'interval_seconds' => 300,
        'timeout_seconds' => 30,
        'retry_limit' => 2,
        'region' => 'North America',
        'next_check_at' => now()->subMinute(),
    ]);

    Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Future API',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com/future',
        'request_method' => 'GET',
        'interval_seconds' => 300,
        'timeout_seconds' => 30,
        'retry_limit' => 2,
        'region' => 'North America',
        'next_check_at' => now()->addMinutes(5),
    ]);

    Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Paused API',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_PAUSED,
        'target' => 'https://example.com/paused',
        'request_method' => 'GET',
        'interval_seconds' => 300,
        'timeout_seconds' => 30,
        'retry_limit' => 2,
        'region' => 'North America',
        'next_check_at' => now()->subMinute(),
    ]);

    $recentlyDispatched = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Recently dispatched API',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com/recent',
        'request_method' => 'GET',
        'interval_seconds' => 60,
        'timeout_seconds' => 15,
        'retry_limit' => 1,
        'region' => 'North America',
        'next_check_at' => now()->subMinute(),
        'last_dispatched_at' => now()->subSeconds(30),
    ]);

    Artisan::call('monitors:run-due', [
        '--batch' => 100,
        '--max-batches' => 2,
    ]);

    Queue::assertPushed(RunMonitorCheckJob::class, 1);
    Queue::assertPushed(RunMonitorCheckJob::class, fn (RunMonitorCheckJob $job) => $job->monitorId === $dueMonitor->id);

    expect($dueMonitor->fresh()->check_claimed_at)->not->toBeNull();
    expect(Monitor::query()->where('name', 'Future API')->first()?->check_claimed_at)->toBeNull();
    expect($recentlyDispatched->fresh()->check_claimed_at)->toBeNull();
});

it('shards monitor check jobs across configured monitor queues', function () {
    Queue::fake();
    config()->set('realuptime.queues.monitor_check_shards', [
        'monitor-checks-a',
        'monitor-checks-b',
        'monitor-checks-c',
    ]);

    $user = User::factory()->create();

    $firstMonitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Shard A',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com/a',
        'request_method' => 'GET',
        'interval_seconds' => 300,
        'timeout_seconds' => 30,
        'retry_limit' => 2,
        'region' => 'North America',
        'next_check_at' => now()->subMinute(),
    ]);

    $secondMonitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Shard B',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com/b',
        'request_method' => 'GET',
        'interval_seconds' => 300,
        'timeout_seconds' => 30,
        'retry_limit' => 2,
        'region' => 'North America',
        'next_check_at' => now()->subMinute(),
    ]);

    Artisan::call('monitors:run-due', [
        '--batch' => 100,
        '--max-batches' => 2,
    ]);

    Queue::assertPushed(
        RunMonitorCheckJob::class,
        fn (RunMonitorCheckJob $job) => $job->monitorId === $firstMonitor->id
            && $job->queue === 'monitor-checks-b'
    );

    Queue::assertPushed(
        RunMonitorCheckJob::class,
        fn (RunMonitorCheckJob $job) => $job->monitorId === $secondMonitor->id
            && $job->queue === 'monitor-checks-c'
    );
});

it('routes ping monitors onto the network queue family when configured', function () {
    Queue::fake();
    config()->set('realuptime.queues.network_monitor_check_shards', [
        'monitor-network-a',
        'monitor-network-b',
    ]);

    $user = User::factory()->create();

    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Network ping',
        'type' => Monitor::TYPE_PING,
        'status' => Monitor::STATUS_UP,
        'target' => '1.1.1.1',
        'interval_seconds' => 300,
        'timeout_seconds' => 5,
        'retry_limit' => 0,
        'region' => 'North America',
        'next_check_at' => now()->subMinute(),
    ]);

    Artisan::call('monitors:run-due', [
        '--batch' => 100,
        '--max-batches' => 2,
    ]);

    Queue::assertPushed(
        RunMonitorCheckJob::class,
        fn (RunMonitorCheckJob $job) => $job->monitorId === $monitor->id
            && $job->monitorType === Monitor::TYPE_PING
            && str_starts_with((string) $job->queue, 'monitor-network-')
    );
});

it('routes monitor jobs onto region-specific queues when regional probes are enabled', function () {
    Queue::fake();
    config()->set('realuptime.probe_regions.use_region_queues', true);

    $user = User::factory()->create();

    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'European API',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com/eu',
        'request_method' => 'GET',
        'interval_seconds' => 300,
        'timeout_seconds' => 15,
        'retry_limit' => 2,
        'region' => 'Europe',
        'next_check_at' => now()->subMinute(),
    ]);

    Artisan::call('monitors:run-due', [
        '--batch' => 100,
        '--max-batches' => 2,
    ]);

    Queue::assertPushed(
        RunMonitorCheckJob::class,
        fn (RunMonitorCheckJob $job) => $job->monitorId === $monitor->id
            && $job->probeRegion === 'Europe'
            && str_ends_with((string) $job->queue, '-eu')
    );
});

it('dispatch loop can run a single iteration on demand', function () {
    Queue::fake();

    $user = User::factory()->create();
    Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Loop API',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com/health',
        'request_method' => 'GET',
        'interval_seconds' => 300,
        'timeout_seconds' => 30,
        'retry_limit' => 2,
        'region' => 'North America',
        'next_check_at' => now()->subMinute(),
    ]);

    Artisan::call('monitors:dispatch-loop', [
        '--batch' => 100,
        '--max-batches' => 2,
        '--once' => true,
    ]);

    Queue::assertPushed(RunMonitorCheckJob::class, 1);
});

it('discards a queued check after a newer dispatcher claim replaces its token', function () {
    Queue::fake();

    $user = User::factory()->create();
    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Reclaimed API',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com/health',
        'request_method' => 'GET',
        'interval_seconds' => 300,
        'timeout_seconds' => 15,
        'retry_limit' => 1,
        'region' => 'North America',
        'next_check_at' => now()->subMinute(),
    ]);

    Artisan::call('monitors:run-due', [
        '--batch' => 100,
        '--max-batches' => 2,
    ]);

    $queuedJob = null;
    Queue::assertPushed(RunMonitorCheckJob::class, function (RunMonitorCheckJob $job) use (&$queuedJob, $monitor) {
        if ($job->monitorId !== $monitor->id) {
            return false;
        }

        $queuedJob = $job;

        return filled($job->claimToken);
    });

    $monitor->forceFill([
        'check_claim_token' => 'newer-claim-token',
        'check_claimed_at' => now(),
    ])->save();

    $runner = Mockery::mock(MonitorRunner::class);
    $runner->shouldNotReceive('runMonitor');

    $queuedJob->handle($runner);

    expect($monitor->fresh()->check_claim_token)->toBe('newer-claim-token');
});

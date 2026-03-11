<?php

use App\Jobs\RunMonitorCheckJob;
use App\Models\Monitor;
use App\Models\User;
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

    Artisan::call('monitors:run-due', [
        '--batch' => 100,
        '--max-batches' => 2,
    ]);

    Queue::assertPushed(RunMonitorCheckJob::class, 1);
    Queue::assertPushed(RunMonitorCheckJob::class, fn (RunMonitorCheckJob $job) => $job->monitorId === $dueMonitor->id);

    expect($dueMonitor->fresh()->check_claimed_at)->not->toBeNull();
    expect(Monitor::query()->where('name', 'Future API')->first()?->check_claimed_at)->toBeNull();
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

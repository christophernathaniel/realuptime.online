<?php

use App\Jobs\RefreshMonitorMetadataJob;
use App\Jobs\RunMonitorCheckJob;
use App\Models\Monitor;
use App\Models\User;
use App\Services\Monitoring\MonitorRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        ->post("/monitors/{$monitor->id}/run-now")
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

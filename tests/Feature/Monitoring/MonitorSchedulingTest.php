<?php

use App\Models\Monitor;
use App\Models\User;
use App\Services\Monitoring\MonitorRunner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('staggers monitor next check times across the interval window', function () {
    Http::fake([
        'https://example.com/*' => Http::response('ok', 200),
    ]);

    $checkedAt = CarbonImmutable::parse('2026-03-12 12:00:00');
    $user = User::factory()->premium()->create();

    $runner = app(MonitorRunner::class);
    $nextCheckAtValues = collect(range(1, 8))->map(function (int $index) use ($checkedAt, $runner, $user) {
        $monitor = Monitor::query()->create([
            'user_id' => $user->id,
            'name' => sprintf('Site %d', $index),
            'type' => Monitor::TYPE_HTTP,
            'status' => Monitor::STATUS_UP,
            'target' => sprintf('https://example.com/%d', $index),
            'request_method' => 'GET',
            'interval_seconds' => 60,
            'timeout_seconds' => 5,
            'retry_limit' => 0,
            'region' => 'North America',
            'next_check_at' => $checkedAt,
        ]);

        $runner->runMonitor($monitor->fresh(['notificationContacts', 'user']), $checkedAt);

        return $monitor->fresh()->next_check_at;
    });

    expect($nextCheckAtValues->every(fn ($value) => $value !== null && $value->gt($checkedAt)))->toBeTrue();
    expect($nextCheckAtValues->every(fn ($value) => $value !== null && $value->lte($checkedAt->addSeconds(60))))->toBeTrue();
    expect($nextCheckAtValues->map(fn ($value) => $value?->toIso8601String())->unique()->count())->toBeGreaterThan(1);
});

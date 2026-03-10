<?php

use App\Models\CheckResult;
use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use App\Models\StatusPage;
use App\Models\StatusPageIncident;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('creates public status page incident posts and renders them publicly', function () {
    $user = User::factory()->premium()->create();
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

    $statusPage = StatusPage::query()->create([
        'user_id' => $user->id,
        'name' => 'Primary status',
        'slug' => 'primary-status',
        'headline' => 'Primary status',
        'description' => 'Public uptime feed',
        'published' => true,
    ]);
    $statusPage->monitors()->attach($monitor->id, ['sort_order' => 0]);

    $this->actingAs($user)
        ->post("/status-pages/{$statusPage->id}/incidents", [
            'title' => 'API latency incident',
            'message' => 'We are investigating elevated response times.',
            'status' => StatusPageIncident::STATUS_INVESTIGATING,
            'impact' => StatusPageIncident::IMPACT_MAJOR,
            'monitor_ids' => [$monitor->id],
        ])
        ->assertRedirect();

    $incident = StatusPageIncident::query()->first();

    expect($incident)->not->toBeNull();
    expect($incident->updates)->toHaveCount(1);

    $this->actingAs($user)
        ->post("/status-page-incidents/{$incident->id}/updates", [
            'status' => StatusPageIncident::STATUS_MONITORING,
            'message' => 'A fix has been deployed and we are monitoring recovery.',
        ])
        ->assertRedirect();

    $this->get("/status/{$user->public_status_key}/primary-status")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('monitoring/public-status')
            ->where('statusPage.incidents.0.title', 'API latency incident')
            ->where('statusPage.incidents.0.status', 'Monitoring')
            ->where('statusPage.incidents.0.updates.0.status', 'Monitoring')
            ->where('statusPage.overallTone', 'warning'));
});

it('renders public status pages from live monitor checks and active maintenance state', function () {
    $now = CarbonImmutable::now();
    Carbon::setTestNow($now);
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
        'region' => 'Europe',
        'last_checked_at' => $now->subMinutes(5),
        'last_status_changed_at' => $now->subHours(2),
        'last_response_time_ms' => 410,
    ]);
    $monitor->forceFill([
        'updated_at' => $now->subHours(6),
    ])->saveQuietly();

    CheckResult::query()->insert(collect(range(0, 287))->map(function (int $step) use ($monitor, $now): array {
        $checkedAt = $now->subMinutes(5 * (288 - $step));
        $isDown = $step < 88;

        return [
            'monitor_id' => $monitor->id,
            'status' => $isDown ? 'down' : 'up',
            'checked_at' => $checkedAt,
            'attempts' => 1,
            'response_time_ms' => $isDown ? null : 410,
            'http_status_code' => 200,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    })->all());

    $statusPage = StatusPage::query()->create([
        'user_id' => $user->id,
        'name' => 'Checkout status',
        'slug' => 'checkout-status',
        'headline' => 'Checkout status',
        'description' => 'Public checkout monitoring',
        'published' => true,
    ]);
    $statusPage->monitors()->attach($monitor->id, ['sort_order' => 0]);
    $statusPage->forceFill([
        'updated_at' => $now->subDays(2),
    ])->saveQuietly();

    $maintenance = MaintenanceWindow::query()->create([
        'user_id' => $user->id,
        'title' => 'Checkout rollout',
        'message' => 'Rolling backend maintenance.',
        'starts_at' => $now->subMinutes(30),
        'ends_at' => $now->addMinutes(30),
        'status' => MaintenanceWindow::STATUS_SCHEDULED,
        'notify_contacts' => false,
    ]);
    $maintenance->monitors()->attach($monitor->id);
    $maintenance->forceFill([
        'updated_at' => $now->subHour(),
    ])->saveQuietly();

    $this->get("/status/{$user->public_status_key}/checkout-status")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('monitoring/public-status')
            ->where('statusPage.overallTone', 'maintenance')
            ->where('statusPage.updatedLabel', 'Updated 5m ago')
            ->where('statusPage.monitors.0.status', 'Maintenance')
            ->where('statusPage.monitors.0.statusTone', 'maintenance')
            ->where('statusPage.monitors.0.uptimeLabel', '69.44%'));

    Carbon::setTestNow();
});

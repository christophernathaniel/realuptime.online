<?php

use App\Jobs\SendMonitorWebhookNotificationJob;
use App\Models\HeartbeatEvent;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\NotificationLog;
use App\Models\User;
use App\Models\WorkspaceIntegration;
use App\Services\Monitoring\MonitorRunner;
use App\Services\Monitoring\WebhookNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('adds browser security headers and prevents authenticated response caching', function () {
    $this->get('/')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Content-Security-Policy', "base-uri 'self'; frame-ancestors 'none'; object-src 'none'");

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('rejects private monitor targets webhooks and transport headers', function () {
    $user = User::factory()->premium()->create();
    $base = [
        'name' => 'Blocked monitor',
        'request_method' => 'GET',
        'interval_seconds' => 60,
        'timeout_seconds' => 5,
        'retry_limit' => 0,
        'follow_redirects' => true,
        'accepted_http_statuses' => '200-299',
        'region' => 'Europe',
    ];

    $this->actingAs($user)->post('/monitors', [
        ...$base,
        'type' => Monitor::TYPE_HTTP,
        'target' => 'http://169.254.169.254/latest/meta-data',
    ])->assertSessionHasErrors('target');

    $this->actingAs($user)->post('/monitors', [
        ...$base,
        'type' => Monitor::TYPE_PING,
        'target' => '127.0.0.1',
    ])->assertSessionHasErrors('target');

    $this->actingAs($user)->post('/monitors', [
        ...$base,
        'type' => Monitor::TYPE_PORT,
        'target' => '10.0.0.5:6379',
    ])->assertSessionHasErrors('target');

    $this->actingAs($user)->post('/monitors', [
        ...$base,
        'type' => Monitor::TYPE_HTTP,
        'target' => 'https://example.com/health',
        'downtime_webhook_urls' => 'http://127.0.0.1/internal-hook',
    ])->assertSessionHasErrors('downtime_webhook_urls');

    $this->actingAs($user)->post('/monitors', [
        ...$base,
        'type' => Monitor::TYPE_HTTP,
        'target' => 'https://example.com/health',
        'custom_headers' => json_encode(['Host' => 'internal.service']),
    ])->assertSessionHasErrors('custom_headers');

    expect($user->monitors()->count())->toBe(0);
});

it('encrypts sensitive monitor configuration and does not redisplay basic auth passwords', function () {
    $user = User::factory()->premium()->create();
    $webhookUrl = 'https://hooks.example.com/path/secret-webhook-token';
    $headerSecret = 'secret-api-header-value';
    $basicAuthSecret = 'stored-basic-auth-secret';

    $this->actingAs($user)->post('/monitors', [
        'name' => 'Secret-bearing monitor',
        'type' => Monitor::TYPE_HTTP,
        'target' => 'https://example.com/health',
        'request_method' => 'GET',
        'interval_seconds' => 60,
        'timeout_seconds' => 5,
        'retry_limit' => 0,
        'follow_redirects' => true,
        'custom_headers' => json_encode(['Authorization' => 'Bearer '.$headerSecret]),
        'auth_username' => 'monitor-user',
        'auth_password' => $basicAuthSecret,
        'accepted_http_statuses' => '200-299',
        'downtime_webhook_urls' => $webhookUrl,
        'region' => 'Europe',
    ])->assertRedirect();

    $monitor = $user->monitors()->firstOrFail();
    $rawMonitor = DB::table('monitors')->where('id', $monitor->id)->first();

    expect($rawMonitor->custom_headers)->not->toContain($headerSecret)
        ->and($rawMonitor->downtime_webhook_urls)->not->toContain('secret-webhook-token')
        ->and($rawMonitor->auth_password)->not->toContain($basicAuthSecret)
        ->and($monitor->custom_headers['Authorization'])->toBe('Bearer '.$headerSecret)
        ->and($monitor->downtime_webhook_urls)->toBe([$webhookUrl]);

    $this->actingAs($user)
        ->get(route('monitors.edit', $monitor))
        ->assertOk()
        ->assertDontSee($basicAuthSecret)
        ->assertDontSee($headerSecret)
        ->assertDontSee('secret-webhook-token')
        ->assertInertia(fn (Assert $page) => $page
            ->where('monitor.auth_password', '')
            ->where('monitor.custom_headers', json_encode(['Authorization' => ''], JSON_PRETTY_PRINT))
            ->where('monitor.downtime_webhook_urls', '[stored webhook 1 at hooks.example.com]'));

    $this->actingAs($user)->put(route('monitors.update', $monitor), [
        'name' => $monitor->name,
        'type' => Monitor::TYPE_HTTP,
        'target' => $monitor->target,
        'request_method' => 'GET',
        'interval_seconds' => 60,
        'timeout_seconds' => 5,
        'retry_limit' => 0,
        'follow_redirects' => true,
        'custom_headers' => json_encode(['Authorization' => '']),
        'auth_username' => 'monitor-user',
        'auth_password' => '',
        'accepted_http_statuses' => '200-299',
        'downtime_webhook_urls' => '[stored webhook 1 at hooks.example.com]',
        'region' => 'Europe',
    ])->assertRedirect();

    $monitor->refresh();

    expect($monitor->auth_password)->toBe($basicAuthSecret)
        ->and($monitor->custom_headers['Authorization'])->toBe('Bearer '.$headerSecret)
        ->and($monitor->downtime_webhook_urls)->toBe([$webhookUrl]);
});

it('rejects private workspace webhooks and non-slack destinations for slack integrations', function () {
    $user = User::factory()->premium()->create();

    $this->actingAs($user)->post('/workspace-integrations', [
        'provider' => WorkspaceIntegration::PROVIDER_WEBHOOK,
        'name' => 'Private workflow',
        'webhook_url' => 'http://127.0.0.1/workflow',
        'enabled' => true,
        'events' => ['monitor.down'],
    ])->assertSessionHasErrors('webhook_url');

    $this->actingAs($user)->post('/workspace-integrations', [
        'provider' => WorkspaceIntegration::PROVIDER_SLACK,
        'name' => 'Impersonated Slack',
        'webhook_url' => 'https://example.com/services/fake',
        'enabled' => true,
        'events' => ['monitor.down'],
    ])->assertSessionHasErrors('webhook_url');

    expect($user->workspaceIntegrations()->count())->toBe(0);
});

it('keeps webhook secrets out of notification logs and queued job payloads', function () {
    Queue::fake();

    $webhookUrl = 'https://hooks.example.com/events/private-delivery-token';
    $user = User::factory()->premium()->create();
    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Secure webhook monitor',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_DOWN,
        'target' => 'https://example.com/health',
        'request_method' => 'GET',
        'interval_seconds' => 60,
        'timeout_seconds' => 5,
        'retry_limit' => 0,
        'downtime_webhook_urls' => [$webhookUrl],
        'region' => 'Europe',
    ]);
    $incident = Incident::query()->create([
        'monitor_id' => $monitor->id,
        'started_at' => now(),
        'type' => Incident::TYPE_DOWNTIME,
        'severity' => Incident::SEVERITY_MAJOR,
        'reason' => 'Connection failed.',
    ]);

    app(WebhookNotificationService::class)->sendDownAlert(
        $monitor->fresh('user'),
        $incident,
    );

    $rawPayload = (string) DB::table('notification_logs')->value('payload');
    $log = NotificationLog::query()->firstOrFail();

    expect($rawPayload)->not->toContain('private-delivery-token')
        ->and($log->payload)->toMatchArray([
            'url_host' => 'hooks.example.com',
            'event' => 'monitor.down',
        ])
        ->and($log->toArray())->not->toHaveKey('payload')
        ->and($monitor->toArray())->not->toHaveKeys([
            'auth_password',
            'custom_headers',
            'downtime_webhook_urls',
            'heartbeat_token',
        ]);

    Queue::assertPushed(SendMonitorWebhookNotificationJob::class, function ($job) use ($webhookUrl): bool {
        return ! str_contains(serialize($job), $webhookUrl)
            && $job->webhookUrl === null
            && $job->webhookUrlHash === hash('sha256', $webhookUrl);
    });
});

it('requires recent authentication before credential billing and profile changes', function () {
    $user = User::factory()->premium()->create([
        'name' => 'Original Name',
        'password_login_enabled' => true,
    ]);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => 0])
        ->post('/api-tokens', ['name' => 'Stolen session token'])
        ->assertRedirect(route('password.confirm'));

    $this->patch(route('profile.update'), [
        'name' => 'Attacker Controlled',
        'email' => 'attacker@example.com',
    ])->assertRedirect(route('password.confirm'));

    $this->post(route('membership.portal'))
        ->assertRedirect(route('password.confirm'));

    expect($user->apiTokens()->count())->toBe(0)
        ->and($user->fresh()->name)->toBe('Original Name');
});

it('blocks private destinations at execution time and revalidates redirects', function () {
    Http::fake([
        'https://example.com/start' => Http::response('', 302, [
            'Location' => 'http://127.0.0.1/internal',
        ]),
    ]);

    $user = User::factory()->premium()->create();
    $legacyPrivateMonitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Legacy private target',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'http://127.0.0.1/internal',
        'request_method' => 'GET',
        'interval_seconds' => 60,
        'timeout_seconds' => 5,
        'retry_limit' => 0,
        'region' => 'Europe',
    ]);

    $privateOutcome = app(MonitorRunner::class)->runMonitor(
        $legacyPrivateMonitor->fresh(['notificationContacts', 'user']),
    );

    expect($privateOutcome->status)->toBe('down')
        ->and($privateOutcome->errorType)->toBe('InvalidArgumentException');
    Http::assertNothingSent();

    $redirectMonitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Redirect target',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com/start',
        'request_method' => 'GET',
        'interval_seconds' => 60,
        'timeout_seconds' => 5,
        'retry_limit' => 0,
        'follow_redirects' => true,
        'region' => 'Europe',
    ]);

    $redirectOutcome = app(MonitorRunner::class)->runMonitor(
        $redirectMonitor->fresh(['notificationContacts', 'user']),
    );

    expect($redirectOutcome->status)->toBe('down');
    Http::assertSentCount(1);
});

it('coalesces heartbeat history and rate limits abusive public writes', function () {
    CarbonImmutable::setTestNow('2026-07-15 12:00:00');

    $user = User::factory()->create();
    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Batch heartbeat',
        'type' => Monitor::TYPE_HEARTBEAT,
        'status' => Monitor::STATUS_UP,
        'interval_seconds' => 60,
        'timeout_seconds' => 5,
        'retry_limit' => 0,
        'region' => 'Europe',
    ]);
    $url = route('heartbeat.store', $monitor->heartbeat_token, absolute: false);

    $this->postJson($url)->assertOk()->assertJsonMissingPath('monitor');
    CarbonImmutable::setTestNow('2026-07-15 12:00:30');
    $this->postJson($url)->assertOk();

    expect(HeartbeatEvent::query()->where('monitor_id', $monitor->id)->count())->toBe(1);

    CarbonImmutable::setTestNow('2026-07-15 12:01:01');
    $this->postJson($url)->assertOk();

    expect(HeartbeatEvent::query()->where('monitor_id', $monitor->id)->count())->toBe(2);

    $rateLimitedMonitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Rate limited heartbeat',
        'type' => Monitor::TYPE_HEARTBEAT,
        'status' => Monitor::STATUS_UP,
        'interval_seconds' => 60,
        'timeout_seconds' => 5,
        'retry_limit' => 0,
        'region' => 'Europe',
    ]);
    $rateLimitedUrl = route('heartbeat.store', $rateLimitedMonitor->heartbeat_token, absolute: false);

    foreach (range(1, 12) as $attempt) {
        $this->postJson($rateLimitedUrl)->assertOk();
    }

    $this->postJson($rateLimitedUrl)->assertTooManyRequests();
});

it('rate limits rotating invalid heartbeat tokens by source address', function () {
    config()->set('realuptime.security.heartbeat_ip_rate_limit_per_minute', 2);

    foreach (range(1, 2) as $attempt) {
        $this->postJson('/heartbeat/'.Str::ulid())->assertNotFound();
    }

    $this->postJson('/heartbeat/'.Str::ulid())->assertTooManyRequests();
});

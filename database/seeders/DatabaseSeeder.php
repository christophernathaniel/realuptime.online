<?php

namespace Database\Seeders;

use App\Models\CheckResult;
use App\Models\Incident;
use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use App\Models\NotificationContact;
use App\Models\NotificationLog;
use App\Models\StatusPage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->isLocal() && ! filter_var((string) env('REALUPTIME_DEMO_DATA', false), FILTER_VALIDATE_BOOL)) {
            return;
        }

        $user = User::query()->firstOrCreate([
            'email' => 'test@example.com',
        ], [
            'name' => 'Test User',
            'password' => 'password',
            'is_admin' => true,
        ]);

        if (! $user->is_admin) {
            $user->forceFill(['is_admin' => true])->save();
        }

        $primaryContact = NotificationContact::query()->firstOrCreate([
            'user_id' => $user->id,
            'email' => $user->email,
        ], [
            'name' => $user->name,
            'enabled' => true,
            'is_primary' => true,
        ]);

        $opsContact = NotificationContact::query()->firstOrCreate([
            'user_id' => $user->id,
            'email' => 'ops@example.com',
        ], [
            'name' => 'Operations',
            'enabled' => true,
            'is_primary' => false,
        ]);

        $now = CarbonImmutable::now();

        $oxts = Monitor::query()->firstOrCreate([
            'user_id' => $user->id,
            'name' => 'oxts.com',
        ], [
            'type' => Monitor::TYPE_HTTP,
            'status' => Monitor::STATUS_UP,
            'target' => 'https://oxts.com',
            'request_method' => 'GET',
            'interval_seconds' => 300,
            'timeout_seconds' => 30,
            'retry_limit' => 2,
            'follow_redirects' => true,
            'expected_status_code' => 200,
            'region' => 'North America',
            'last_checked_at' => $now->subMinutes(3),
            'last_status_changed_at' => $now->subMinutes(48),
            'last_response_time_ms' => 3872,
            'last_http_status' => 200,
            'domain_expires_at' => $now->addMonths(9),
            'domain_registrar' => 'Cloudflare Registrar',
            'domain_checked_at' => $now->subHours(3),
            'ssl_expires_at' => $now->addDays(271),
            'ssl_issuer' => 'Let’s Encrypt',
            'ssl_checked_at' => $now->subHours(3),
        ]);

        $api = Monitor::query()->firstOrCreate([
            'user_id' => $user->id,
            'name' => 'api.realuptime.test',
        ], [
            'type' => Monitor::TYPE_KEYWORD,
            'status' => Monitor::STATUS_UP,
            'target' => 'https://example.com/health',
            'request_method' => 'GET',
            'interval_seconds' => 300,
            'timeout_seconds' => 20,
            'retry_limit' => 2,
            'follow_redirects' => true,
            'expected_status_code' => 200,
            'expected_keyword' => 'ok',
            'keyword_match_type' => 'contains',
            'region' => 'Europe',
            'last_checked_at' => $now->subMinutes(4),
            'last_status_changed_at' => $now->subHours(7),
            'last_response_time_ms' => 812,
            'last_http_status' => 200,
            'domain_expires_at' => $now->addMonths(4),
            'domain_registrar' => 'Namecheap',
            'domain_checked_at' => $now->subHours(5),
            'ssl_expires_at' => $now->addDays(89),
            'ssl_issuer' => 'Let’s Encrypt',
            'ssl_checked_at' => $now->subHours(5),
        ]);

        $heartbeat = Monitor::query()->firstOrCreate([
            'user_id' => $user->id,
            'name' => 'Nightly backup job',
        ], [
            'type' => Monitor::TYPE_HEARTBEAT,
            'status' => Monitor::STATUS_UP,
            'target' => 'Nightly backup job',
            'interval_seconds' => 3600,
            'timeout_seconds' => 30,
            'retry_limit' => 1,
            'heartbeat_grace_seconds' => 900,
            'region' => 'North America',
            'last_checked_at' => $now->subMinutes(10),
            'last_status_changed_at' => $now->subDays(2),
            'last_heartbeat_at' => $now->subMinutes(20),
        ]);

        foreach ([$oxts, $api, $heartbeat] as $monitor) {
            $monitor->notificationContacts()->syncWithoutDetaching([$primaryContact->id, $opsContact->id]);
        }

        $this->seedChecks($oxts, $now, 3872);
        $this->seedChecks($api, $now, 812, range(188, 191));
        $this->seedHeartbeatHistory($heartbeat, $now);

        if (! $api->incidents()->exists()) {
            $incidentStartedAt = $now->subHours(8)->subMinutes(20);
            $incidentResolvedAt = $now->subHours(7)->subMinutes(40);
            $latestCheck = $api->checkResults()->where('status', 'down')->latest('checked_at')->first();

            $incident = Incident::query()->create([
                'monitor_id' => $api->id,
                'latest_check_result_id' => $latestCheck?->id,
                'started_at' => $incidentStartedAt,
                'resolved_at' => $incidentResolvedAt,
                'duration_seconds' => $incidentStartedAt->diffInSeconds($incidentResolvedAt),
                'reason' => 'Expected keyword validation failed for "ok".',
                'error_type' => 'keyword_mismatch',
                'http_status_code' => 200,
                'meta' => [
                    'suppressed_by_maintenance' => false,
                ],
            ]);

            NotificationLog::query()->create([
                'monitor_id' => $api->id,
                'incident_id' => $incident->id,
                'notification_contact_id' => $opsContact->id,
                'channel' => 'email',
                'type' => 'down',
                'subject' => 'api.realuptime.test is down',
                'status' => 'sent',
                'sent_at' => $incidentStartedAt->addMinutes(5),
                'payload' => ['email' => $opsContact->email],
            ]);

            NotificationLog::query()->create([
                'monitor_id' => $api->id,
                'incident_id' => $incident->id,
                'notification_contact_id' => $opsContact->id,
                'channel' => 'email',
                'type' => 'recovered',
                'subject' => 'api.realuptime.test is back up',
                'status' => 'sent',
                'sent_at' => $incidentResolvedAt->addMinutes(2),
                'payload' => ['email' => $opsContact->email],
            ]);
        }

        $statusPage = StatusPage::query()->firstOrCreate([
            'user_id' => $user->id,
            'slug' => 'primary-status',
        ], [
            'name' => 'Primary status',
            'headline' => 'System status',
            'description' => 'Live uptime and incident information for public-facing services.',
            'published' => true,
        ]);

        $statusPage->monitors()->syncWithoutDetaching([
            $oxts->id => ['sort_order' => 0],
            $api->id => ['sort_order' => 1],
            $heartbeat->id => ['sort_order' => 2],
        ]);

        $maintenance = MaintenanceWindow::query()->firstOrCreate([
            'user_id' => $user->id,
            'title' => 'Edge network certificate rotation',
        ], [
            'message' => 'Routine certificate rotation for public edge services.',
            'starts_at' => $now->addDay()->setTime(1, 0),
            'ends_at' => $now->addDay()->setTime(2, 30),
            'status' => MaintenanceWindow::STATUS_SCHEDULED,
            'notify_contacts' => true,
        ]);

        $maintenance->monitors()->syncWithoutDetaching([$oxts->id, $api->id]);
    }

    /**
     * @param  array<int, int>  $downSteps
     */
    protected function seedChecks(Monitor $monitor, CarbonImmutable $now, int $responseTime, array $downSteps = []): void
    {
        if ($monitor->checkResults()->exists()) {
            return;
        }

        foreach (range(0, 287) as $step) {
            $checkedAt = $now->subMinutes(5 * (287 - $step));
            $isDown = in_array($step, $downSteps, true);

            CheckResult::query()->create([
                'monitor_id' => $monitor->id,
                'status' => $isDown ? 'down' : 'up',
                'checked_at' => $checkedAt,
                'attempts' => $isDown ? 3 : 1,
                'response_time_ms' => $isDown ? null : $responseTime,
                'http_status_code' => $isDown ? 200 : 200,
                'error_type' => $isDown ? 'keyword_mismatch' : null,
                'error_message' => $isDown ? 'Expected keyword validation failed for "ok".' : null,
                'keyword_match' => $isDown ? false : true,
                'meta' => [
                    'region' => $monitor->region,
                ],
            ]);
        }
    }

    protected function seedHeartbeatHistory(Monitor $monitor, CarbonImmutable $now): void
    {
        if ($monitor->checkResults()->exists()) {
            return;
        }

        foreach (range(0, 47) as $step) {
            $checkedAt = $now->subHours(47 - $step);

            CheckResult::query()->create([
                'monitor_id' => $monitor->id,
                'status' => 'up',
                'checked_at' => $checkedAt,
                'attempts' => 1,
                'meta' => [
                    'last_heartbeat_at' => $checkedAt->subMinutes(15)->toIso8601String(),
                ],
            ]);
        }
    }
}

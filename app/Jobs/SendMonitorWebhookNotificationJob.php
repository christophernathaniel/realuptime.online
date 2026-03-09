<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Models\Monitor;
use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class SendMonitorWebhookNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public int $notificationLogId,
        public int $monitorId,
        public int $incidentId,
        public string $webhookUrl,
    ) {
        $this->onQueue(config('realuptime.queues.notifications'));
    }

    public function handle(): void
    {
        $log = NotificationLog::query()->find($this->notificationLogId);
        $monitor = Monitor::query()->with('user')->find($this->monitorId);
        $incident = Incident::query()->find($this->incidentId);

        if (! $log) {
            return;
        }

        if (! $monitor || ! $incident) {
            $log->forceFill([
                'status' => 'failed',
                'failure_message' => 'Webhook target no longer exists.',
            ])->save();

            return;
        }

        if (! $monitor->user?->supportsDowntimeWebhooks()) {
            $log->forceFill([
                'status' => 'failed',
                'failure_message' => 'Workspace membership no longer includes downtime webhooks.',
            ])->save();

            return;
        }

        if (! in_array($this->webhookUrl, $monitor->downtime_webhook_urls ?? [], true)) {
            $log->forceFill([
                'status' => 'failed',
                'failure_message' => 'Webhook URL is no longer configured on this monitor.',
            ])->save();

            return;
        }

        $payload = [
            'event' => 'monitor.down',
            'sent_at' => now()->toIso8601String(),
            'monitor' => [
                'id' => $monitor->id,
                'name' => $monitor->name,
                'type' => $monitor->type,
                'status' => $monitor->status,
                'target' => $monitor->target,
                'region' => $monitor->region,
                'url' => route('monitors.show', $monitor),
            ],
            'incident' => [
                'id' => $incident->id,
                'type' => $incident->type,
                'severity' => $incident->severity,
                'started_at' => $incident->started_at?->toIso8601String(),
                'reason' => $incident->reason,
                'error_type' => $incident->error_type,
                'http_status_code' => $incident->http_status_code,
            ],
            'workspace' => [
                'id' => $monitor->user_id,
                'name' => $monitor->user?->name,
                'plan' => $monitor->user?->membershipPlan()->value,
            ],
        ];

        try {
            $response = Http::asJson()
                ->acceptJson()
                ->timeout(10)
                ->withHeaders([
                    'User-Agent' => 'RealUptime Webhooks',
                ])
                ->post($this->webhookUrl, $payload);

            if (! $response->successful()) {
                throw new RuntimeException(sprintf('Webhook responded with HTTP %d.', $response->status()));
            }

            $log->forceFill([
                'status' => 'sent',
                'sent_at' => now(),
                'payload' => [
                    ...($log->payload ?? []),
                    'response_status' => $response->status(),
                ],
            ])->save();
        } catch (Throwable $exception) {
            $log->forceFill([
                'status' => 'failed',
                'failure_message' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }
}

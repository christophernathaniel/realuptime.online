<?php

namespace App\Services\Monitoring;

use App\Jobs\SendMonitorWebhookNotificationJob;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\NotificationLog;

class WebhookNotificationService
{
    public function sendDownAlert(Monitor $monitor, Incident $incident): void
    {
        if (! $monitor->user?->supportsDowntimeWebhooks()) {
            return;
        }

        $webhookUrls = collect($monitor->downtime_webhook_urls ?? [])
            ->map(fn (mixed $url) => trim((string) $url))
            ->filter()
            ->unique()
            ->take(5)
            ->values();

        foreach ($webhookUrls as $webhookUrl) {
            $log = NotificationLog::query()->create([
                'monitor_id' => $monitor->id,
                'incident_id' => $incident->id,
                'channel' => 'webhook',
                'type' => 'down',
                'subject' => sprintf('Downtime webhook for %s', $monitor->name),
                'status' => 'pending',
                'payload' => [
                    'url' => $webhookUrl,
                    'event' => 'monitor.down',
                ],
            ]);

            SendMonitorWebhookNotificationJob::dispatch(
                notificationLogId: $log->id,
                monitorId: $monitor->id,
                incidentId: $incident->id,
                webhookUrl: $webhookUrl,
            )->afterCommit();
        }
    }
}

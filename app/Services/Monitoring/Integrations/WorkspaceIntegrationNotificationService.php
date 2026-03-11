<?php

namespace App\Services\Monitoring\Integrations;

use App\Jobs\SendWorkspaceIntegrationNotificationJob;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\NotificationLog;
use App\Models\WorkspaceIntegration;

class WorkspaceIntegrationNotificationService
{
    public const EVENT_MONITOR_DOWN = 'monitor.down';

    public const EVENT_MONITOR_RECOVERED = 'monitor.recovered';

    public function __construct(
        protected WorkspaceIntegrationProviderManager $providers,
        protected WorkspaceIntegrationPayloadFactory $payloadFactory,
    ) {}

    public function sendDownAlert(Monitor $monitor, Incident $incident): void
    {
        $this->dispatch(self::EVENT_MONITOR_DOWN, $monitor, $incident);
    }

    public function sendRecoveryAlert(Monitor $monitor, Incident $incident): void
    {
        $this->dispatch(self::EVENT_MONITOR_RECOVERED, $monitor, $incident);
    }

    protected function dispatch(string $event, Monitor $monitor, Incident $incident): void
    {
        $workspace = $monitor->relationLoaded('user')
            ? $monitor->user
            : $monitor->user()->first();

        if (! $workspace || ! $workspace->allowsAdvancedWorkspaceFeatures()) {
            return;
        }

        $integrations = $workspace->workspaceIntegrations()
            ->where('status', WorkspaceIntegration::STATUS_ACTIVE)
            ->orderBy('created_at')
            ->get();

        foreach ($integrations as $integration) {
            if (! $integration->supportsEvent($event)) {
                continue;
            }

            $provider = $this->providers->for($integration);

            if (! in_array($event, $provider->supportedEvents(), true)) {
                continue;
            }

            $payload = $this->payloadFactory->make($event, $workspace, $monitor, $incident);
            $log = NotificationLog::query()->create([
                'monitor_id' => $monitor->id,
                'incident_id' => $incident->id,
                'integration_id' => $integration->id,
                'channel' => $integration->provider,
                'type' => $this->typeFromEvent($event),
                'subject' => $provider->subject($event, $payload),
                'status' => 'pending',
                'payload' => [
                    'event' => $event,
                    'provider' => $integration->provider,
                    'integration_name' => $integration->name,
                ],
            ]);

            SendWorkspaceIntegrationNotificationJob::dispatch(
                notificationLogId: $log->id,
                integrationId: $integration->id,
                event: $event,
                payload: $payload,
            )->afterCommit();
        }
    }

    protected function typeFromEvent(string $event): string
    {
        return match ($event) {
            self::EVENT_MONITOR_RECOVERED => 'recovered',
            default => 'down',
        };
    }
}

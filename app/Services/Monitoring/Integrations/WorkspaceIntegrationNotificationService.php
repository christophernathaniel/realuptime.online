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

    public const EVENT_MONITOR_TEST = 'monitor.test';

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

    public function sendTest(Monitor $monitor): void
    {
        $workspace = $monitor->relationLoaded('user')
            ? $monitor->user
            : $monitor->user()->first();

        if (! $workspace || ! $workspace->allowsAdvancedWorkspaceFeatures()) {
            return;
        }

        $this->dispatch(
            self::EVENT_MONITOR_TEST,
            $monitor,
            payload: $this->payloadFactory->makeTest($workspace, $monitor),
            ignoreScopes: true,
        );
    }

    public function sendIntegrationTest(WorkspaceIntegration $integration, Monitor $monitor): void
    {
        $workspace = $monitor->relationLoaded('user')
            ? $monitor->user
            : $monitor->user()->first();

        if (! $workspace || ! $workspace->allowsAdvancedWorkspaceFeatures()) {
            return;
        }

        $this->dispatch(
            self::EVENT_MONITOR_TEST,
            $monitor,
            integration: $integration,
            payload: $this->payloadFactory->makeTest($workspace, $monitor),
            ignoreScopes: true,
        );
    }

    protected function dispatch(
        string $event,
        Monitor $monitor,
        ?Incident $incident = null,
        ?WorkspaceIntegration $integration = null,
        ?array $payload = null,
        bool $ignoreScopes = false,
    ): void
    {
        $workspace = $monitor->relationLoaded('user')
            ? $monitor->user
            : $monitor->user()->first();

        if (! $workspace || ! $workspace->allowsAdvancedWorkspaceFeatures()) {
            return;
        }

        $integrations = $integration
            ? collect([$integration])
            : $workspace->workspaceIntegrations()
                ->where('status', WorkspaceIntegration::STATUS_ACTIVE)
                ->orderBy('created_at')
                ->get();

        foreach ($integrations as $integration) {
            if (! $ignoreScopes && ! $integration->supportsEvent($event)) {
                continue;
            }

            $provider = $this->providers->for($integration);

            if (! in_array($event, $provider->supportedEvents(), true)) {
                continue;
            }

            $deliveryPayload = $payload ?? $this->payloadFactory->make($event, $workspace, $monitor, $incident);
            $log = NotificationLog::query()->create([
                'monitor_id' => $monitor->id,
                'incident_id' => $incident?->id,
                'integration_id' => $integration->id,
                'channel' => $integration->provider,
                'type' => $this->typeFromEvent($event),
                'subject' => $provider->subject($event, $deliveryPayload),
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
                payload: $deliveryPayload,
            )->afterCommit();
        }
    }

    protected function typeFromEvent(string $event): string
    {
        return match ($event) {
            self::EVENT_MONITOR_RECOVERED => 'recovered',
            self::EVENT_MONITOR_TEST => 'test',
            default => 'down',
        };
    }
}

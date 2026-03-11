<?php

namespace App\Services\Monitoring\Integrations;

use App\Models\WorkspaceIntegration;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WebhookWorkspaceIntegrationProvider implements WorkspaceIntegrationProvider
{
    public function provider(): string
    {
        return WorkspaceIntegration::PROVIDER_WEBHOOK;
    }

    public function label(): string
    {
        return 'Webhook';
    }

    public function supportedEvents(): array
    {
        return [
            WorkspaceIntegrationNotificationService::EVENT_MONITOR_DOWN,
            WorkspaceIntegrationNotificationService::EVENT_MONITOR_RECOVERED,
            WorkspaceIntegrationNotificationService::EVENT_MONITOR_TEST,
        ];
    }

    public function subject(string $event, array $payload): string
    {
        $monitorName = data_get($payload, 'monitor.name', 'Monitor');

        return match ($event) {
            WorkspaceIntegrationNotificationService::EVENT_MONITOR_DOWN => sprintf('Webhook alert: %s is down', $monitorName),
            WorkspaceIntegrationNotificationService::EVENT_MONITOR_RECOVERED => sprintf('Webhook alert: %s recovered', $monitorName),
            WorkspaceIntegrationNotificationService::EVENT_MONITOR_TEST => sprintf('Webhook test alert for %s', $monitorName),
            default => sprintf('Webhook alert: %s', $monitorName),
        };
    }

    public function send(WorkspaceIntegration $integration, string $event, array $payload): array
    {
        $webhookUrl = trim((string) data_get($integration->config, 'webhook_url', ''));

        if ($webhookUrl === '') {
            throw new RuntimeException('Webhook URL is missing.');
        }

        $response = Http::asJson()
            ->acceptJson()
            ->timeout(10)
            ->withHeaders([
                'User-Agent' => 'RealUptime Workflow Webhooks',
            ])
            ->post($webhookUrl, $this->workflowPayload($integration, $event, $payload));

        if (! $response->successful()) {
            throw new RuntimeException(sprintf('Webhook responded with HTTP %d.', $response->status()));
        }

        return [
            'response_status' => $response->status(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function workflowPayload(WorkspaceIntegration $integration, string $event, array $payload): array
    {
        return [
            'event' => $event,
            'event_label' => $this->eventLabel($event),
            'is_test' => (bool) data_get($payload, 'is_test', false),
            'sent_at' => data_get($payload, 'sent_at'),
            'integration_id' => $integration->id,
            'integration_name' => $integration->name,
            'integration_provider' => $integration->provider,
            'workspace_id' => data_get($payload, 'workspace.id'),
            'workspace_name' => data_get($payload, 'workspace.name'),
            'workspace_plan' => data_get($payload, 'workspace.plan'),
            'monitor_id' => data_get($payload, 'monitor.id'),
            'monitor_name' => data_get($payload, 'monitor.name'),
            'monitor_type' => data_get($payload, 'monitor.type'),
            'monitor_status' => data_get($payload, 'monitor.status'),
            'monitor_target' => data_get($payload, 'monitor.target'),
            'monitor_region' => data_get($payload, 'monitor.region'),
            'monitor_url' => data_get($payload, 'monitor.url'),
            'incident_id' => data_get($payload, 'incident.id'),
            'incident_type' => data_get($payload, 'incident.type'),
            'incident_severity' => data_get($payload, 'incident.severity'),
            'incident_started_at' => data_get($payload, 'incident.started_at'),
            'incident_resolved_at' => data_get($payload, 'incident.resolved_at'),
            'incident_reason' => data_get($payload, 'incident.reason'),
            'incident_error_type' => data_get($payload, 'incident.error_type'),
            'incident_http_status_code' => data_get($payload, 'incident.http_status_code'),
            'incident_url' => data_get($payload, 'incident.url'),
        ];
    }

    protected function eventLabel(string $event): string
    {
        return match ($event) {
            WorkspaceIntegrationNotificationService::EVENT_MONITOR_DOWN => 'Downtime opened',
            WorkspaceIntegrationNotificationService::EVENT_MONITOR_RECOVERED => 'Downtime recovered',
            WorkspaceIntegrationNotificationService::EVENT_MONITOR_TEST => 'Test alert',
            default => 'Monitor alert',
        };
    }
}

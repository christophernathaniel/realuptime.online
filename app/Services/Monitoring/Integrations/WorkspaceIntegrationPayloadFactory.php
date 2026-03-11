<?php

namespace App\Services\Monitoring\Integrations;

use App\Models\Incident;
use App\Models\Monitor;
use App\Models\User;

class WorkspaceIntegrationPayloadFactory
{
    /**
     * @return array<string, mixed>
     */
    public function make(string $event, User $workspace, Monitor $monitor, ?Incident $incident = null): array
    {
        $reason = $incident?->reason;

        return [
            'event' => $event,
            'is_test' => false,
            'sent_at' => now()->toIso8601String(),
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'plan' => $workspace->membershipPlan()->value,
            ],
            'monitor' => [
                'id' => $monitor->id,
                'name' => $monitor->name,
                'type' => $monitor->type,
                'status' => $monitor->status,
                'target' => $monitor->target,
                'region' => $monitor->region,
                'url' => route('monitors.show', $monitor),
            ],
            'incident' => $incident ? [
                'id' => $incident->id,
                'type' => $incident->type,
                'severity' => $incident->severity,
                'started_at' => $incident->started_at?->toIso8601String(),
                'resolved_at' => $incident->resolved_at?->toIso8601String(),
                'reason' => $reason,
                'error_type' => $incident->error_type,
                'http_status_code' => $incident->http_status_code,
                'url' => route('incidents.show', $incident),
            ] : null,
            'message' => [
                'title' => $this->messageTitle($event),
                'summary' => $this->messageSummary($event, $monitor),
                'status_label' => $this->statusLabel($event),
                'status_tone' => $this->statusTone($event),
                'icon' => $this->statusIcon($event),
                'reason' => $reason,
                'link_label' => 'Open monitor',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function makeTest(User $workspace, Monitor $monitor): array
    {
        $reason = 'This is a test alert from RealUptime.';

        return [
            'event' => WorkspaceIntegrationNotificationService::EVENT_MONITOR_TEST,
            'is_test' => true,
            'sent_at' => now()->toIso8601String(),
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'plan' => $workspace->membershipPlan()->value,
            ],
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
                'id' => null,
                'type' => 'test',
                'severity' => 'info',
                'started_at' => now()->toIso8601String(),
                'resolved_at' => null,
                'reason' => $reason,
                'error_type' => 'test',
                'http_status_code' => null,
                'url' => null,
            ],
            'message' => [
                'title' => $this->messageTitle(WorkspaceIntegrationNotificationService::EVENT_MONITOR_TEST),
                'summary' => $this->messageSummary(WorkspaceIntegrationNotificationService::EVENT_MONITOR_TEST, $monitor),
                'status_label' => $this->statusLabel(WorkspaceIntegrationNotificationService::EVENT_MONITOR_TEST),
                'status_tone' => $this->statusTone(WorkspaceIntegrationNotificationService::EVENT_MONITOR_TEST),
                'icon' => $this->statusIcon(WorkspaceIntegrationNotificationService::EVENT_MONITOR_TEST),
                'reason' => $reason,
                'link_label' => 'Open monitor',
            ],
        ];
    }

    protected function messageTitle(string $event): string
    {
        return match ($event) {
            WorkspaceIntegrationNotificationService::EVENT_MONITOR_DOWN => 'Website issue detected',
            WorkspaceIntegrationNotificationService::EVENT_MONITOR_RECOVERED => 'Website recovered',
            WorkspaceIntegrationNotificationService::EVENT_MONITOR_TEST => 'Test alert',
            default => 'Monitor alert',
        };
    }

    protected function messageSummary(string $event, Monitor $monitor): string
    {
        return match ($event) {
            WorkspaceIntegrationNotificationService::EVENT_MONITOR_DOWN => sprintf('%s is currently unavailable.', $monitor->name),
            WorkspaceIntegrationNotificationService::EVENT_MONITOR_RECOVERED => sprintf('%s is back up and responding again.', $monitor->name),
            WorkspaceIntegrationNotificationService::EVENT_MONITOR_TEST => sprintf('%s sent a test alert from RealUptime.', $monitor->name),
            default => sprintf('Update for %s.', $monitor->name),
        };
    }

    protected function statusLabel(string $event): string
    {
        return match ($event) {
            WorkspaceIntegrationNotificationService::EVENT_MONITOR_DOWN => 'Down',
            WorkspaceIntegrationNotificationService::EVENT_MONITOR_RECOVERED => 'Up',
            WorkspaceIntegrationNotificationService::EVENT_MONITOR_TEST => 'Test',
            default => 'Update',
        };
    }

    protected function statusTone(string $event): string
    {
        return match ($event) {
            WorkspaceIntegrationNotificationService::EVENT_MONITOR_DOWN => 'danger',
            WorkspaceIntegrationNotificationService::EVENT_MONITOR_RECOVERED => 'success',
            WorkspaceIntegrationNotificationService::EVENT_MONITOR_TEST => 'info',
            default => 'default',
        };
    }

    protected function statusIcon(string $event): string
    {
        return match ($event) {
            WorkspaceIntegrationNotificationService::EVENT_MONITOR_DOWN => ':red_circle:',
            WorkspaceIntegrationNotificationService::EVENT_MONITOR_RECOVERED => ':large_green_circle:',
            WorkspaceIntegrationNotificationService::EVENT_MONITOR_TEST => ':large_blue_circle:',
            default => ':information_source:',
        };
    }
}

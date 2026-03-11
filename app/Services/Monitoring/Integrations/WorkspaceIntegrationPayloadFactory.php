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
        return [
            'event' => $event,
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
                'reason' => $incident->reason,
                'error_type' => $incident->error_type,
                'http_status_code' => $incident->http_status_code,
                'url' => route('incidents.show', $incident),
            ] : null,
        ];
    }
}

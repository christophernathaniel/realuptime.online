<?php

namespace App\Services\Monitoring\Integrations;

use App\Models\WorkspaceIntegration;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SlackWorkspaceIntegrationProvider implements WorkspaceIntegrationProvider
{
    public function provider(): string
    {
        return WorkspaceIntegration::PROVIDER_SLACK;
    }

    public function label(): string
    {
        return 'Slack';
    }

    public function supportedEvents(): array
    {
        return [
            WorkspaceIntegrationNotificationService::EVENT_MONITOR_DOWN,
            WorkspaceIntegrationNotificationService::EVENT_MONITOR_RECOVERED,
        ];
    }

    public function subject(string $event, array $payload): string
    {
        $monitorName = data_get($payload, 'monitor.name', 'Monitor');

        return match ($event) {
            WorkspaceIntegrationNotificationService::EVENT_MONITOR_DOWN => sprintf('Slack alert: %s is down', $monitorName),
            WorkspaceIntegrationNotificationService::EVENT_MONITOR_RECOVERED => sprintf('Slack alert: %s recovered', $monitorName),
            default => sprintf('Slack alert: %s', $monitorName),
        };
    }

    public function send(WorkspaceIntegration $integration, string $event, array $payload): array
    {
        $webhookUrl = trim((string) data_get($integration->config, 'webhook_url', ''));

        if ($webhookUrl === '') {
            throw new RuntimeException('Slack webhook URL is missing.');
        }

        $response = Http::asJson()
            ->acceptJson()
            ->timeout(10)
            ->withHeaders([
                'User-Agent' => 'RealUptime Slack Integrations',
            ])
            ->post($webhookUrl, [
                'text' => $this->plainText($event, $payload),
                'blocks' => $this->blocks($event, $payload),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(sprintf('Slack responded with HTTP %d.', $response->status()));
        }

        return [
            'response_status' => $response->status(),
        ];
    }

    protected function plainText(string $event, array $payload): string
    {
        $workspaceName = data_get($payload, 'workspace.name', 'Workspace');
        $monitorName = data_get($payload, 'monitor.name', 'Monitor');
        $reason = data_get($payload, 'incident.reason');

        return match ($event) {
            WorkspaceIntegrationNotificationService::EVENT_MONITOR_DOWN => trim(sprintf(
                '[%s] %s is down. %s',
                $workspaceName,
                $monitorName,
                $reason ?? 'Investigate the latest incident.'
            )),
            WorkspaceIntegrationNotificationService::EVENT_MONITOR_RECOVERED => sprintf(
                '[%s] %s has recovered.',
                $workspaceName,
                $monitorName,
            ),
            default => sprintf('[%s] Monitor update for %s.', $workspaceName, $monitorName),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function blocks(string $event, array $payload): array
    {
        $workspaceName = (string) data_get($payload, 'workspace.name', 'Workspace');
        $monitorName = (string) data_get($payload, 'monitor.name', 'Monitor');
        $monitorTarget = (string) data_get($payload, 'monitor.target', 'No target');
        $monitorRegion = (string) data_get($payload, 'monitor.region', 'Unknown region');
        $monitorUrl = data_get($payload, 'monitor.url');
        $incidentUrl = data_get($payload, 'incident.url');
        $reason = (string) data_get($payload, 'incident.reason', 'No reason recorded.');
        $startedAt = (string) data_get($payload, 'incident.started_at', 'Unknown');
        $resolvedAt = (string) data_get($payload, 'incident.resolved_at', 'Unresolved');

        $header = match ($event) {
            WorkspaceIntegrationNotificationService::EVENT_MONITOR_DOWN => ':red_circle: Monitor down',
            WorkspaceIntegrationNotificationService::EVENT_MONITOR_RECOVERED => ':large_green_circle: Monitor recovered',
            default => ':large_blue_circle: Monitor update',
        };

        $summary = match ($event) {
            WorkspaceIntegrationNotificationService::EVENT_MONITOR_DOWN => sprintf('*%s* is down in *%s*.', $monitorName, $workspaceName),
            WorkspaceIntegrationNotificationService::EVENT_MONITOR_RECOVERED => sprintf('*%s* has recovered in *%s*.', $monitorName, $workspaceName),
            default => sprintf('Update for *%s* in *%s*.', $monitorName, $workspaceName),
        };

        $blocks = [
            [
                'type' => 'header',
                'text' => [
                    'type' => 'plain_text',
                    'text' => $header,
                ],
            ],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $summary,
                ],
            ],
            [
                'type' => 'section',
                'fields' => array_values(array_filter([
                    [
                        'type' => 'mrkdwn',
                        'text' => sprintf("*Target*\n%s", $monitorTarget !== '' ? $monitorTarget : 'No target'),
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => sprintf("*Region*\n%s", $monitorRegion),
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => sprintf("*Started*\n%s", $startedAt),
                    ],
                    $event === WorkspaceIntegrationNotificationService::EVENT_MONITOR_RECOVERED
                        ? [
                            'type' => 'mrkdwn',
                            'text' => sprintf("*Resolved*\n%s", $resolvedAt),
                        ]
                        : null,
                ])),
            ],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => sprintf("*Reason*\n%s", $reason),
                ],
            ],
        ];

        $links = array_values(array_filter([
            $monitorUrl ? sprintf('<%s|Open monitor>', $monitorUrl) : null,
            $incidentUrl ? sprintf('<%s|Open incident>', $incidentUrl) : null,
        ]));

        if ($links !== []) {
            $blocks[] = [
                'type' => 'context',
                'elements' => [[
                    'type' => 'mrkdwn',
                    'text' => implode(' • ', $links),
                ]],
            ];
        }

        return $blocks;
    }
}

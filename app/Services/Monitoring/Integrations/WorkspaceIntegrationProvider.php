<?php

namespace App\Services\Monitoring\Integrations;

use App\Models\WorkspaceIntegration;

interface WorkspaceIntegrationProvider
{
    public function provider(): string;

    public function label(): string;

    /**
     * @return array<int, string>
     */
    public function supportedEvents(): array;

    public function subject(string $event, array $payload): string;

    /**
     * @return array<string, mixed>
     */
    public function send(WorkspaceIntegration $integration, string $event, array $payload): array;
}

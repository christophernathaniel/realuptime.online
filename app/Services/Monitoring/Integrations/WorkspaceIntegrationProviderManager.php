<?php

namespace App\Services\Monitoring\Integrations;

use App\Models\WorkspaceIntegration;
use InvalidArgumentException;

class WorkspaceIntegrationProviderManager
{
    public function __construct(
        protected SlackWorkspaceIntegrationProvider $slack,
    ) {}

    public function for(WorkspaceIntegration|string $integration): WorkspaceIntegrationProvider
    {
        $provider = $integration instanceof WorkspaceIntegration ? $integration->provider : $integration;

        return match ($provider) {
            WorkspaceIntegration::PROVIDER_SLACK => $this->slack,
            default => throw new InvalidArgumentException(sprintf('Unsupported integration provider [%s].', $provider)),
        };
    }
}

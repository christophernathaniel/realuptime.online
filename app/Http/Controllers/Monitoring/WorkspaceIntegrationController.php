<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Http\Requests\Monitoring\UpsertWorkspaceIntegrationRequest;
use App\Models\WorkspaceIntegration;
use App\Services\Monitoring\Integrations\WorkspaceIntegrationNotificationService;
use App\Support\WorkspaceResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceIntegrationController extends Controller
{
    public function __construct(
        protected WorkspaceResolver $workspaces,
        protected WorkspaceIntegrationNotificationService $integrations,
    ) {}

    public function store(UpsertWorkspaceIntegrationRequest $request): RedirectResponse
    {
        $workspace = $this->workspaces->current($request);
        $integration = $workspace->workspaceIntegrations()->create($request->integrationData());

        return back()->with('success', sprintf('%s integration added.', ucfirst($integration->provider)));
    }

    public function update(UpsertWorkspaceIntegrationRequest $request, WorkspaceIntegration $workspaceIntegration): RedirectResponse
    {
        abort_unless($workspaceIntegration->user_id === $this->workspaces->current($request)->id, 404);

        $workspaceIntegration->update($request->integrationData());

        return back()->with('success', sprintf('%s integration updated.', ucfirst($workspaceIntegration->provider)));
    }

    public function destroy(WorkspaceIntegration $workspaceIntegration): RedirectResponse
    {
        $request = request();
        abort_unless($workspaceIntegration->user_id === $this->workspaces->current($request)->id, 404);

        $workspaceIntegration->delete();

        return back()->with('success', 'Integration removed.');
    }

    public function test(Request $request, WorkspaceIntegration $workspaceIntegration): RedirectResponse
    {
        $workspace = $this->workspaces->current($request);
        abort_unless($workspaceIntegration->user_id === $workspace->id, 404);

        if (! $workspaceIntegration->isActive()) {
            return back()->with('error', 'Enable the integration before sending a test payload.');
        }

        $monitor = $workspace->monitors()->orderBy('id')->first();

        if (! $monitor) {
            return back()->with('error', 'Create at least one monitor before sending a test payload.');
        }

        $this->integrations->sendIntegrationTest($workspaceIntegration, $monitor->loadMissing('user'));

        return back()->with('success', 'Test webhook queued.');
    }
}

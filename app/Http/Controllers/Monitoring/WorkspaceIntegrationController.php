<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Http\Requests\Monitoring\UpsertWorkspaceIntegrationRequest;
use App\Models\WorkspaceIntegration;
use App\Support\WorkspaceResolver;
use Illuminate\Http\RedirectResponse;

class WorkspaceIntegrationController extends Controller
{
    public function __construct(protected WorkspaceResolver $workspaces) {}

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
}

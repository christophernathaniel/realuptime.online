<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Http\Requests\Monitoring\StoreStatusPageIncidentRequest;
use App\Http\Requests\Monitoring\StoreStatusPageIncidentUpdateRequest;
use App\Models\StatusPage;
use App\Models\StatusPageIncident;
use App\Support\WorkspaceResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StatusPageIncidentController extends Controller
{
    public function __construct(protected WorkspaceResolver $workspaces) {}

    public function store(StoreStatusPageIncidentRequest $request, StatusPage $statusPage): RedirectResponse
    {
        abort_unless($statusPage->user_id === $this->workspaces->current($request)->id, 404);

        $monitorIds = collect($request->validated('monitor_ids', []))
            ->map(fn ($id) => (int) $id)
            ->intersect($statusPage->monitors()->pluck('monitors.id'))
            ->values()
            ->all();

        if ($monitorIds === []) {
            throw ValidationException::withMessages([
                'monitor_ids' => 'Select at least one monitor already attached to this status page.',
            ]);
        }

        DB::transaction(function () use ($request, $statusPage, $monitorIds): void {
            $incident = $statusPage->incidents()->create([
                'user_id' => $this->workspaces->current($request)->id,
                'title' => $request->validated('title'),
                'message' => $request->validated('message'),
                'status' => $request->validated('status'),
                'impact' => $request->validated('impact'),
                'started_at' => now(),
                'resolved_at' => $request->validated('status') === StatusPageIncident::STATUS_RESOLVED ? now() : null,
            ]);

            $incident->monitors()->sync($monitorIds);
            $incident->updates()->create([
                'status' => $request->validated('status'),
                'message' => $request->validated('message'),
            ]);
        });

        return back()->with('success', 'Status page incident created.');
    }

    public function storeUpdate(StoreStatusPageIncidentUpdateRequest $request, StatusPageIncident $statusPageIncident): RedirectResponse
    {
        abort_unless($statusPageIncident->user_id === $this->workspaces->current($request)->id, 404);

        $statusPageIncident->updates()->create([
            'status' => $request->validated('status'),
            'message' => $request->validated('message'),
        ]);

        $statusPageIncident->forceFill([
            'status' => $request->validated('status'),
            'message' => $request->validated('message'),
            'resolved_at' => $request->validated('status') === StatusPageIncident::STATUS_RESOLVED
                ? ($statusPageIncident->resolved_at ?? now())
                : null,
        ])->save();

        return back()->with('success', 'Status page update published.');
    }

    public function destroy(StatusPageIncident $statusPageIncident): RedirectResponse
    {
        abort_unless($statusPageIncident->user_id === $this->workspaces->current(request())->id, 404);

        $statusPageIncident->delete();

        return back()->with('success', 'Status page incident deleted.');
    }
}

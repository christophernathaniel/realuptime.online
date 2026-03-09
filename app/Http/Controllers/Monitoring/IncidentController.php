<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Http\Requests\Monitoring\UpdateIncidentRequest;
use App\Models\Incident;
use App\Services\Monitoring\MonitorPresenter;
use App\Support\WorkspaceResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IncidentController extends Controller
{
    public function __construct(
        protected MonitorPresenter $presenter,
        protected WorkspaceResolver $workspaces,
    ) {}

    public function show(Request $request, Incident $incident): Response
    {
        return Inertia::render('incidents/show', $this->presenter->incident($this->workspaces->current($request), $incident));
    }

    public function update(UpdateIncidentRequest $request, Incident $incident): RedirectResponse
    {
        abort_unless($incident->monitor()->where('user_id', $this->workspaces->current($request)->id)->exists(), 404);

        $incident->forceFill([
            'operator_notes' => $request->string('operator_notes')->toString() ?: null,
            'root_cause_summary' => $request->string('root_cause_summary')->toString() ?: null,
        ])->save();

        return back()->with('success', 'Incident notes updated.');
    }
}

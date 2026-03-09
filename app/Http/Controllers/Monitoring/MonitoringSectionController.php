<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Services\Monitoring\MonitorPresenter;
use App\Support\WorkspaceResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MonitoringSectionController extends Controller
{
    public function __construct(
        protected MonitorPresenter $presenter,
        protected WorkspaceResolver $workspaces,
    ) {}

    public function incidents(Request $request): Response
    {
        return Inertia::render('monitoring/incidents', $this->presenter->incidents($this->workspaces->current($request)));
    }

    public function statusPages(Request $request): Response
    {
        return Inertia::render('monitoring/status-pages', $this->presenter->statusPages($this->workspaces->current($request)));
    }

    public function maintenance(Request $request): Response
    {
        return Inertia::render('monitoring/maintenance', $this->presenter->maintenance(
            $this->workspaces->current($request),
            $request->integer('monitor_id') ?: null,
        ));
    }

    public function team(Request $request): Response
    {
        return Inertia::render('monitoring/team', $this->presenter->team(
            $request->user(),
            $this->workspaces->current($request),
        ));
    }

    public function integrations(Request $request): Response
    {
        return Inertia::render('monitoring/integrations', $this->presenter->integrations($this->workspaces->current($request)));
    }
}

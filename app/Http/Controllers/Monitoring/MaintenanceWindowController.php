<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Http\Requests\Monitoring\UpsertMaintenanceWindowRequest;
use App\Models\MaintenanceWindow;
use App\Services\Monitoring\EmailNotificationService;
use App\Support\WorkspaceResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class MaintenanceWindowController extends Controller
{
    public function __construct(protected WorkspaceResolver $workspaces) {}

    public function store(UpsertMaintenanceWindowRequest $request, EmailNotificationService $notifications): RedirectResponse
    {
        $workspace = $this->workspaces->current($request);

        $window = DB::transaction(function () use ($request, $workspace): MaintenanceWindow {
            $window = $workspace->maintenanceWindows()->create([
                ...$request->maintenanceData(),
                'status' => $this->resolveStatus($request->date('starts_at'), $request->date('ends_at')),
            ]);

            $window->monitors()->sync($request->monitorIds());
            $window->load(['user.notificationContacts', 'monitors.notificationContacts']);

            return $window;
        });

        if ($window->notify_contacts) {
            $notifications->sendMaintenanceScheduled($window);
        }

        return back()->with('success', 'Maintenance window scheduled.');
    }

    public function update(UpsertMaintenanceWindowRequest $request, MaintenanceWindow $maintenanceWindow): RedirectResponse
    {
        abort_unless($maintenanceWindow->user_id === $this->workspaces->current($request)->id, 404);

        DB::transaction(function () use ($request, $maintenanceWindow): void {
            $maintenanceWindow->update([
                ...$request->maintenanceData(),
                'status' => $this->resolveStatus($request->date('starts_at'), $request->date('ends_at')),
            ]);

            $maintenanceWindow->monitors()->sync($request->monitorIds());
        });

        return back()->with('success', 'Maintenance window updated.');
    }

    public function destroy(MaintenanceWindow $maintenanceWindow): RedirectResponse
    {
        abort_unless($maintenanceWindow->user_id === $this->workspaces->current(request())->id, 404);

        $maintenanceWindow->delete();

        return back()->with('success', 'Maintenance window deleted.');
    }

    protected function resolveStatus(?\DateTimeInterface $startsAt, ?\DateTimeInterface $endsAt): string
    {
        $now = now();

        if ($endsAt && $endsAt < $now) {
            return MaintenanceWindow::STATUS_COMPLETED;
        }

        if ($startsAt && $startsAt <= $now && $endsAt && $endsAt >= $now) {
            return MaintenanceWindow::STATUS_ACTIVE;
        }

        return MaintenanceWindow::STATUS_SCHEDULED;
    }
}

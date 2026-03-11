<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Http\Requests\Monitoring\UpsertMonitorRequest;
use App\Jobs\RunMonitorCheckJob;
use App\Models\Capability;
use App\Models\Monitor;
use App\Models\User;
use App\Services\Monitoring\EmailNotificationService;
use App\Services\Monitoring\Integrations\WorkspaceIntegrationNotificationService;
use App\Services\Monitoring\MonitorPresenter;
use App\Support\WorkspaceResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MonitorController extends Controller
{
    public function __construct(
        protected MonitorPresenter $presenter,
        protected EmailNotificationService $notifications,
        protected WorkspaceIntegrationNotificationService $integrations,
        protected WorkspaceResolver $workspaces,
    ) {}

    public function index(Request $request): Response
    {
        $workspace = $this->workspaces->current($request);

        return Inertia::render('monitors/index', [
            ...$this->presenter->indexPage($workspace),
            'capabilities' => Inertia::defer(
                fn () => $this->presenter->indexCapabilities($workspace),
                'monitor-insights',
            ),
        ]);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $workspace = $this->workspaces->current($request);
        $actor = $request->user();

        if ($workspace->hasReachedMonitorLimit()) {
            return redirect()
                ->route($workspace->id === $actor?->id ? 'membership.show' : 'monitors.index')
                ->with('error', sprintf(
                    'This workspace is already using %d of %d monitors. Upgrade the membership plan to add more.',
                    $workspace->monitors()->count(),
                    $workspace->monitorLimit(),
                ));
        }

        return Inertia::render('monitors/edit', [
            'mode' => 'create',
            ...$this->presenter->form($workspace, actor: $actor),
        ]);
    }

    public function store(UpsertMonitorRequest $request): RedirectResponse
    {
        $workspace = $this->workspaces->current($request);
        $actor = $request->user();

        if ($workspace->hasReachedMonitorLimit()) {
            return redirect()
                ->route($workspace->id === $actor?->id ? 'membership.show' : 'monitors.index')
                ->with('error', sprintf(
                    'This workspace is already using %d of %d monitors. Upgrade to Premium or Ultra to add more.',
                    $workspace->monitors()->count(),
                    $workspace->monitorLimit(),
                ));
        }

        $monitor = $workspace->monitors()->create([
            ...$request->monitorData(),
            'admin_interval_override' => false,
            'status' => Monitor::STATUS_UP,
            'last_status_changed_at' => now(),
            'next_check_at' => now(),
        ]);

        $contactIds = $request->contactIds();

        if ($contactIds === []) {
            $contactIds = $workspace->notificationContacts()->where('enabled', true)->pluck('id')->all();
        }

        $monitor->notificationContacts()->sync($contactIds);
        $this->syncCapabilities($workspace, $monitor, $request->capabilityNames());

        return redirect()->route('monitors.show', $monitor);
    }

    public function show(Request $request, Monitor $monitor): Response
    {
        abort_unless($monitor->user_id === $this->workspaces->current($request)->id, 404);

        $responseRange = $request->string('response_range')->toString();
        $responseGranularity = $request->string('response_granularity')->toString();

        return Inertia::render('monitors/show', [
            ...$this->presenter->showPage($monitor),
            'monitorHistory' => Inertia::defer(
                fn () => $this->presenter->showHistory($monitor->fresh(), $responseRange, $responseGranularity)['monitorHistory'],
                'monitor-insights',
            ),
            'monitorCapabilities' => Inertia::defer(
                fn () => $this->presenter->showCapabilities($monitor->fresh())['monitorCapabilities'],
                'monitor-insights',
            ),
        ]);
    }

    public function edit(Request $request, Monitor $monitor): Response
    {
        $workspace = $this->workspaces->current($request);
        abort_unless($monitor->user_id === $workspace->id, 404);

        return Inertia::render('monitors/edit', [
            'mode' => 'edit',
            ...$this->presenter->form($workspace, $monitor, $request->user()),
        ]);
    }

    public function update(UpsertMonitorRequest $request, Monitor $monitor): RedirectResponse
    {
        abort_unless($monitor->user_id === $this->workspaces->current($request)->id, 404);

        $monitor->fill([
            ...$request->monitorData(),
            'admin_interval_override' => false,
            'next_check_at' => now(),
            'check_claimed_at' => null,
            'check_claim_token' => null,
        ])->save();
        $monitor->notificationContacts()->sync($request->contactIds());
        $this->syncCapabilities($this->workspaces->current($request), $monitor, $request->capabilityNames());

        return redirect()->route('monitors.show', $monitor);
    }

    public function toggle(Request $request, Monitor $monitor): RedirectResponse
    {
        abort_unless($monitor->user_id === $this->workspaces->current($request)->id, 404);

        $monitor->forceFill([
            'status' => $monitor->status === Monitor::STATUS_PAUSED ? Monitor::STATUS_UP : Monitor::STATUS_PAUSED,
            'last_status_changed_at' => now(),
            'next_check_at' => $monitor->status === Monitor::STATUS_PAUSED ? now() : null,
            'check_claimed_at' => null,
            'check_claim_token' => null,
        ])->save();

        return back();
    }

    public function testNotification(Request $request, Monitor $monitor): RedirectResponse
    {
        abort_unless($monitor->user_id === $this->workspaces->current($request)->id, 404);

        $monitor = $monitor->loadMissing('notificationContacts', 'user');

        $this->notifications->sendTest($monitor);
        $this->integrations->sendTest($monitor);

        return back()->with('success', 'Test alert queued for configured notification channels.');
    }

    public function runNow(Request $request, Monitor $monitor): RedirectResponse
    {
        abort_unless($monitor->user_id === $this->workspaces->current($request)->id, 404);

        if ($monitor->status === Monitor::STATUS_PAUSED) {
            return back()->with('error', 'Resume the monitor before running an on-demand check.');
        }

        RunMonitorCheckJob::dispatch($monitor->id, now()->toIso8601String())->afterCommit();

        return back()->with('success', sprintf('Queued an on-demand check for %s.', $monitor->name));
    }

    public function destroy(Request $request, Monitor $monitor): RedirectResponse
    {
        abort_unless($monitor->user_id === $this->workspaces->current($request)->id, 404);

        $monitor->delete();

        return redirect()->route('monitors.index')->with('success', 'Monitor deleted.');
    }

    /**
     * @param  array<int, string>  $names
     */
    protected function syncCapabilities(User $workspace, Monitor $monitor, array $names): void
    {
        if (! $workspace->allowsAdvancedWorkspaceFeatures()) {
            $monitor->capabilities()->sync([]);

            return;
        }

        $capabilityIds = collect($names)->map(function (string $name) use ($workspace): int {
            $existing = $workspace->capabilities()->where('name', $name)->first();

            if ($existing) {
                return $existing->id;
            }

            $baseSlug = Str::slug($name) ?: Str::lower(Str::random(8));
            $slug = $baseSlug;
            $suffix = 2;

            while ($workspace->capabilities()->where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$suffix;
                $suffix++;
            }

            return Capability::query()->create([
                'user_id' => $workspace->id,
                'name' => $name,
                'slug' => $slug,
            ])->id;
        })->all();

        $monitor->capabilities()->sync($capabilityIds);
    }
}

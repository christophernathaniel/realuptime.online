<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Http\Requests\Monitoring\UpsertStatusPageRequest;
use App\Models\StatusPage;
use App\Support\WorkspaceResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class StatusPageController extends Controller
{
    public function __construct(protected WorkspaceResolver $workspaces) {}

    public function store(UpsertStatusPageRequest $request): RedirectResponse
    {
        $workspace = $this->workspaces->current($request);

        $statusPage = DB::transaction(function () use ($request, $workspace): StatusPage {
            $statusPage = $workspace->statusPages()->create($request->statusPageData());
            $statusPage->monitors()->sync($this->syncPayload($request->monitorIds()));

            return $statusPage;
        });

        return redirect()->route('status-pages.index')->with('success', sprintf('Status page "%s" created.', $statusPage->name));
    }

    public function update(UpsertStatusPageRequest $request, StatusPage $statusPage): RedirectResponse
    {
        abort_unless($statusPage->user_id === $this->workspaces->current($request)->id, 404);

        DB::transaction(function () use ($request, $statusPage): void {
            $statusPage->update($request->statusPageData());
            $statusPage->monitors()->sync($this->syncPayload($request->monitorIds()));
        });

        return back()->with('success', 'Status page updated.');
    }

    public function destroy(StatusPage $statusPage): RedirectResponse
    {
        abort_unless($statusPage->user_id === $this->workspaces->current(request())->id, 404);

        $statusPage->delete();

        return back()->with('success', 'Status page deleted.');
    }

    /**
     * @param  array<int, int>  $monitorIds
     * @return array<int, array<string, int>>
     */
    protected function syncPayload(array $monitorIds): array
    {
        $payload = [];

        foreach (array_values($monitorIds) as $index => $monitorId) {
            $payload[$monitorId] = ['sort_order' => $index];
        }

        return $payload;
    }
}

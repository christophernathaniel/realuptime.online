<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RunMonitorCheckJob;
use App\Models\Monitor;
use App\Support\MonitorQueueResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonitorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min(100, max(1, $request->integer('per_page', 50)));
        $page = max(1, $request->integer('page', 1));

        $monitors = $user?->monitors()
            ->orderBy('created_at')
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();

        return response()->json([
            'data' => collect($monitors?->items() ?? [])
                ->map(fn (Monitor $monitor) => $this->serializeMonitor($monitor))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $monitors?->currentPage() ?? 1,
                'last_page' => $monitors?->lastPage() ?? 1,
                'per_page' => $monitors?->perPage() ?? $perPage,
                'total' => $monitors?->total() ?? 0,
                'from' => $monitors?->firstItem(),
                'to' => $monitors?->lastItem(),
            ],
            'links' => [
                'previous' => $monitors?->previousPageUrl(),
                'next' => $monitors?->nextPageUrl(),
            ],
        ]);
    }

    public function show(Request $request, Monitor $monitor): JsonResponse
    {
        abort_unless($monitor->user_id === $request->user()?->id, 404);

        return response()->json([
            'data' => $this->serializeMonitor($monitor),
        ]);
    }

    public function runNow(Request $request, Monitor $monitor): JsonResponse
    {
        abort_unless($monitor->user_id === $request->user()?->id, 404);

        if ($monitor->status === Monitor::STATUS_PAUSED) {
            return response()->json([
                'message' => 'Resume the monitor before running an on-demand check.',
            ], 422);
        }

        RunMonitorCheckJob::dispatch(
            $monitor->id,
            now()->toIso8601String(),
            $monitor->type,
            MonitorQueueResolver::usesRegionQueues() ? $monitor->region : null,
        );

        return response()->json([
            'message' => 'Monitor check dispatched.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeMonitor(Monitor $monitor): array
    {
        return [
            'id' => $monitor->id,
            'public_id' => $monitor->public_id,
            'name' => $monitor->name,
            'type' => $monitor->type,
            'status' => $monitor->status,
            'target' => $monitor->target,
            'interval_seconds' => $monitor->interval_seconds,
            'timeout_seconds' => $monitor->timeout_seconds,
            'region' => $monitor->region,
            'accepted_http_statuses' => $monitor->accepted_http_statuses ?: '200-299',
            'last_checked_at' => $monitor->last_checked_at?->toIso8601String(),
            'next_check_at' => $monitor->next_check_at?->toIso8601String(),
            'last_response_time_ms' => $monitor->last_response_time_ms,
            'last_queue_lag_ms' => $monitor->last_queue_lag_ms,
            'last_probe_region' => $monitor->last_probe_region,
            'last_http_status' => $monitor->last_http_status,
            'last_error_type' => $monitor->last_error_type,
            'last_error_message' => $monitor->last_error_message,
        ];
    }
}

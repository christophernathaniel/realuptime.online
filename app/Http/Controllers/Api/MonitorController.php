<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RunMonitorCheckJob;
use App\Models\Monitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonitorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $monitors = $user?->monitors()
            ->orderBy('created_at')
            ->get()
            ->map(fn (Monitor $monitor) => $this->serializeMonitor($monitor))
            ->all();

        return response()->json([
            'data' => $monitors,
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

        RunMonitorCheckJob::dispatch($monitor->id, now()->toIso8601String());

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
            'name' => $monitor->name,
            'type' => $monitor->type,
            'status' => $monitor->status,
            'target' => $monitor->target,
            'interval_seconds' => $monitor->interval_seconds,
            'timeout_seconds' => $monitor->timeout_seconds,
            'region' => $monitor->region,
            'last_checked_at' => $monitor->last_checked_at?->toIso8601String(),
            'next_check_at' => $monitor->next_check_at?->toIso8601String(),
            'last_response_time_ms' => $monitor->last_response_time_ms,
            'last_http_status' => $monitor->last_http_status,
            'last_error_type' => $monitor->last_error_type,
            'last_error_message' => $monitor->last_error_message,
        ];
    }
}

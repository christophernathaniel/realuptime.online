<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Models\HeartbeatEvent;
use App\Models\Monitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HeartbeatController extends Controller
{
    public function store(Request $request, string $token): JsonResponse
    {
        $monitor = Monitor::query()
            ->where('type', Monitor::TYPE_HEARTBEAT)
            ->where('heartbeat_token', $token)
            ->firstOrFail();

        HeartbeatEvent::query()->create([
            'monitor_id' => $monitor->id,
            'received_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        $monitor->forceFill([
            'last_heartbeat_at' => now(),
            'status' => $monitor->status === Monitor::STATUS_PAUSED ? Monitor::STATUS_PAUSED : Monitor::STATUS_UP,
            'last_status_changed_at' => now(),
        ])->save();

        return response()->json([
            'ok' => true,
            'monitor' => $monitor->name,
            'received_at' => now()->toIso8601String(),
        ]);
    }
}

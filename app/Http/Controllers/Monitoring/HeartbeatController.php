<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Models\HeartbeatEvent;
use App\Models\Monitor;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HeartbeatController extends Controller
{
    public function store(Request $request, string $token): JsonResponse
    {
        $receivedAt = CarbonImmutable::now();

        DB::transaction(function () use ($request, $token, $receivedAt): void {
            $monitor = Monitor::query()
                ->where('type', Monitor::TYPE_HEARTBEAT)
                ->where('heartbeat_token', $token)
                ->lockForUpdate()
                ->firstOrFail();

            $latestStoredHeartbeat = HeartbeatEvent::query()
                ->where('monitor_id', $monitor->id)
                ->latest('received_at')
                ->value('received_at');

            if (
                $latestStoredHeartbeat === null
                || CarbonImmutable::parse($latestStoredHeartbeat)->lte($receivedAt->subMinute())
            ) {
                HeartbeatEvent::query()->create([
                    'monitor_id' => $monitor->id,
                    'received_at' => $receivedAt,
                    'ip_address' => $request->ip(),
                    'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                ]);
            }

            $nextStatus = $monitor->status === Monitor::STATUS_PAUSED
                ? Monitor::STATUS_PAUSED
                : Monitor::STATUS_UP;

            $monitor->forceFill([
                'last_heartbeat_at' => $receivedAt,
                'status' => $nextStatus,
                'last_status_changed_at' => $monitor->status === $nextStatus
                    ? $monitor->last_status_changed_at
                    : $receivedAt,
            ])->save();
        });

        return response()->json([
            'ok' => true,
            'received_at' => $receivedAt->toIso8601String(),
        ]);
    }
}

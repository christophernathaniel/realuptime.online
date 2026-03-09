<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $incidents = Incident::query()
            ->whereHas('monitor', fn ($query) => $query->where('user_id', $user?->id))
            ->with('monitor')
            ->latest('started_at')
            ->limit(100)
            ->get()
            ->map(fn (Incident $incident) => [
                'id' => $incident->id,
                'monitor_id' => $incident->monitor_id,
                'monitor_name' => $incident->monitor?->name,
                'type' => $incident->type,
                'severity' => $incident->severity,
                'reason' => $incident->reason,
                'started_at' => $incident->started_at?->toIso8601String(),
                'resolved_at' => $incident->resolved_at?->toIso8601String(),
                'duration_seconds' => $incident->duration_seconds,
            ])->all();

        return response()->json([
            'data' => $incidents,
        ]);
    }
}

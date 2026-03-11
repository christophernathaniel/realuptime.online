<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackAuthenticatedSession
{
    protected const REQUEST_SESSION_ATTRIBUTE = 'tracked_session.current';

    protected const SESSION_SYNCED_AT_KEY = 'tracked_session_synced_at';

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        $sessionId = $request->session()->getId();

        if (! $user || $sessionId === '') {
            return $response;
        }

        $refreshSeconds = max(60, (int) config('realuptime.session_tracking.refresh_seconds', 300));
        $lastSyncedAt = $request->session()->get(self::SESSION_SYNCED_AT_KEY);

        if (is_string($lastSyncedAt) && CarbonImmutable::parse($lastSyncedAt)->gte(now()->subSeconds($refreshSeconds))) {
            return $response;
        }

        /** @var UserSession|null $trackedSession */
        $trackedSession = $request->attributes->get(self::REQUEST_SESSION_ATTRIBUTE);
        $now = now();
        $payload = [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'last_path' => $request->path(),
            'last_active_at' => $now,
            'revoked_at' => null,
        ];

        if ($trackedSession) {
            if ($trackedSession->revoked_at) {
                return $response;
            }

            if (! $trackedSession->last_active_at || $trackedSession->last_active_at->lte($now->copy()->subSeconds($refreshSeconds))) {
                $trackedSession->forceFill($payload)->save();
            }
        } else {
            $trackedSession = UserSession::query()
                ->where('user_id', $user->id)
                ->where('session_id', $sessionId)
                ->first(['id', 'user_id', 'session_id', 'last_active_at', 'revoked_at']);

            if ($trackedSession) {
                if (! $trackedSession->last_active_at || $trackedSession->last_active_at->lte($now->copy()->subSeconds($refreshSeconds))) {
                    $trackedSession->forceFill($payload)->save();
                }
            } else {
                UserSession::query()->create([
                    'user_id' => $user->id,
                    'session_id' => $sessionId,
                    ...$payload,
                ]);
            }
        }

        $request->session()->put(self::SESSION_SYNCED_AT_KEY, $now->toIso8601String());

        return $response;
    }
}

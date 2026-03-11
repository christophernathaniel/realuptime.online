<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureTrackedSessionIsActive
{
    protected const REQUEST_SESSION_ATTRIBUTE = 'tracked_session.current';

    protected const SESSION_VERIFIED_AT_KEY = 'tracked_session_verified_at';

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $sessionId = $request->session()->getId();

        if (! $user || $sessionId === '') {
            return $next($request);
        }

        $verifySeconds = max(15, (int) config('realuptime.session_tracking.verify_seconds', 60));
        $lastVerifiedAt = $request->session()->get(self::SESSION_VERIFIED_AT_KEY);

        if (is_string($lastVerifiedAt) && CarbonImmutable::parse($lastVerifiedAt)->gte(now()->subSeconds($verifySeconds))) {
            return $next($request);
        }

        $trackedSession = UserSession::query()
            ->where('user_id', $user->id)
            ->where('session_id', $sessionId)
            ->first(['id', 'user_id', 'session_id', 'last_active_at', 'revoked_at']);

        $request->attributes->set(self::REQUEST_SESSION_ATTRIBUTE, $trackedSession);

        if ($trackedSession?->revoked_at) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'This session has been ended.');
        }

        $request->session()->put(self::SESSION_VERIFIED_AT_KEY, now()->toIso8601String());

        return $next($request);
    }
}

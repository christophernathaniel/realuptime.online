<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureTrackedSessionIsActive
{
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

        $trackedSession = UserSession::query()
            ->where('user_id', $user->id)
            ->where('session_id', $sessionId)
            ->first();

        if ($trackedSession?->revoked_at) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'This session has been ended.');
        }

        return $next($request);
    }
}

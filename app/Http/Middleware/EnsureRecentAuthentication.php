<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\Response;

class EnsureRecentAuthentication
{
    public function handle(Request $request, Closure $next, int $timeoutSeconds = 900): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $confirmedAt = (int) $request->session()->get('auth.password_confirmed_at', 0);

        if (Date::now()->unix() - $confirmedAt <= max(60, $timeoutSeconds)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Recent authentication is required.',
            ], 423);
        }

        if (! $user->password_login_enabled) {
            return redirect()->route('profile.edit')->with(
                'error',
                'Sign out and sign in with your connected provider again before changing security or billing settings.',
            );
        }

        return redirect()->guest(route('password.confirm'));
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainTextToken = $request->bearerToken();

        if (! $plainTextToken) {
            return response()->json([
                'message' => 'Missing API token.',
            ], 401);
        }

        $token = ApiToken::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $plainTextToken))
            ->first();

        if (! $token || ! $token->user) {
            return response()->json([
                'message' => 'Invalid API token.',
            ], 401);
        }

        $token->forceFill([
            'last_used_at' => now(),
        ])->save();

        $request->attributes->set('apiToken', $token);
        $request->setUserResolver(fn () => $token->user);

        return $next($request);
    }
}

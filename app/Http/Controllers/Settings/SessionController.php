<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\UserSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    protected const REQUEST_SESSION_ATTRIBUTE = 'tracked_session.current';

    protected const SESSION_SYNCED_AT_KEY = 'tracked_session_synced_at';

    public function index(Request $request): Response
    {
        $user = $request->user();
        $currentSessionId = $request->session()->getId();

        if ($currentSessionId !== '') {
            $refreshSeconds = max(60, (int) config('realuptime.session_tracking.refresh_seconds', 300));
            /** @var UserSession|null $trackedSession */
            $trackedSession = $request->attributes->get(self::REQUEST_SESSION_ATTRIBUTE);
            $now = now();

            if (! $trackedSession) {
                $trackedSession = $user->trackedSessions()
                    ->where('session_id', $currentSessionId)
                    ->first(['id', 'user_id', 'session_id', 'last_active_at', 'revoked_at']);
            }

            if (! $trackedSession) {
                $trackedSession = $user->trackedSessions()->create([
                    'session_id' => $currentSessionId,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'last_path' => $request->path(),
                    'last_active_at' => $now,
                    'revoked_at' => null,
                ]);
            } elseif (! $trackedSession->last_active_at || $trackedSession->last_active_at->lte($now->copy()->subSeconds($refreshSeconds))) {
                $trackedSession->forceFill([
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'last_path' => $request->path(),
                    'last_active_at' => $now,
                    'revoked_at' => null,
                ])->save();
            }

            $request->attributes->set(self::REQUEST_SESSION_ATTRIBUTE, $trackedSession);
            $request->session()->put(self::SESSION_SYNCED_AT_KEY, $now->toIso8601String());
        }

        $sessions = $user->trackedSessions()
            ->whereNull('revoked_at')
            ->orderByDesc('last_active_at')
            ->get();

        return Inertia::render('settings/sessions', [
            'sessions' => $sessions->map(fn (UserSession $session) => [
                'id' => $session->id,
                'sessionId' => $session->session_id,
                'deviceLabel' => $this->deviceLabel($session->user_agent),
                'browser' => $this->browser($session->user_agent),
                'platform' => $this->platform($session->user_agent),
                'ipAddress' => $session->ip_address ?? 'Unknown',
                'lastPath' => $session->last_path,
                'lastActiveAt' => $session->last_active_at?->format('M j, Y H:i'),
                'lastActiveAgo' => $session->last_active_at?->diffForHumans(),
                'isCurrent' => $session->session_id === $currentSessionId,
            ])->all(),
        ]);
    }

    public function destroy(Request $request, UserSession $session): RedirectResponse
    {
        abort_unless($session->user_id === $request->user()->id, 404);

        $session->forceFill(['revoked_at' => now()])->save();

        if ($session->session_id === $request->session()->getId()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('success', 'Session ended.');
        }

        return back()->with('success', 'Session ended.');
    }

    public function destroyOthers(Request $request): RedirectResponse
    {
        $request->user()->trackedSessions()
            ->where('session_id', '!=', $request->session()->getId())
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        return back()->with('success', 'All other sessions have been ended.');
    }

    protected function deviceLabel(?string $userAgent): string
    {
        if (! $userAgent) {
            return 'Unknown device';
        }

        return trim($this->platform($userAgent).' • '.$this->browser($userAgent));
    }

    protected function browser(?string $userAgent): string
    {
        $userAgent = Str::lower((string) $userAgent);

        return match (true) {
            str_contains($userAgent, 'edg/') => 'Edge',
            str_contains($userAgent, 'chrome/') => 'Chrome',
            str_contains($userAgent, 'safari/') && ! str_contains($userAgent, 'chrome/') => 'Safari',
            str_contains($userAgent, 'firefox/') => 'Firefox',
            default => 'Browser',
        };
    }

    protected function platform(?string $userAgent): string
    {
        $userAgent = Str::lower((string) $userAgent);

        return match (true) {
            str_contains($userAgent, 'iphone') => 'iPhone',
            str_contains($userAgent, 'ipad') => 'iPad',
            str_contains($userAgent, 'android') => 'Android',
            str_contains($userAgent, 'mac os') || str_contains($userAgent, 'macintosh') => 'macOS',
            str_contains($userAgent, 'windows') => 'Windows',
            str_contains($userAgent, 'linux') => 'Linux',
            default => 'Device',
        };
    }
}

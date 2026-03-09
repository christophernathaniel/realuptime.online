<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Support\OAuthProviderCatalog;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user()->load('connectedAccounts');

        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'oauthProviders' => collect(OAuthProviderCatalog::all())
                ->map(function (array $provider) use ($user): array {
                    $account = $user->connectedAccounts()->where('provider', $provider['key'])->first();

                    return [
                        ...$provider,
                        'connected' => $account !== null,
                        'connectedAs' => $account?->provider_email ?: $account?->provider_name,
                        'avatarUrl' => $account?->avatar_url,
                        'redirectUrl' => route('oauth.redirect', $provider['key']),
                        'disconnectUrl' => route('oauth.disconnect', $provider['key']),
                        'canDisconnect' => $account !== null && ($user->password_login_enabled || $user->connectedAccounts()->count() > 1),
                    ];
                })
                ->values()
                ->all(),
            'passwordLoginEnabled' => $user->password_login_enabled,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

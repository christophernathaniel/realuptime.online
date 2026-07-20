<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Services\Billing\SubscriptionManager;
use App\Support\OAuthProviderCatalog;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ProfileController extends Controller
{
    public function __construct(protected SubscriptionManager $subscriptions) {}

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
                ->filter(fn (array $provider) => $provider['enabled'] || $provider['connected'])
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
        $user = $request->user();
        $data = $request->validated();

        if (
            $user->isMainAdmin()
            && strcasecmp((string) $data['email'], (string) config('realuptime.admin.main_admin_email')) !== 0
        ) {
            return back()->withErrors([
                'email' => 'Change REALUPTIME_MAIN_ADMIN_EMAIL before changing the main admin email address.',
            ]);
        }

        if (
            filled($user->stripe_id)
            && ($user->name !== $data['name'] || strcasecmp($user->email, $data['email']) !== 0)
        ) {
            if (blank(config('cashier.secret'))) {
                return back()->withErrors([
                    'email' => 'Stripe must be configured before changing details for a Stripe customer.',
                ]);
            }

            try {
                $user->updateStripeCustomer([
                    'name' => $data['name'],
                    'email' => $data['email'],
                ]);
            } catch (Throwable $exception) {
                report($exception);

                return back()->withErrors([
                    'email' => 'Stripe could not update your billing details. Your profile was not changed.',
                ]);
            }
        }

        $user->fill($data);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        try {
            $this->subscriptions->cancelImmediatelyForDeletion($user);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Stripe billing could not be cancelled, so your account was not deleted.');
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

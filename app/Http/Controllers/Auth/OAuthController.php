<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ConnectedAccount;
use App\Models\User;
use App\Support\OAuthProviderCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

class OAuthController extends Controller
{
    public function redirect(Request $request, string $provider): RedirectResponse
    {
        abort_unless(OAuthProviderCatalog::isSupported($provider), 404);

        if (! $this->providerEnabled($provider)) {
            return back()->with('error', ucfirst($provider).' login is not configured yet.');
        }

        $request->session()->put('oauth.provider', $provider);
        $request->session()->put('oauth.intent', $request->user() ? 'link' : 'login');
        $request->session()->put('oauth.user_id', $request->user()?->id);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        abort_unless(OAuthProviderCatalog::isSupported($provider), 404);

        if (! $this->providerEnabled($provider)) {
            return redirect()->route('login')->with('error', ucfirst($provider).' login is not configured yet.');
        }

        $socialiteUser = Socialite::driver($provider)->user();
        $intent = $request->session()->pull('oauth.intent', 'login');
        $requestedUserId = $request->session()->pull('oauth.user_id');
        $request->session()->forget('oauth.provider');

        if ($intent === 'link') {
            $user = $request->user() ?? User::query()->find($requestedUserId);

            if (! $user) {
                return redirect()->route('login')->with('error', 'Sign in before linking an OAuth provider.');
            }

            return $this->linkAccount($user, $provider, $socialiteUser);
        }

        return $this->authenticate($request, $provider, $socialiteUser);
    }

    public function destroy(Request $request, string $provider): RedirectResponse
    {
        abort_unless(OAuthProviderCatalog::isSupported($provider), 404);

        $user = $request->user();
        $account = $user->connectedAccounts()->where('provider', $provider)->firstOrFail();
        $linkedAccountsCount = $user->connectedAccounts()->count();

        if (! $user->password_login_enabled && $linkedAccountsCount <= 1) {
            return back()->with('error', 'Set a password before disconnecting your last OAuth provider.');
        }

        $account->delete();

        return back()->with('success', ucfirst($provider).' account disconnected.');
    }

    protected function authenticate(Request $request, string $provider, SocialiteUser $socialiteUser): RedirectResponse
    {
        $connectedAccount = ConnectedAccount::query()
            ->with('user')
            ->where('provider', $provider)
            ->where('provider_id', $socialiteUser->getId())
            ->first();

        if ($connectedAccount) {
            $this->syncConnectedAccount($connectedAccount, $socialiteUser);
            Auth::login($connectedAccount->user, true);
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }

        $email = $socialiteUser->getEmail();

        if (! $email) {
            return redirect()->route('login')->with('error', ucfirst($provider).' did not return an email address.');
        }

        $user = User::query()->firstWhere('email', $email);

        if (! $user) {
            $user = User::query()->create([
                'name' => $socialiteUser->getName() ?: $socialiteUser->getNickname() ?: ucfirst($provider).' user',
                'email' => $email,
                'password' => Str::password(40),
                'password_login_enabled' => false,
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();
        } elseif ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $this->upsertConnectedAccount($user, $provider, $socialiteUser);

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    protected function linkAccount(User $user, string $provider, SocialiteUser $socialiteUser): RedirectResponse
    {
        $existingAccount = ConnectedAccount::query()
            ->where('provider', $provider)
            ->where('provider_id', $socialiteUser->getId())
            ->first();

        if ($existingAccount && $existingAccount->user_id !== $user->id) {
            return redirect()->route('profile.edit')->with('error', ucfirst($provider).' is already linked to another account.');
        }

        $this->upsertConnectedAccount($user, $provider, $socialiteUser);

        return redirect()->route('profile.edit')->with('success', ucfirst($provider).' account linked.');
    }

    protected function upsertConnectedAccount(User $user, string $provider, SocialiteUser $socialiteUser): ConnectedAccount
    {
        return $user->connectedAccounts()->updateOrCreate(
            ['provider' => $provider],
            [
                'provider_id' => $socialiteUser->getId(),
                'provider_email' => $socialiteUser->getEmail(),
                'provider_name' => $socialiteUser->getName() ?: $socialiteUser->getNickname(),
                'avatar_url' => $socialiteUser->getAvatar(),
                'token' => $socialiteUser->token,
                'refresh_token' => $socialiteUser->refreshToken,
                'expires_at' => $socialiteUser->expiresIn ? now()->addSeconds($socialiteUser->expiresIn) : null,
            ],
        );
    }

    protected function syncConnectedAccount(ConnectedAccount $account, SocialiteUser $socialiteUser): void
    {
        $account->forceFill([
            'provider_email' => $socialiteUser->getEmail(),
            'provider_name' => $socialiteUser->getName() ?: $socialiteUser->getNickname(),
            'avatar_url' => $socialiteUser->getAvatar(),
            'token' => $socialiteUser->token,
            'refresh_token' => $socialiteUser->refreshToken,
            'expires_at' => $socialiteUser->expiresIn ? now()->addSeconds($socialiteUser->expiresIn) : null,
        ])->save();
    }

    protected function providerEnabled(string $provider): bool
    {
        return collect(OAuthProviderCatalog::all())
            ->firstWhere('key', $provider)['enabled'] ?? false;
    }
}

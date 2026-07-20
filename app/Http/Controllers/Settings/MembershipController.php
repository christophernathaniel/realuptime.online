<?php

namespace App\Http\Controllers\Settings;

use App\Enums\MembershipPlan;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Cashier\Checkout;
use Throwable;

class MembershipController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user()->loadMissing('subscriptions.items');
        $currentPlan = $user->membershipPlan();
        $subscription = $user->subscription('default');
        $stripeReady = $this->stripeReady();

        return Inertia::render('settings/membership', [
            'membership' => [
                'currentPlan' => [
                    'value' => $currentPlan->value,
                    'label' => $currentPlan->label(),
                    'priceLabel' => $currentPlan->priceLabel(),
                    'monitorLimit' => $user->monitorLimit(),
                    'monitorLimitLabel' => (string) $user->monitorLimit(),
                    'minimumIntervalLabel' => $this->intervalLabel($user->minimumMonitorIntervalSeconds()),
                    'source' => $user->membershipSource(),
                    'sourceLabel' => match ($user->membershipSource()) {
                        'admin' => 'Managed by admin override',
                        'stripe' => 'Stripe subscription',
                        'support' => 'Courtesy extension from support',
                        default => 'Free access',
                    },
                    'advancedFeaturesUnlocked' => $user->allowsAdvancedWorkspaceFeatures(),
                    'isAdmin' => $user->isMainAdmin(),
                ],
                'plans' => collect(MembershipPlan::cases())->map(fn (MembershipPlan $plan) => [
                    'value' => $plan->value,
                    'label' => $plan->label(),
                    'priceLabel' => $plan->priceLabel(),
                    'monitorLimit' => $plan->monitorLimit(),
                    'minimumIntervalLabel' => $this->intervalLabel($plan->minimumIntervalSeconds()),
                    'advancedFeaturesUnlocked' => $plan->allowsAdvancedWorkspaceFeatures(),
                    'stripeEnabled' => $plan === MembershipPlan::FREE || $plan->stripePriceId() !== null,
                    'isCurrent' => $currentPlan === $plan,
                ])->all(),
                'stripeReady' => $stripeReady,
                'stripeMissing' => $this->stripeMissingConfiguration(),
                'canCheckout' => $stripeReady && $user->adminPlanOverride() === null && ! $user->subscribed('default'),
                'canManageBilling' => $stripeReady && filled($user->stripe_id),
                'subscriptionActive' => $subscription?->valid() ?? false,
                'subscriptionStatus' => $subscription?->stripe_status,
                'subscriptionOnGracePeriod' => $subscription?->onGracePeriod() ?? false,
                'subscriptionEndsAt' => $subscription?->ends_at?->format('M j, Y H:i'),
                'adminOverride' => $user->adminPlanOverride()?->value,
                'supportExtension' => $user->supportPlanExtension() ? [
                    'plan' => $user->supportPlanExtension()?->value,
                    'planLabel' => $user->supportPlanExtension()?->label(),
                    'expiresAt' => $user->support_plan_expires_at?->format('M j, Y H:i'),
                ] : null,
                'checkoutSuccess' => $request->boolean('checkout'),
                'checkoutCancelled' => $request->boolean('cancelled'),
            ],
        ]);
    }

    public function checkout(Request $request, string $plan): RedirectResponse|Checkout
    {
        $user = $request->user();
        $membershipPlan = MembershipPlan::tryFrom($plan);

        abort_unless($user !== null, 401);

        if (! $membershipPlan || $membershipPlan === MembershipPlan::FREE) {
            return back()->with('error', 'Select a paid membership plan to continue.');
        }

        if ($user->adminPlanOverride() !== null) {
            return back()->with('error', 'Your membership is currently managed by an admin override.');
        }

        if ($user->subscribed('default')) {
            return redirect()->route('membership.show')->with('error', 'Manage your existing subscription from the billing portal.');
        }

        if (! $this->stripeReady()) {
            return back()->with('error', 'Stripe keys, webhook signing secret, and paid plan prices must be configured first.');
        }

        $priceId = $membershipPlan->stripePriceId();

        if (! $priceId) {
            return back()->with('error', 'Stripe pricing is not configured for this plan yet.');
        }

        try {
            return $user
                ->newSubscription('default', $priceId)
                ->checkout([
                    'success_url' => route('membership.show', ['checkout' => 1]),
                    'cancel_url' => route('membership.show', ['cancelled' => 1]),
                ]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Stripe checkout is temporarily unavailable. Please try again.');
        }
    }

    public function portal(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        if (! $this->stripeReady()) {
            return redirect()->route('membership.show')->with('error', 'Stripe billing is not fully configured.');
        }

        if (! $user->subscriptions()->exists()) {
            return redirect()->route('membership.show')->with('error', 'No Stripe billing portal is available until a subscription exists.');
        }

        try {
            return $user->redirectToBillingPortal(route('membership.show'));
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('membership.show')->with('error', 'The Stripe billing portal is temporarily unavailable.');
        }
    }

    protected function intervalLabel(int $seconds): string
    {
        [$value, $unit] = match (true) {
            $seconds < 60 => [$seconds, 'second'],
            $seconds < 3600 => [(int) round($seconds / 60), 'minute'],
            default => [(int) round($seconds / 3600), 'hour'],
        };

        return $value.' '.$unit.($value === 1 ? '' : 's');
    }

    protected function stripeReady(): bool
    {
        return $this->stripeMissingConfiguration() === [];
    }

    protected function stripeMissingConfiguration(): array
    {
        return collect([
            'Publishable key' => filled(config('cashier.key')),
            'Secret key' => filled(config('cashier.secret')),
            'Webhook signing secret' => filled(config('cashier.webhook.secret')),
            'Premium price' => filled(MembershipPlan::PREMIUM->stripePriceId()),
            'Ultra price' => filled(MembershipPlan::ULTRA->stripePriceId()),
        ])->filter(fn (bool $configured) => ! $configured)
            ->keys()
            ->values()
            ->all();
    }
}

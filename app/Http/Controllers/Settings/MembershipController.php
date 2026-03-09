<?php

namespace App\Http\Controllers\Settings;

use App\Enums\MembershipPlan;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MembershipController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user()->loadMissing('subscriptions.items');
        $currentPlan = $user->membershipPlan();
        $subscription = $user->subscription('default');

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
                        default => 'Free access',
                    },
                    'advancedFeaturesUnlocked' => $user->allowsAdvancedWorkspaceFeatures(),
                    'isAdmin' => (bool) $user->is_admin,
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
                'canCheckout' => $user->adminPlanOverride() === null && ! $user->subscribed('default'),
                'canManageBilling' => $subscription !== null,
                'subscriptionActive' => $subscription?->valid() ?? false,
                'subscriptionStatus' => $subscription?->stripe_status,
                'adminOverride' => $user->adminPlanOverride()?->value,
                'checkoutSuccess' => $request->boolean('checkout'),
                'checkoutCancelled' => $request->boolean('cancelled'),
            ],
        ]);
    }

    public function checkout(Request $request, string $plan): RedirectResponse|\Laravel\Cashier\Checkout
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

        $priceId = $membershipPlan->stripePriceId();

        if (! $priceId) {
            return back()->with('error', 'Stripe pricing is not configured for this plan yet.');
        }

        return $user
            ->newSubscription('default', $priceId)
            ->checkout([
                'success_url' => route('membership.show', ['checkout' => 1]),
                'cancel_url' => route('membership.show', ['cancelled' => 1]),
            ]);
    }

    public function portal(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        if (! $user->subscriptions()->exists()) {
            return redirect()->route('membership.show')->with('error', 'No Stripe billing portal is available until a subscription exists.');
        }

        return $user->redirectToBillingPortal(route('membership.show'));
    }

    protected function intervalLabel(int $seconds): string
    {
        return match (true) {
            $seconds < 60 => $seconds.' seconds',
            $seconds < 3600 => (int) round($seconds / 60).' minutes',
            default => (int) round($seconds / 3600).' hours',
        };
    }
}

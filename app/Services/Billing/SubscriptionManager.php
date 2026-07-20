<?php

namespace App\Services\Billing;

use App\Enums\MembershipPlan;
use App\Models\User;
use Laravel\Cashier\Subscription;
use RuntimeException;

class SubscriptionManager
{
    public function changePlan(User $user, MembershipPlan $plan): void
    {
        $priceId = $this->configuredPriceId($plan);
        $subscription = $this->subscription($user);

        if (! $subscription || $subscription->ended()) {
            throw new RuntimeException('This subscription has ended. Reactivate it instead.');
        }

        if ($subscription->hasPrice($priceId) && ! $subscription->onGracePeriod()) {
            throw new RuntimeException(sprintf('%s is already on the %s Stripe plan.', $user->email, $plan->label()));
        }

        $subscription->swap($priceId);
        $this->forgetSubscriptionState($user);
    }

    public function cancel(User $user): void
    {
        $this->ensureStripeIsConfigured();
        $subscription = $this->subscription($user);

        if (! $subscription || $subscription->ended()) {
            throw new RuntimeException('There is no current Stripe subscription to cancel.');
        }

        if ($subscription->onGracePeriod()) {
            throw new RuntimeException('This subscription is already scheduled to cancel.');
        }

        $subscription->cancel();
        $this->forgetSubscriptionState($user);
    }

    public function reactivate(User $user, MembershipPlan $plan): void
    {
        $priceId = $this->configuredPriceId($plan);
        $subscription = $this->subscription($user);

        if ($subscription?->onGracePeriod()) {
            if ($subscription->hasPrice($priceId)) {
                $subscription->resume();
            } else {
                $subscription->swap($priceId);
            }

            $this->forgetSubscriptionState($user);

            return;
        }

        if ($subscription && ! $subscription->ended()) {
            throw new RuntimeException('The Stripe subscription has not ended and cannot be reactivated.');
        }

        if (blank($user->stripe_id)) {
            throw new RuntimeException('This account has no Stripe customer. Ask the customer to complete checkout first.');
        }

        $paymentMethod = $user->defaultPaymentMethod();

        if (! $paymentMethod) {
            throw new RuntimeException('This Stripe customer has no reusable default payment method.');
        }

        $user->newSubscription('default', $priceId)->create($paymentMethod);
        $this->forgetSubscriptionState($user);
    }

    public function cancelImmediatelyForDeletion(User $user): void
    {
        $subscription = $this->subscription($user);

        if (! $subscription || $subscription->ended()) {
            return;
        }

        $this->ensureStripeIsConfigured();
        $subscription->cancelNow();
        $this->forgetSubscriptionState($user);
    }

    protected function configuredPriceId(MembershipPlan $plan): string
    {
        if ($plan === MembershipPlan::FREE) {
            throw new RuntimeException('The Free plan does not have a Stripe subscription price.');
        }

        $this->ensureStripeIsConfigured();
        $priceId = trim((string) $plan->stripePriceId());

        if ($priceId === '') {
            throw new RuntimeException(sprintf('The Stripe price for %s is not configured.', $plan->label()));
        }

        return $priceId;
    }

    protected function ensureStripeIsConfigured(): void
    {
        if (
            blank(config('cashier.key'))
            || blank(config('cashier.secret'))
            || blank(config('cashier.webhook.secret'))
        ) {
            throw new RuntimeException('Stripe keys and the webhook signing secret must be configured first.');
        }
    }

    protected function subscription(User $user): ?Subscription
    {
        $user->loadMissing('subscriptions.items');

        return $user->subscription('default');
    }

    protected function forgetSubscriptionState(User $user): void
    {
        $user->unsetRelation('subscriptions');
        $user->refresh();
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MembershipPlan;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Billing\SubscriptionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class AdminUserSubscriptionController extends Controller
{
    public function __construct(protected SubscriptionManager $subscriptions) {}

    public function update(Request $request, User $user): RedirectResponse
    {
        $plan = $this->paidPlan($request);

        try {
            $this->subscriptions->changePlan($user, $plan);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Stripe could not change this subscription. No plan change was applied.');
        }

        $this->audit($request, 'subscription.plan_changed', $user, $plan);

        return back()->with('success', sprintf('%s now has the %s Stripe subscription.', $user->email, $plan->label()));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        try {
            $this->subscriptions->cancel($user);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Stripe could not schedule this subscription for cancellation.');
        }

        $this->audit($request, 'subscription.cancelled', $user);

        return back()->with('success', sprintf('%s will keep access until the Stripe billing period ends.', $user->email));
    }

    public function reactivate(Request $request, User $user): RedirectResponse
    {
        $plan = $this->paidPlan($request);

        try {
            $this->subscriptions->reactivate($user, $plan);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Stripe could not reactivate this subscription.');
        }

        $this->audit($request, 'subscription.reactivated', $user, $plan);

        return back()->with('success', sprintf('%s is active on the %s Stripe subscription.', $user->email, $plan->label()));
    }

    protected function paidPlan(Request $request): MembershipPlan
    {
        $data = $request->validate([
            'plan' => ['required', Rule::in(array_map(
                fn (MembershipPlan $plan) => $plan->value,
                MembershipPlan::paidCases(),
            ))],
        ]);

        return MembershipPlan::from($data['plan']);
    }

    protected function audit(Request $request, string $action, User $subject, ?MembershipPlan $plan = null): void
    {
        Log::notice('Main admin Stripe subscription action.', [
            'action' => $action,
            'actor_user_id' => $request->user()?->id,
            'subject_user_id' => $subject->id,
            'subject_email' => $subject->email,
            'plan' => $plan?->value,
        ]);
    }
}

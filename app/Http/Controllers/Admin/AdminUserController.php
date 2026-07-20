<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MembershipPlan;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\AdminPresenter;
use App\Services\Billing\SubscriptionManager;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AdminUserController extends Controller
{
    public function __construct(
        protected AdminPresenter $presenter,
        protected SubscriptionManager $subscriptions,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('admin/users', $this->presenter->users(
            (string) $request->string('search')->trim(),
            (int) $request->integer('page', 1),
        ));
    }

    public function show(User $user): Response
    {
        return Inertia::render('admin/user-show', [
            ...$this->presenter->user($user),
            'stripeInvoices' => Inertia::optional(fn () => $this->presenter->invoices($user)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'password_login_enabled' => true,
            'email_verified_at' => now(),
            'is_admin' => false,
        ]);
        $this->audit($request, 'account.created', $user);

        return back()->with('success', sprintf('User %s created.', $data['email']));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'email_verified' => ['required', 'boolean'],
            'password_login_enabled' => ['required', 'boolean'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        if (
            $user->isMainAdmin()
            && strcasecmp((string) $data['email'], (string) config('realuptime.admin.main_admin_email')) !== 0
        ) {
            return back()->with('error', 'Change REALUPTIME_MAIN_ADMIN_EMAIL before changing the main admin email address.');
        }

        if (
            $user->isMainAdmin()
            && (! $data['email_verified'] || ! $data['password_login_enabled'])
        ) {
            return back()->with('error', 'The main admin must remain verified with password login enabled.');
        }

        if (
            filled($user->stripe_id)
            && ($user->name !== $data['name'] || strcasecmp($user->email, $data['email']) !== 0)
        ) {
            if (blank(config('cashier.secret'))) {
                return back()->with('error', 'Stripe must be configured before changing details for a Stripe customer.');
            }

            try {
                $user->updateStripeCustomer([
                    'name' => $data['name'],
                    'email' => $data['email'],
                ]);
            } catch (Throwable $exception) {
                report($exception);

                return back()->with('error', 'Stripe could not update this customer. No account details were changed.');
            }
        }

        $attributes = [
            'name' => $data['name'],
            'email' => $data['email'],
            'email_verified_at' => $data['email_verified'] ? ($user->email_verified_at ?? now()) : null,
            'password_login_enabled' => $data['password_login_enabled'],
        ];

        if (filled($data['password'] ?? null)) {
            $attributes['password'] = $data['password'];
        }

        $user->forceFill($attributes)->save();
        $this->audit($request, 'account.updated', $user, [
            'email_verified' => (bool) $data['email_verified'],
            'password_changed' => filled($data['password'] ?? null),
            'password_login_enabled' => (bool) $data['password_login_enabled'],
        ]);

        return back()->with('success', sprintf('Updated account details for %s.', $user->email));
    }

    public function updateMembership(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'admin_plan_override' => ['nullable', Rule::in(array_map(fn (MembershipPlan $plan) => $plan->value, MembershipPlan::cases()))],
        ]);

        $override = $data['admin_plan_override'] ?? null;

        $user->forceFill([
            'admin_plan_override' => $override,
            'admin_plan_assigned_by' => $override !== null ? $request->user()?->id : null,
            'admin_plan_assigned_at' => $override !== null ? now() : null,
        ])->save();
        $this->audit($request, 'membership.override.updated', $user, ['plan' => $override]);

        return back()->with('success', $override !== null
            ? sprintf('%s is now on the %s plan via admin override.', $user->email, MembershipPlan::from($override)->label())
            : sprintf('%s now uses subscription / default plan resolution.', $user->email));
    }

    public function extendSupportMembership(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', Rule::in(array_map(fn (MembershipPlan $plan) => $plan->value, MembershipPlan::paidCases()))],
        ]);

        $plan = MembershipPlan::from($data['plan']);
        $baseTime = $user->support_plan_expires_at !== null && $user->support_plan_expires_at->isFuture()
            ? CarbonImmutable::parse($user->support_plan_expires_at)
            : CarbonImmutable::now();
        $expiresAt = $baseTime->addMonthNoOverflow();

        $user->forceFill([
            'support_plan_extension' => $plan->value,
            'support_plan_granted_by' => $request->user()?->id,
            'support_plan_granted_at' => now(),
            'support_plan_expires_at' => $expiresAt,
        ])->save();
        $this->audit($request, 'membership.courtesy_extended', $user, [
            'plan' => $plan->value,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);

        return back()->with('success', sprintf(
            '%s now has a %s courtesy extension until %s.',
            $user->email,
            $plan->label(),
            $expiresAt->format('M j, Y H:i'),
        ));
    }

    public function clearSupportMembership(Request $request, User $user): RedirectResponse
    {
        $user->forceFill([
            'support_plan_extension' => null,
            'support_plan_granted_by' => null,
            'support_plan_granted_at' => null,
            'support_plan_expires_at' => null,
        ])->save();
        $this->audit($request, 'membership.courtesy_cleared', $user);

        return back()->with('success', sprintf('Cleared the courtesy extension for %s.', $user->email));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        if ($actor && $actor->id === $user->id) {
            return back()->with('error', 'You cannot delete your own account from the admin area.');
        }

        try {
            $this->subscriptions->cancelImmediatelyForDeletion($user);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Stripe billing could not be cancelled, so the account was not deleted.');
        }

        $email = $user->email;
        $this->audit($request, 'account.deleted', $user);
        $user->delete();

        return back()->with('success', sprintf('User %s deleted.', $email));
    }

    protected function audit(Request $request, string $action, User $subject, array $context = []): void
    {
        Log::notice('Main admin account action.', [
            'action' => $action,
            'actor_user_id' => $request->user()?->id,
            'subject_user_id' => $subject->id,
            'subject_email' => $subject->email,
            ...$context,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MembershipPlan;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\AdminPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function __construct(protected AdminPresenter $presenter) {}

    public function index(): Response
    {
        return Inertia::render('admin/users', $this->presenter->users());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'password_login_enabled' => true,
            'email_verified_at' => now(),
            'is_admin' => false,
        ]);

        return back()->with('success', sprintf('User %s created.', $data['email']));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'is_admin' => ['required', 'boolean'],
        ]);

        $actor = $request->user();
        $shouldBeAdmin = (bool) $data['is_admin'];

        if ($actor && $actor->id === $user->id && ! $shouldBeAdmin) {
            return back()->with('error', 'You cannot remove your own admin access while signed in.');
        }

        if ($user->is_admin && ! $shouldBeAdmin && User::query()->where('is_admin', true)->whereKeyNot($user->id)->doesntExist()) {
            return back()->with('error', 'At least one admin user must remain.');
        }

        $user->forceFill([
            'is_admin' => $shouldBeAdmin,
        ])->save();

        return back()->with('success', sprintf(
            '%s is now %s.',
            $user->email,
            $shouldBeAdmin ? 'an admin user' : 'a standard user',
        ));
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

        return back()->with('success', $override !== null
            ? sprintf('%s is now on the %s plan via admin override.', $user->email, MembershipPlan::from($override)->label())
            : sprintf('%s now uses subscription / default plan resolution.', $user->email));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        if ($actor && $actor->id === $user->id) {
            return back()->with('error', 'You cannot delete your own account from the admin area.');
        }

        if ($user->is_admin && User::query()->where('is_admin', true)->whereKeyNot($user->id)->doesntExist()) {
            return back()->with('error', 'At least one admin user must remain.');
        }

        $email = $user->email;
        $user->delete();

        return back()->with('success', sprintf('User %s deleted.', $email));
    }
}

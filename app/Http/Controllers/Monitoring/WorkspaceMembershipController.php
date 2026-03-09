<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Models\WorkspaceMembership;
use App\Notifications\WorkspaceInvitationNotification;
use App\Support\WorkspaceResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WorkspaceMembershipController extends Controller
{
    public function __construct(protected WorkspaceResolver $workspaces) {}

    public function store(Request $request): RedirectResponse
    {
        $owner = $this->workspaces->current($request);
        abort_unless($request->user()?->id === $owner->id, 403);

        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
        ]);

        $email = Str::lower($data['email']);

        if ($email === Str::lower($owner->email)) {
            throw ValidationException::withMessages([
                'email' => 'Invite a different email address.',
            ]);
        }

        $membership = WorkspaceMembership::query()
            ->where('owner_user_id', $owner->id)
            ->where('invited_email', $email)
            ->first();

        if ($membership?->isAccepted()) {
            return back()->with('success', sprintf('%s already has access to this workspace.', $membership->invited_email));
        }

        $membership = WorkspaceMembership::query()->updateOrCreate(
            [
                'owner_user_id' => $owner->id,
                'invited_email' => $email,
            ],
            [
                'member_user_id' => $membership?->member_user_id,
                'invited_by_user_id' => $request->user()?->id,
                'token' => Str::uuid()->toString(),
                'invited_at' => now(),
                'accepted_at' => null,
                'revoked_at' => null,
            ],
        );

        Notification::route('mail', $membership->invited_email)
            ->notify(new WorkspaceInvitationNotification($membership, $owner));

        return back()->with('success', sprintf('Invitation sent to %s.', $membership->invited_email));
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $membership = WorkspaceMembership::query()
            ->with('owner')
            ->where('token', $token)
            ->whereNull('revoked_at')
            ->firstOrFail();

        $user = $request->user();
        abort_unless($user !== null, 401);

        if (Str::lower($user->email) !== Str::lower($membership->invited_email)) {
            return redirect()->route('team-members.index')
                ->with('error', 'Sign in with the invited email address to accept this workspace invitation.');
        }

        $membership->forceFill([
            'member_user_id' => $user->id,
            'accepted_at' => $membership->accepted_at ?? now(),
        ])->save();

        $request->session()->put('workspace_owner_id', $membership->owner_user_id);

        return redirect()->route('team-members.index')
            ->with('success', sprintf('You now have access to %s\'s workspace.', $membership->owner?->name ?? 'the invited'));
    }

    public function switch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'owner_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $workspace = $this->workspaces->setCurrentWorkspace($request, (int) $data['owner_id']);

        return back()->with('success', sprintf('Switched to %s\'s workspace.', $workspace->name));
    }

    public function destroy(Request $request, WorkspaceMembership $workspaceMembership): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $canManage = $workspaceMembership->owner_user_id === $user->id
            || $workspaceMembership->member_user_id === $user->id;

        abort_unless($canManage, 404);

        $workspaceMembership->forceFill([
            'revoked_at' => now(),
        ])->save();

        if ((int) $request->session()->get('workspace_owner_id') === $workspaceMembership->owner_user_id
            && $workspaceMembership->member_user_id === $user->id
        ) {
            $request->session()->put('workspace_owner_id', $user->id);
        }

        return back()->with('success', 'Workspace access updated.');
    }
}

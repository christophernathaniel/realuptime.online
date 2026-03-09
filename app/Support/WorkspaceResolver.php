<?php

namespace App\Support;

use App\Models\User;
use App\Models\WorkspaceMembership;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class WorkspaceResolver
{
    public function current(Request $request): User
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $available = $this->availableFor($user);
        $selectedId = (int) $request->session()->get('workspace_owner_id', 0);

        $current = $available->firstWhere('id', $selectedId)
            ?? $available->firstWhere('id', $user->id)
            ?? $available->first();

        abort_unless($current !== null, 403);

        $request->session()->put('workspace_owner_id', $current->id);

        return $current;
    }

    /**
     * @return Collection<int, User>
     */
    public function availableFor(User $user): Collection
    {
        $ownerIds = WorkspaceMembership::query()
            ->where('member_user_id', $user->id)
            ->whereNotNull('accepted_at')
            ->whereNull('revoked_at')
            ->pluck('owner_user_id')
            ->prepend($user->id)
            ->unique()
            ->values();

        $owners = User::query()
            ->whereIn('id', $ownerIds)
            ->get()
            ->keyBy('id');

        return $ownerIds
            ->map(fn (int $id) => $owners->get($id))
            ->filter()
            ->values();
    }

    public function canAccess(User $user, User|int $owner): bool
    {
        $ownerId = $owner instanceof User ? $owner->id : $owner;

        if ($user->id === $ownerId) {
            return true;
        }

        return WorkspaceMembership::query()
            ->where('owner_user_id', $ownerId)
            ->where('member_user_id', $user->id)
            ->whereNotNull('accepted_at')
            ->whereNull('revoked_at')
            ->exists();
    }

    public function setCurrentWorkspace(Request $request, int $ownerId): User
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless($this->canAccess($user, $ownerId), 403);

        $request->session()->put('workspace_owner_id', $ownerId);

        return User::query()->findOrFail($ownerId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function share(Request $request): ?array
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        $available = $this->availableFor($user);
        $current = $this->current($request);
        $plan = $current->membershipPlan();

        return [
            'current' => [
                'id' => $current->id,
                'name' => $current->name,
                'email' => $current->email,
                'isPersonal' => $current->id === $user->id,
                'membership' => [
                    'plan' => $plan->value,
                    'planLabel' => $plan->label(),
                    'priceLabel' => $plan->priceLabel(),
                    'monitorLimit' => $current->monitorLimit(),
                    'monitorLimitLabel' => (string) $current->monitorLimit(),
                    'monitorCount' => $current->monitors()->count(),
                    'minimumIntervalLabel' => $this->intervalLabel($current->minimumMonitorIntervalSeconds()),
                    'advancedFeaturesUnlocked' => $current->allowsAdvancedWorkspaceFeatures(),
                    'manageUrl' => $current->id === $user->id ? route('membership.show') : null,
                ],
            ],
            'available' => $available->map(fn (User $workspace) => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'email' => $workspace->email,
                'isPersonal' => $workspace->id === $user->id,
            ])->all(),
        ];
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

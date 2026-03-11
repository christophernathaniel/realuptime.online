<?php

namespace App\Support;

use App\Models\User;
use App\Models\WorkspaceMembership;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class WorkspaceResolver
{
    protected const REQUEST_AVAILABLE_CACHE_KEY = 'workspace.available';

    protected const REQUEST_AVAILABLE_WITH_COUNTS_CACHE_KEY = 'workspace.available.with_counts';

    protected const REQUEST_CURRENT_CACHE_KEY = 'workspace.current';

    protected const REQUEST_CURRENT_WITH_COUNTS_CACHE_KEY = 'workspace.current.with_counts';

    public function current(Request $request): User
    {
        return $this->resolveCurrentWorkspace($request);
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

        $available = $this->availableForRequest($request, withMonitorCounts: true);
        $current = $this->resolveCurrentWorkspace($request, withMonitorCounts: true);
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
                    'monitorCount' => (int) ($current->monitors_count ?? 0),
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

    /**
     * @return Collection<int, User>
     */
    protected function availableForRequest(Request $request, bool $withMonitorCounts = false): Collection
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        if (! $withMonitorCounts && $request->attributes->has(self::REQUEST_AVAILABLE_WITH_COUNTS_CACHE_KEY)) {
            /** @var Collection<int, User> $available */
            $available = $request->attributes->get(self::REQUEST_AVAILABLE_WITH_COUNTS_CACHE_KEY);

            return $available;
        }

        $cacheKey = $withMonitorCounts
            ? self::REQUEST_AVAILABLE_WITH_COUNTS_CACHE_KEY
            : self::REQUEST_AVAILABLE_CACHE_KEY;

        if ($request->attributes->has($cacheKey)) {
            /** @var Collection<int, User> $available */
            $available = $request->attributes->get($cacheKey);

            return $available;
        }

        $ownerIds = WorkspaceMembership::query()
            ->where('member_user_id', $user->id)
            ->whereNotNull('accepted_at')
            ->whereNull('revoked_at')
            ->pluck('owner_user_id')
            ->prepend($user->id)
            ->unique()
            ->values();

        $ownersQuery = User::query()->whereIn('id', $ownerIds);

        if ($withMonitorCounts) {
            $ownersQuery->withCount('monitors');
        }

        $owners = $ownersQuery->get()->keyBy('id');

        $available = $ownerIds
            ->map(fn (int $id) => $owners->get($id))
            ->filter()
            ->values();

        $request->attributes->set($cacheKey, $available);

        if ($withMonitorCounts && ! $request->attributes->has(self::REQUEST_AVAILABLE_CACHE_KEY)) {
            $request->attributes->set(self::REQUEST_AVAILABLE_CACHE_KEY, $available);
        }

        return $available;
    }

    protected function resolveCurrentWorkspace(Request $request, bool $withMonitorCounts = false): User
    {
        $cacheKey = $withMonitorCounts
            ? self::REQUEST_CURRENT_WITH_COUNTS_CACHE_KEY
            : self::REQUEST_CURRENT_CACHE_KEY;

        if ($request->attributes->has($cacheKey)) {
            /** @var User $current */
            $current = $request->attributes->get($cacheKey);

            return $current;
        }

        $user = $request->user();
        abort_unless($user !== null, 401);

        $available = $this->availableForRequest($request, $withMonitorCounts);
        $selectedId = (int) $request->session()->get('workspace_owner_id', 0);

        $current = $available->firstWhere('id', $selectedId)
            ?? $available->firstWhere('id', $user->id)
            ?? $available->first();

        abort_unless($current !== null, 403);

        if ($selectedId !== $current->id) {
            $request->session()->put('workspace_owner_id', $current->id);
        }

        $request->attributes->set($cacheKey, $current);

        if ($withMonitorCounts && ! $request->attributes->has(self::REQUEST_CURRENT_CACHE_KEY)) {
            $request->attributes->set(self::REQUEST_CURRENT_CACHE_KEY, $current);
        }

        return $current;
    }
}

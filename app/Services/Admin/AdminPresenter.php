<?php

namespace App\Services\Admin;

use App\Models\Incident;
use App\Models\User;
use Carbon\CarbonImmutable;

class AdminPresenter
{
    public function users(): array
    {
        $openIncidentCounts = Incident::query()
            ->selectRaw('monitors.user_id, count(*) as aggregate')
            ->join('monitors', 'monitors.id', '=', 'incidents.monitor_id')
            ->whereNull('incidents.resolved_at')
            ->groupBy('monitors.user_id')
            ->pluck('aggregate', 'monitors.user_id');

        $users = User::query()
            ->withCount([
                'monitors',
                'notificationContacts',
                'statusPages',
                'trackedSessions as active_sessions_count' => fn ($query) => $query->whereNull('revoked_at'),
                'ownedWorkspaceMemberships as accepted_members_count' => fn ($query) => $query
                    ->whereNotNull('accepted_at')
                    ->whereNull('revoked_at'),
                'ownedWorkspaceMemberships as pending_invitations_count' => fn ($query) => $query
                    ->whereNull('accepted_at')
                    ->whereNull('revoked_at'),
            ])
            ->with('subscriptions.items')
            ->with('latestTrackedSession')
            ->orderByDesc('is_admin')
            ->orderBy('name')
            ->get();

        return [
            'summary' => [
                'users' => $users->count(),
                'admins' => $users->where('is_admin', true)->count(),
                'monitors' => $users->sum('monitors_count'),
                'statusPages' => $users->sum('status_pages_count'),
                'openIncidents' => $users->sum(fn (User $user) => (int) ($openIncidentCounts[$user->id] ?? 0)),
            ],
            'users' => $users->map(function (User $user) use ($openIncidentCounts): array {
                $lastSession = $user->latestTrackedSession;
                $lastActiveAt = $lastSession?->last_active_at;

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'isAdmin' => (bool) $user->is_admin,
                    'emailVerified' => $user->email_verified_at !== null,
                    'membershipPlan' => $user->membershipPlan()->value,
                    'membershipPlanLabel' => $user->membershipPlan()->label(),
                    'membershipSource' => $user->membershipSource(),
                    'adminPlanOverride' => $user->admin_plan_override,
                    'hasSubscription' => $user->subscription('default') !== null,
                    'createdAt' => $user->created_at?->format('M j, Y'),
                    'lastActiveAt' => $lastActiveAt?->format('M j, Y H:i'),
                    'lastActiveLabel' => $lastActiveAt ? $this->timeAgo($lastActiveAt) : 'No tracked session',
                    'monitorsCount' => (int) $user->monitors_count,
                    'statusPagesCount' => (int) $user->status_pages_count,
                    'contactsCount' => (int) $user->notification_contacts_count,
                    'acceptedMembersCount' => (int) $user->accepted_members_count,
                    'pendingInvitationsCount' => (int) $user->pending_invitations_count,
                    'activeSessionsCount' => (int) $user->active_sessions_count,
                    'openIncidentsCount' => (int) ($openIncidentCounts[$user->id] ?? 0),
                ];
            })->all(),
            'formDefaults' => [
                'name' => '',
                'email' => '',
                'password' => '',
                'password_confirmation' => '',
            ],
        ];
    }

    protected function timeAgo($time): string
    {
        $seconds = CarbonImmutable::parse($time)->diffInSeconds(now());

        if ($seconds < 60) {
            return $seconds.' sec ago';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours > 0) {
            return $minutes > 0 ? sprintf('%dh %dm ago', $hours, $minutes) : sprintf('%dh ago', $hours);
        }

        return sprintf('%dm ago', $minutes);
    }
}

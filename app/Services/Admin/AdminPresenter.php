<?php

namespace App\Services\Admin;

use App\Enums\MembershipPlan;
use App\Models\ApiToken;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\NotificationContact;
use App\Models\NotificationLog;
use App\Models\StatusPage;
use App\Models\User;
use App\Models\UserSession;
use App\Models\WorkspaceIntegration;
use App\Models\WorkspaceMembership;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Laravel\Cashier\Invoice;
use Throwable;

class AdminPresenter
{
    public function users(?string $search = null, int $page = 1): array
    {
        $search = trim((string) $search);
        $openIncidentCounts = $this->openIncidentCountsByUser();

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
            ->with(['subscriptions.items', 'latestTrackedSession'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $userQuery) use ($search): void {
                    $userQuery->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->orderByDesc('is_admin')
            ->orderByDesc('created_at')
            ->orderBy('name')
            ->paginate(16, ['*'], 'page', $page)
            ->withQueryString();

        return [
            'summary' => [
                'users' => User::query()->count(),
                'admins' => User::query()->where('is_admin', true)->count(),
                'monitors' => Monitor::query()->count(),
                'supportExtensions' => User::query()
                    ->whereNotNull('support_plan_extension')
                    ->where('support_plan_expires_at', '>', now())
                    ->count(),
                'openIncidents' => Incident::query()->whereNull('resolved_at')->count(),
            ],
            'filters' => [
                'search' => $search,
            ],
            'users' => $this->paginateData($users, fn (User $user) => $this->userListItem($user, $openIncidentCounts)),
            'formDefaults' => [
                'name' => '',
                'email' => '',
                'password' => '',
                'password_confirmation' => '',
            ],
            'supportPlanOptions' => $this->supportPlanOptions(),
        ];
    }

    public function user(User $user): array
    {
        $user->loadMissing([
            'subscriptions.items',
            'latestTrackedSession',
            'assignedAdmin:id,name,email',
            'supportPlanGranter:id,name,email',
            'monitors' => fn ($query) => $query
                ->withCount(['openIncidents', 'checkResults', 'notificationLogs'])
                ->with([
                    'statusPages:id,name,slug',
                    'notificationContacts:id,name',
                    'capabilities:id,name',
                ])
                ->orderByRaw("case when status = 'down' then 0 when status = 'up' then 1 else 2 end")
                ->orderBy('name'),
            'statusPages' => fn ($query) => $query
                ->withCount(['monitors', 'incidents'])
                ->with('monitors:id,name')
                ->orderByDesc('published')
                ->orderBy('name'),
            'notificationContacts' => fn ($query) => $query
                ->withCount('notificationLogs')
                ->with('monitors:id,name')
                ->orderByDesc('is_primary')
                ->orderBy('name'),
            'workspaceIntegrations' => fn ($query) => $query
                ->withCount('notificationLogs')
                ->latest(),
            'apiTokens' => fn ($query) => $query->latest(),
            'ownedWorkspaceMemberships' => fn ($query) => $query
                ->with(['member:id,name,email', 'inviter:id,name,email'])
                ->orderByDesc('accepted_at')
                ->orderByDesc('invited_at'),
            'trackedSessions' => fn ($query) => $query
                ->latest('last_active_at')
                ->limit(8),
        ])->loadCount([
            'monitors',
            'notificationContacts',
            'statusPages',
            'workspaceIntegrations',
            'apiTokens',
            'trackedSessions as active_sessions_count' => fn ($query) => $query->whereNull('revoked_at'),
            'ownedWorkspaceMemberships as accepted_members_count' => fn ($query) => $query
                ->whereNotNull('accepted_at')
                ->whereNull('revoked_at'),
            'ownedWorkspaceMemberships as pending_invitations_count' => fn ($query) => $query
                ->whereNull('accepted_at')
                ->whereNull('revoked_at'),
        ]);

        $openIncidentsCount = Incident::query()
            ->whereNull('resolved_at')
            ->whereHas('monitor', fn (Builder $query) => $query->where('user_id', $user->id))
            ->count();

        $recentIncidents = Incident::query()
            ->whereHas('monitor', fn (Builder $query) => $query->where('user_id', $user->id))
            ->with('monitor:id,name')
            ->latest('started_at')
            ->limit(10)
            ->get();

        $recentLogs = NotificationLog::query()
            ->whereHas('monitor', fn (Builder $query) => $query->where('user_id', $user->id))
            ->with(['monitor:id,name', 'notificationContact:id,name,email', 'integration:id,name'])
            ->latest()
            ->limit(12)
            ->get();

        return [
            'account' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'isAdmin' => (bool) $user->is_admin,
                'emailVerified' => $user->email_verified_at !== null,
                'createdAt' => $user->created_at?->format('M j, Y H:i'),
                'lastActiveAt' => $user->latestTrackedSession?->last_active_at?->format('M j, Y H:i'),
                'lastActiveLabel' => $user->latestTrackedSession?->last_active_at
                    ? $this->timeAgo($user->latestTrackedSession->last_active_at)
                    : 'No tracked session',
                'membershipPlan' => $user->membershipPlan()->value,
                'membershipPlanLabel' => $user->membershipPlan()->label(),
                'membershipSource' => $user->membershipSource(),
                'membershipSourceLabel' => match ($user->membershipSource()) {
                    'admin' => 'Admin override',
                    'stripe' => 'Stripe subscription',
                    'support' => 'Courtesy extension',
                    default => 'Free default',
                },
                'publicStatusKey' => $user->public_status_key,
            ],
            'usage' => [
                'monitors' => (int) $user->monitors_count,
                'statusPages' => (int) $user->status_pages_count,
                'contacts' => (int) $user->notification_contacts_count,
                'integrations' => (int) $user->workspace_integrations_count,
                'apiTokens' => (int) $user->api_tokens_count,
                'activeSessions' => (int) $user->active_sessions_count,
                'acceptedMembers' => (int) $user->accepted_members_count,
                'pendingInvitations' => (int) $user->pending_invitations_count,
                'openIncidents' => $openIncidentsCount,
            ],
            'support' => [
                'adminOverride' => $user->adminPlanOverride() ? [
                    'plan' => $user->adminPlanOverride()?->value,
                    'planLabel' => $user->adminPlanOverride()?->label(),
                    'assignedAt' => $user->admin_plan_assigned_at?->format('M j, Y H:i'),
                    'assignedBy' => $user->assignedAdmin?->name ?? $user->assignedAdmin?->email,
                ] : null,
                'courtesyExtension' => $user->supportPlanExtension() ? [
                    'plan' => $user->supportPlanExtension()?->value,
                    'planLabel' => $user->supportPlanExtension()?->label(),
                    'grantedAt' => $user->support_plan_granted_at?->format('M j, Y H:i'),
                    'grantedBy' => $user->supportPlanGranter?->name ?? $user->supportPlanGranter?->email,
                    'expiresAt' => $user->support_plan_expires_at?->format('M j, Y H:i'),
                    'expiresLabel' => $user->support_plan_expires_at?->diffForHumans(),
                ] : null,
                'supportPlanOptions' => $this->supportPlanOptions(),
            ],
            'billing' => $this->billingData($user),
            'monitors' => $user->monitors->map(function (Monitor $monitor): array {
                return [
                    'id' => $monitor->id,
                    'name' => $monitor->name,
                    'status' => ucfirst($monitor->status),
                    'statusValue' => $monitor->status,
                    'typeLabel' => $this->monitorTypeLabel($monitor),
                    'target' => $monitor->target,
                    'region' => $monitor->region,
                    'intervalLabel' => $this->secondsLabel($monitor->interval_seconds),
                    'timeoutLabel' => $this->secondsLabel($monitor->timeout_seconds),
                    'retries' => $monitor->retry_limit,
                    'lastCheckedAt' => $monitor->last_checked_at?->format('M j, Y H:i'),
                    'lastCheckedLabel' => $monitor->last_checked_at ? $this->timeAgo($monitor->last_checked_at) : 'Never checked',
                    'lastResponseTimeLabel' => $monitor->last_response_time_ms !== null ? $monitor->last_response_time_ms.' ms' : 'No response recorded',
                    'lastHttpStatus' => $monitor->last_http_status,
                    'lastError' => $monitor->last_error_message,
                    'openIncidentsCount' => (int) $monitor->open_incidents_count,
                    'checkResultsCount' => (int) $monitor->check_results_count,
                    'notificationLogsCount' => (int) $monitor->notification_logs_count,
                    'statusPages' => $monitor->statusPages->pluck('name')->values()->all(),
                    'contacts' => $monitor->notificationContacts->pluck('name')->values()->all(),
                    'capabilities' => $monitor->capabilities->pluck('name')->values()->all(),
                    'showUrl' => route('monitors.show', $monitor),
                ];
            })->all(),
            'statusPages' => $user->statusPages->map(function (StatusPage $statusPage) use ($user): array {
                return [
                    'id' => $statusPage->id,
                    'name' => $statusPage->name,
                    'headline' => $statusPage->headline,
                    'published' => (bool) $statusPage->published,
                    'slug' => $statusPage->slug,
                    'monitorCount' => (int) $statusPage->monitors_count,
                    'incidentsCount' => (int) $statusPage->incidents_count,
                    'monitorNames' => $statusPage->monitors->pluck('name')->take(4)->values()->all(),
                    'publicUrl' => url(sprintf('/status/%s/%s', $user->public_status_key, $statusPage->slug)),
                ];
            })->all(),
            'contacts' => $user->notificationContacts->map(function (NotificationContact $contact): array {
                return [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'email' => $contact->email,
                    'enabled' => (bool) $contact->enabled,
                    'isPrimary' => (bool) $contact->is_primary,
                    'logsCount' => (int) $contact->notification_logs_count,
                    'monitorNames' => $contact->monitors->pluck('name')->take(4)->values()->all(),
                ];
            })->all(),
            'integrations' => $user->workspaceIntegrations->map(function (WorkspaceIntegration $integration): array {
                return [
                    'id' => $integration->id,
                    'name' => $integration->name,
                    'provider' => $integration->provider,
                    'status' => $integration->isActive() ? 'Active' : 'Disabled',
                    'events' => collect($integration->scopes ?? [])->values()->all(),
                    'notificationLogsCount' => (int) $integration->notification_logs_count,
                    'lastTestedAt' => $integration->last_tested_at?->format('M j, Y H:i'),
                    'lastError' => $integration->last_error_message,
                ];
            })->all(),
            'apiTokens' => $user->apiTokens->map(function (ApiToken $token): array {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'createdAt' => $token->created_at?->format('M j, Y H:i'),
                    'lastUsedAt' => $token->last_used_at?->format('M j, Y H:i'),
                    'lastUsedLabel' => $token->last_used_at ? $this->timeAgo($token->last_used_at) : 'Never used',
                ];
            })->all(),
            'team' => $user->ownedWorkspaceMemberships->map(function (WorkspaceMembership $membership): array {
                $member = $membership->member;

                return [
                    'id' => $membership->id,
                    'email' => $membership->invited_email,
                    'memberName' => $member?->name,
                    'memberEmail' => $member?->email,
                    'status' => $membership->isAccepted() ? 'Accepted' : ($membership->isPending() ? 'Pending' : 'Revoked'),
                    'invitedAt' => $membership->invited_at?->format('M j, Y H:i'),
                    'acceptedAt' => $membership->accepted_at?->format('M j, Y H:i'),
                    'invitedBy' => $membership->inviter?->name ?? $membership->inviter?->email,
                ];
            })->all(),
            'sessions' => $user->trackedSessions->map(function (UserSession $session): array {
                return [
                    'id' => $session->id,
                    'active' => $session->revoked_at === null,
                    'lastActiveAt' => $session->last_active_at?->format('M j, Y H:i'),
                    'lastActiveLabel' => $session->last_active_at ? $this->timeAgo($session->last_active_at) : 'Unknown',
                    'lastPath' => $session->last_path,
                    'ipAddress' => $session->ip_address,
                    'userAgent' => Str::limit((string) $session->user_agent, 120),
                ];
            })->all(),
            'recentIncidents' => $recentIncidents->map(function (Incident $incident): array {
                return [
                    'id' => $incident->id,
                    'monitor' => $incident->monitor?->name,
                    'status' => $incident->resolved_at ? 'Resolved' : 'Open',
                    'startedAt' => $incident->started_at?->format('M j, Y H:i'),
                    'resolvedAt' => $incident->resolved_at?->format('M j, Y H:i'),
                    'duration' => $incident->duration_seconds ? $this->durationLabel((int) $incident->duration_seconds) : null,
                    'reason' => $incident->reason,
                ];
            })->all(),
            'recentNotifications' => $recentLogs->map(fn (NotificationLog $log) => [
                'id' => $log->id,
                'type' => ucfirst(str_replace('_', ' ', $log->type)),
                'channel' => ucfirst($log->channel),
                'status' => ucfirst($log->status),
                'subject' => $log->subject,
                'destination' => $this->notificationDestinationLabel($log),
                'monitor' => $log->monitor?->name,
                'sentAt' => $log->sent_at?->format('M j, Y H:i') ?? $log->created_at?->format('M j, Y H:i'),
                'failureMessage' => $log->failure_message,
            ])->all(),
        ];
    }

    protected function userListItem(User $user, array $openIncidentCounts): array
    {
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
            'supportExtension' => $user->supportPlanExtension() ? [
                'plan' => $user->supportPlanExtension()?->value,
                'planLabel' => $user->supportPlanExtension()?->label(),
                'expiresAt' => $user->support_plan_expires_at?->format('M j, Y'),
            ] : null,
            'showUrl' => route('admin.users.show', $user),
        ];
    }

    protected function billingData(User $user): array
    {
        $subscription = $user->subscription('default');
        $subscriptionPlan = $user->subscriptionPlan();
        $currentSubscription = $subscription ? [
            'stripeId' => $subscription->stripe_id,
            'status' => $subscription->stripe_status,
            'planLabel' => $subscriptionPlan?->label(),
            'priceIds' => $subscription->items->isNotEmpty()
                ? $subscription->items->pluck('stripe_price')->values()->all()
                : array_values(array_filter([$subscription->stripe_price])),
            'quantity' => $subscription->quantity,
            'trialEndsAt' => $subscription->trial_ends_at?->format('M j, Y H:i'),
            'endsAt' => $subscription->ends_at?->format('M j, Y H:i'),
            'createdAt' => $subscription->created_at?->format('M j, Y H:i'),
            'valid' => $subscription->valid(),
        ] : null;

        $paymentMethodLabel = $user->pm_type && $user->pm_last_four
            ? sprintf('%s ending in %s', ucfirst($user->pm_type), $user->pm_last_four)
            : 'No saved card on record';

        $invoices = [];
        $invoiceStatus = 'unavailable';
        $invoiceError = null;

        if (blank($user->stripe_id)) {
            $invoiceStatus = 'none';
        } elseif (blank(config('cashier.secret'))) {
            $invoiceStatus = 'error';
            $invoiceError = 'Stripe secret is not configured in this environment.';
        } else {
            try {
                $invoices = $user->invoices(true, ['limit' => 12])
                    ->map(fn (Invoice $invoice) => $this->invoiceItem($invoice))
                    ->values()
                    ->all();
                $invoiceStatus = 'loaded';
            } catch (Throwable $exception) {
                $invoiceStatus = 'error';
                $invoiceError = 'Stripe invoice history is currently unavailable.';
            }
        }

        return [
            'customerId' => $user->stripe_id,
            'paymentMethodLabel' => $paymentMethodLabel,
            'currentSubscription' => $currentSubscription,
            'invoiceStatus' => $invoiceStatus,
            'invoiceError' => $invoiceError,
            'invoices' => $invoices,
        ];
    }

    protected function invoiceItem(Invoice $invoice): array
    {
        $stripeInvoice = $invoice->asStripeInvoice();
        $paidAt = data_get($stripeInvoice, 'status_transitions.paid_at');
        $status = (string) ($stripeInvoice->status ?? 'unknown');
        $attempted = (bool) ($stripeInvoice->attempted ?? false);
        $amountRemaining = (int) ($stripeInvoice->amount_remaining ?? 0);
        $tone = $status === 'paid'
            ? 'paid'
            : ($attempted && $amountRemaining > 0 ? 'failed' : 'pending');

        return [
            'id' => $stripeInvoice->id,
            'number' => $stripeInvoice->number,
            'status' => Str::headline($status),
            'tone' => $tone,
            'date' => $invoice->date()->format('M j, Y'),
            'dueDate' => $invoice->dueDate()?->format('M j, Y'),
            'paidAt' => $paidAt ? CarbonImmutable::createFromTimestampUTC((int) $paidAt)->format('M j, Y H:i') : null,
            'total' => $invoice->total(),
            'amountPaid' => $invoice->amountPaid(),
            'currency' => strtoupper((string) ($stripeInvoice->currency ?? 'gbp')),
            'attemptCount' => (int) ($stripeInvoice->attempt_count ?? 0),
            'hostedInvoiceUrl' => $stripeInvoice->hosted_invoice_url,
            'invoicePdf' => $stripeInvoice->invoice_pdf,
        ];
    }

    protected function openIncidentCountsByUser(): array
    {
        return Incident::query()
            ->selectRaw('monitors.user_id, count(*) as aggregate')
            ->join('monitors', 'monitors.id', '=', 'incidents.monitor_id')
            ->whereNull('incidents.resolved_at')
            ->groupBy('monitors.user_id')
            ->pluck('aggregate', 'monitors.user_id')
            ->all();
    }

    protected function paginateData(LengthAwarePaginator $paginator, callable $mapper): array
    {
        return [
            'data' => collect($paginator->items())->map($mapper)->values()->all(),
            'currentPage' => $paginator->currentPage(),
            'lastPage' => $paginator->lastPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'previousPageUrl' => $paginator->previousPageUrl(),
            'nextPageUrl' => $paginator->nextPageUrl(),
        ];
    }

    protected function notificationDestinationLabel(NotificationLog $log): string
    {
        if ($log->notificationContact) {
            return sprintf('%s (%s)', $log->notificationContact->name, $log->notificationContact->email);
        }

        if ($log->integration) {
            return $log->integration->name;
        }

        return 'Unknown destination';
    }

    protected function supportPlanOptions(): array
    {
        return collect(MembershipPlan::paidCases())
            ->map(fn (MembershipPlan $plan) => [
                'value' => $plan->value,
                'label' => $plan->label(),
            ])
            ->values()
            ->all();
    }

    protected function monitorTypeLabel(Monitor $monitor): string
    {
        return match ($monitor->type) {
            Monitor::TYPE_HTTP => 'HTTP(S) Monitor',
            Monitor::TYPE_PORT => 'Port Monitor',
            Monitor::TYPE_PING => 'Ping Monitor',
            Monitor::TYPE_KEYWORD => 'Keyword Monitor',
            Monitor::TYPE_SSL => 'SSL Certificate Monitor',
            Monitor::TYPE_HEARTBEAT => 'Heartbeat Monitor',
            Monitor::TYPE_SYNTHETIC => 'Synthetic Transaction Monitor',
            default => 'Monitor',
        };
    }

    protected function secondsLabel(?int $seconds): string
    {
        if ($seconds === null) {
            return 'Not configured';
        }

        if ($seconds < 60) {
            return $seconds.' sec';
        }

        if ($seconds < 3600) {
            return (int) round($seconds / 60).' min';
        }

        return (int) round($seconds / 3600).' hr';
    }

    protected function durationLabel(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.' sec';
        }

        if ($seconds < 3600) {
            return (int) round($seconds / 60).' min';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return $minutes > 0 ? sprintf('%dh %dm', $hours, $minutes) : sprintf('%dh', $hours);
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

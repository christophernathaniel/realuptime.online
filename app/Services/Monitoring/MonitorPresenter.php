<?php

namespace App\Services\Monitoring;

use App\Models\Capability;
use App\Models\CheckResult;
use App\Models\Incident;
use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use App\Models\NotificationLog;
use App\Models\StatusPage;
use App\Models\StatusPageIncident;
use App\Models\User;
use App\Models\WorkspaceMembership;
use App\Support\MonitorQueueResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;

class MonitorPresenter
{
    protected ?CarbonImmutable $requestTime = null;

    /**
     * @var array<string, array{
     *     incidents: \Illuminate\Support\Collection<int, Incident>,
     *     monitoringStart: CarbonImmutable|null,
     *     monitoredSeconds: int,
     *     downtimeSeconds: int
     * }>
     */
    protected array $downtimeWindowCache = [];

    /**
     * @var array<string, Collection<int, CheckResult>>
     */
    protected array $windowCheckResultCache = [];

    /**
     * @var array<string, array{firstCheckedAt: CarbonImmutable|null, totalResults: int, downResults: int}>
     */
    protected array $windowCheckResultSummaryCache = [];

    /**
     * @var array<int, array<int, array{
     *     from: CarbonImmutable,
     *     to: CarbonImmutable,
     *     incidents: Collection<int, Incident>
     * }>>
     */
    protected array $preloadedDowntimeIncidents = [];

    /**
     * @var array<int, array<int, array{
     *     from: CarbonImmutable,
     *     to: CarbonImmutable,
     *     results: Collection<int, CheckResult>
     * }>>
     */
    protected array $preloadedCheckResults = [];

    public function index(User $user, ?User $actor = null): array
    {
        return [
            ...$this->indexPage($user, $actor),
            'capabilities' => $this->indexCapabilities($user),
        ];
    }

    public function indexPage(User $user, ?User $actor = null): array
    {
        $monitors = $user->monitors()
            ->with([
                'user',
                'incidents' => fn ($query) => $query->latest('started_at')->limit(20),
            ])
            ->orderBy('created_at')
            ->get();
        $now = $this->currentTime();

        $this->preloadDowntimeIncidents($monitors, $now->subDay(), $now);
        $this->preloadCheckResults($monitors, $now->subDay(), $now);

        $summary = [
            'up' => $monitors->where('status', Monitor::STATUS_UP)->count(),
            'down' => $monitors->where('status', Monitor::STATUS_DOWN)->count(),
            'paused' => $monitors->where('status', Monitor::STATUS_PAUSED)->count(),
            'total' => $monitors->count(),
        ];

        return [
            'summary' => [
                ...$summary,
                'usageLabel' => sprintf(
                    'Using %d of %d monitors on the %s plan.',
                    $summary['total'],
                    $user->monitorLimit(),
                    $user->membershipPlan()->label(),
                ),
                'canCreate' => ! $user->hasReachedMonitorLimit(),
            ],
            'last24Hours' => $this->aggregateMonitorWindow($monitors, 1),
            'monitors' => $monitors->map(fn (Monitor $monitor) => $this->monitorListItem($monitor))->values()->all(),
        ];
    }

    public function indexCapabilities(User $user): array
    {
        return $this->capabilityOverview($user);
    }

    public function show(Monitor $monitor, ?string $responseRange = null, ?string $responseGranularity = null): array
    {
        $responseRange = $this->normalizeResponseRange($responseRange);
        $responseGranularity = $this->normalizeResponseGranularity($responseGranularity, $responseRange);

        $monitor = $this->loadMonitorShowCoreRelations($monitor);
        $monitor = $this->loadMonitorShowHistoryRelations($monitor);
        $monitor = $this->loadMonitorShowCapabilityRelations($monitor);

        return [
            'monitor' => [
                ...$this->monitorCorePayload($monitor),
                ...$this->monitorHistoryPayload($monitor, $responseRange, $responseGranularity),
                'capabilities' => $this->monitorCapabilityOverview($monitor),
            ],
        ];
    }

    public function showPage(Monitor $monitor): array
    {
        return [
            'monitor' => $this->monitorCorePayload(
                $this->loadMonitorShowCoreRelations($monitor)
            ),
        ];
    }

    public function showHistory(Monitor $monitor, ?string $responseRange = null, ?string $responseGranularity = null): array
    {
        $responseRange = $this->normalizeResponseRange($responseRange);
        $responseGranularity = $this->normalizeResponseGranularity($responseGranularity, $responseRange);

        return [
            'monitorHistory' => $this->monitorHistoryPayload(
                $this->loadMonitorShowHistoryRelations($monitor),
                $responseRange,
                $responseGranularity,
            ),
        ];
    }

    public function showCapabilities(Monitor $monitor): array
    {
        return [
            'monitorCapabilities' => $this->monitorCapabilityOverview(
                $this->loadMonitorShowCapabilityRelations($monitor)
            ),
        ];
    }

    protected function loadMonitorShowCoreRelations(Monitor $monitor): Monitor
    {
        $monitor->load([
            'user',
            'incidents' => fn ($query) => $query->latest('started_at')->limit(10),
            'notificationLogs' => fn ($query) => $query->with('notificationContact')->latest()->limit(8),
            'heartbeatEvents' => fn ($query) => $query->latest('received_at')->limit(1),
            'maintenanceWindows' => fn ($query) => $query->with('monitors:id,name')->orderBy('starts_at'),
            'statusPages',
        ]);

        return $monitor;
    }

    protected function loadMonitorShowHistoryRelations(Monitor $monitor): Monitor
    {
        $monitor->load([
            'user',
            'incidents' => fn ($query) => $query->latest('started_at')->limit(10),
        ]);

        return $monitor;
    }

    protected function loadMonitorShowCapabilityRelations(Monitor $monitor): Monitor
    {
        $monitor->load([
            'capabilities' => fn ($query) => $query
                ->with([
                    'monitors' => fn ($monitorQuery) => $this->applyCapabilityMonitorSummaryQuery($monitorQuery),
                ])
                ->orderBy('name'),
        ]);

        return $monitor;
    }

    /**
     * @return array<string, mixed>
     */
    protected function monitorCorePayload(Monitor $monitor): array
    {
        $nextMaintenance = $this->nextMaintenanceForMonitor($monitor);
        $effectiveIntervalSeconds = $this->effectiveIntervalSeconds($monitor);
        $heartbeatBaseline = $monitor->last_heartbeat_at
            ?? $monitor->heartbeatEvents->first()?->received_at
            ?? ($monitor->type === Monitor::TYPE_HEARTBEAT ? $monitor->created_at : null);
        $heartbeatDeadline = $heartbeatBaseline && $monitor->type === Monitor::TYPE_HEARTBEAT
            ? CarbonImmutable::parse($heartbeatBaseline)->addSeconds($effectiveIntervalSeconds + ($monitor->heartbeat_grace_seconds ?: 0))
            : null;

        return [
            'id' => $monitor->id,
            'name' => $monitor->name,
            'type' => strtoupper($monitor->type === Monitor::TYPE_HTTP ? 'http/s' : $monitor->type),
            'typeLabel' => $this->typeLabel($monitor),
            'status' => $monitor->status,
            'statusLabel' => ucfirst($monitor->status),
            'target' => $monitor->target,
            'targetLabel' => $monitor->target ?: 'Heartbeat monitor',
            'targetHost' => $this->targetHost($monitor),
            'lastCheckLabel' => $monitor->last_checked_at ? $this->timeAgo($monitor->last_checked_at) : 'Never checked',
            'checkedEveryLabel' => $this->intervalLabel($effectiveIntervalSeconds),
            'currentStatusLabel' => ucfirst($monitor->status),
            'currentStatusDurationValue' => $monitor->last_status_changed_at
                ? $this->durationLabel($monitor->last_status_changed_at->diffInSeconds(now()))
                : 'N/A',
            'currentStatusDurationLabel' => $monitor->last_status_changed_at
                ? sprintf('Currently %s for %s', $monitor->status, $this->durationLabel($monitor->last_status_changed_at->diffInSeconds(now())))
                : 'No status changes recorded',
            'domainSsl' => [
                'host' => $this->targetHost($monitor),
                'domainValidUntil' => $monitor->domain_expires_at?->format('M j, Y') ?? 'Unavailable',
                'domainRegistrar' => $monitor->domain_registrar ?? 'Unavailable',
                'domainDaysRemaining' => $this->daysRemainingLabel($monitor->domain_expires_at),
                'domainCheckedAt' => $monitor->domain_checked_at ? 'Refreshed '.$this->timeAgo($monitor->domain_checked_at) : 'No domain refresh yet',
                'sslValidUntil' => $monitor->ssl_expires_at?->format('M j, Y') ?? 'Unavailable',
                'issuer' => $monitor->ssl_issuer ?? 'Unavailable',
                'sslDaysRemaining' => $this->daysRemainingLabel($monitor->ssl_expires_at),
                'sslCheckedAt' => $monitor->ssl_checked_at ? 'Refreshed '.$this->timeAgo($monitor->ssl_checked_at) : 'No TLS refresh yet',
            ],
            'nextMaintenance' => $nextMaintenance ? $this->maintenanceWindowLabel($nextMaintenance) : 'No maintenance planned.',
            'maintenanceDefaults' => [
                'title' => '',
                'message' => sprintf('Planned maintenance for %s.', $monitor->name),
                'starts_at' => now()->addHour()->format('Y-m-d\TH:i'),
                'ends_at' => now()->addHours(2)->format('Y-m-d\TH:i'),
                'notify_contacts' => false,
                'monitor_ids' => [$monitor->id],
            ],
            'maintenanceWindows' => $monitor->maintenanceWindows
                ->filter(fn (MaintenanceWindow $window) => $window->ends_at?->gte(now()->subDays(1)))
                ->values()
                ->map(fn (MaintenanceWindow $window) => $this->maintenanceWindowItem($window))
                ->all(),
            'region' => $monitor->region,
            'heartbeatUrl' => $monitor->heartbeat_token ? route('heartbeat.store', $monitor->heartbeat_token) : null,
            'lastHeartbeatLabel' => $monitor->type === Monitor::TYPE_HEARTBEAT
                ? ($monitor->last_heartbeat_at ? $this->timeAgo($monitor->last_heartbeat_at) : 'No heartbeat received yet')
                : null,
            'nextHeartbeatDeadlineLabel' => $heartbeatDeadline?->format('M j, Y H:i'),
            'recentIncidents' => $monitor->incidents->map(fn (Incident $incident) => [
                'id' => $incident->id,
                'startedAt' => $incident->started_at?->format('M j, Y H:i'),
                'endedAt' => $incident->resolved_at?->format('M j, Y H:i') ?? 'Open',
                'duration' => $incident->duration_seconds !== null ? $this->durationLabel((int) $incident->duration_seconds) : 'Open',
                'reason' => $incident->reason,
                'typeLabel' => $this->incidentTypeLabel($incident),
                'severityLabel' => ucfirst($incident->severity),
                'statusLabel' => $this->incidentStatusLabel($incident),
                'showUrl' => route('incidents.show', $incident),
            ])->all(),
            'notificationLog' => $monitor->notificationLogs->map(fn ($log) => [
                'type' => ucfirst(str_replace('_', ' ', $log->type)),
                'channel' => ucfirst($log->channel),
                'status' => ucfirst($log->status),
                'subject' => $log->subject,
                'recipient' => $log->notificationContact?->email ?? data_get($log->payload, 'url'),
                'time' => $log->created_at->format('M j, H:i'),
            ])->all(),
            'statusPages' => $monitor->statusPages->map(fn (StatusPage $statusPage) => [
                'name' => $statusPage->name,
                'slug' => $statusPage->slug,
                'published' => $statusPage->published,
                'publicUrl' => $this->publicStatusPageUrl($statusPage),
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function monitorHistoryPayload(Monitor $monitor, string $responseRange, string $responseGranularity): array
    {
        $now = $this->currentTime();

        $this->preloadDowntimeIncidents(collect([$monitor]), $now->subYear(), $now);
        $this->preloadCheckResults(collect([$monitor]), $now->subDays(7), $now);

        $responseTimeData = $this->responseTimeData($monitor, $responseRange, $responseGranularity);

        return [
            'last6Bars' => $this->uptimeBars($monitor, 6, 12),
            'last24Bars' => $this->uptimeBars($monitor, 24, 24),
            'last7Bars' => $this->uptimeBars($monitor, 24 * 7, 28),
            'last6Hours' => $this->windowStatsByHours($monitor, 6),
            'last24Stats' => $this->windowStats($monitor, 1),
            'last7Days' => $this->windowStats($monitor, 7),
            'last30Days' => $this->windowStats($monitor, 30),
            'last365Days' => $this->windowStats($monitor, 365),
            'customRange' => $this->windowStats($monitor, 14),
            'mtbf' => $this->mtbfLabel($monitor, 7),
            'responseTimeRange' => $responseRange,
            'responseTimeRangeLabel' => $responseTimeData['label'],
            'responseTimeRangeOptions' => $this->responseTimeRangeOptions(),
            'responseTimeGranularity' => $responseGranularity,
            'responseTimeGranularityLabel' => $responseTimeData['granularity_label'],
            'responseTimeGranularityOptions' => $this->responseTimeGranularityOptions($responseRange),
            'responseTimeChart' => $responseTimeData['points'],
            'responseTimeStats' => $responseTimeData['stats'],
            'responseTimeSignals' => $responseTimeData['signals'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function monitorCapabilityOverview(Monitor $monitor): array
    {
        return $monitor->capabilities
            ->map(fn (Capability $capability) => $this->capabilityItem($capability))
            ->values()
            ->all();
    }

    protected function currentTime(): CarbonImmutable
    {
        return $this->requestTime ??= CarbonImmutable::now()->startOfSecond();
    }

    /**
     * @param  Collection<int, Monitor>  $monitors
     */
    protected function preloadDowntimeIncidents(Collection $monitors, CarbonImmutable $from, CarbonImmutable $to): void
    {
        if ($monitors->isEmpty()) {
            return;
        }

        $monitorIds = $monitors->pluck('id')->map(fn ($id) => (int) $id)->all();
        $grouped = Incident::query()
            ->whereIn('monitor_id', $monitorIds)
            ->where('type', Incident::TYPE_DOWNTIME)
            ->where('started_at', '<', $to)
            ->where(function ($query) use ($from): void {
                $query->whereNull('resolved_at')
                    ->orWhere('resolved_at', '>', $from);
            })
            ->orderBy('monitor_id')
            ->orderBy('started_at')
            ->get()
            ->groupBy('monitor_id');

        foreach ($monitorIds as $monitorId) {
            $this->rememberPreloadedDowntimeIncidents(
                (int) $monitorId,
                $from,
                $to,
                $grouped->get($monitorId, collect())->values()
            );
        }
    }

    /**
     * @param  Collection<int, Monitor>  $monitors
     */
    protected function preloadCheckResults(Collection $monitors, CarbonImmutable $from, CarbonImmutable $to): void
    {
        if ($monitors->isEmpty()) {
            return;
        }

        $monitorIds = $monitors->pluck('id')->map(fn ($id) => (int) $id)->all();
        $grouped = CheckResult::query()
            ->whereIn('monitor_id', $monitorIds)
            ->where('checked_at', '>=', $from)
            ->where('checked_at', '<', $to)
            ->orderBy('monitor_id')
            ->orderBy('checked_at')
            ->get(['id', 'monitor_id', 'status', 'checked_at', 'response_time_ms', 'meta'])
            ->groupBy('monitor_id');

        foreach ($monitorIds as $monitorId) {
            $this->rememberPreloadedCheckResults(
                (int) $monitorId,
                $from,
                $to,
                $grouped->get($monitorId, collect())->values()
            );
        }
    }

    /**
     * @param  Collection<int, Incident>  $incidents
     */
    protected function rememberPreloadedDowntimeIncidents(
        int $monitorId,
        CarbonImmutable $from,
        CarbonImmutable $to,
        Collection $incidents,
    ): void {
        $this->preloadedDowntimeIncidents[$monitorId][] = [
            'from' => $from,
            'to' => $to,
            'incidents' => $incidents,
        ];
    }

    /**
     * @param  Collection<int, CheckResult>  $results
     */
    protected function rememberPreloadedCheckResults(
        int $monitorId,
        CarbonImmutable $from,
        CarbonImmutable $to,
        Collection $results,
    ): void {
        $this->preloadedCheckResults[$monitorId][] = [
            'from' => $from,
            'to' => $to,
            'results' => $results,
        ];
    }

    /**
     * @return Collection<int, Incident>|null
     */
    protected function preloadedDowntimeIncidentsForWindow(Monitor $monitor, CarbonImmutable $from, CarbonImmutable $to): ?Collection
    {
        foreach ($this->preloadedDowntimeIncidents[$monitor->id] ?? [] as $window) {
            if ($window['from']->greaterThan($from) || $window['to']->lessThan($to)) {
                continue;
            }

            return $window['incidents']
                ->filter(fn (Incident $incident) => $incident->started_at?->lt($to)
                    && ($incident->resolved_at === null || $incident->resolved_at->gt($from)))
                ->values();
        }

        return null;
    }

    /**
     * @return Collection<int, CheckResult>|null
     */
    protected function preloadedCheckResultsForWindow(Monitor $monitor, CarbonImmutable $from, CarbonImmutable $to): ?Collection
    {
        foreach ($this->preloadedCheckResults[$monitor->id] ?? [] as $window) {
            if ($window['from']->greaterThan($from) || $window['to']->lessThan($to)) {
                continue;
            }

            return $window['results']
                ->filter(fn (CheckResult $result) => $result->checked_at?->greaterThanOrEqualTo($from)
                    && $result->checked_at->lt($to))
                ->values();
        }

        return null;
    }

    public function form(User $user, ?Monitor $monitor = null, ?User $actor = null): array
    {
        $contactIds = $monitor?->notificationContacts()->pluck('notification_contacts.id')->all()
            ?? $user->notificationContacts()->where('enabled', true)->pluck('id')->all();
        $minimumInterval = $user->minimumMonitorIntervalSeconds();
        $selectedInterval = max($monitor?->interval_seconds ?? 300, $minimumInterval);
        $plan = $user->membershipPlan();
        $guardrails = config('realuptime.guardrails');
        $maxTimeoutSeconds = (int) ($guardrails['max_timeout_seconds'] ?? 15);
        $maxRetryLimit = (int) ($guardrails['max_retry_limit'] ?? 2);
        $maxContactsPerMonitor = (int) ($guardrails['max_contacts_per_monitor'] ?? 5);
        $maxDowntimeWebhookUrls = (int) ($guardrails['max_downtime_webhook_urls'] ?? 2);
        $maxCustomHeaderCount = (int) ($guardrails['max_custom_header_count'] ?? 8);
        $maxCustomHeaderValueLength = (int) ($guardrails['max_custom_header_value_length'] ?? 256);

        return [
            'monitor' => [
                'id' => $monitor?->id,
                'name' => $monitor?->name ?? '',
                'type' => $monitor?->type ?? Monitor::TYPE_HTTP,
                'target' => $monitor?->target ?? 'https://',
                'request_method' => $monitor?->request_method ?? 'GET',
                'interval_seconds' => $selectedInterval,
                'timeout_seconds' => min($monitor?->timeout_seconds ?? $maxTimeoutSeconds, $maxTimeoutSeconds),
                'retry_limit' => min($monitor?->retry_limit ?? $maxRetryLimit, $maxRetryLimit),
                'follow_redirects' => $monitor?->follow_redirects ?? true,
                'custom_headers' => $monitor?->custom_headers ? json_encode($monitor->custom_headers, JSON_PRETTY_PRINT) : '',
                'auth_username' => $monitor?->auth_username ?? '',
                'auth_password' => $monitor?->auth_password ?? '',
                'expected_status_code' => $monitor?->expected_status_code ?? 200,
                'expected_keyword' => $monitor?->expected_keyword ?? '',
                'keyword_match_type' => $monitor?->keyword_match_type ?? 'contains',
                'packet_count' => $monitor?->packet_count ?? 1,
                'synthetic_steps' => $monitor?->synthetic_steps ? json_encode($monitor->synthetic_steps, JSON_PRETTY_PRINT) : '',
                'latency_threshold_ms' => $monitor?->latency_threshold_ms ?? 1500,
                'degraded_consecutive_checks' => $monitor?->degraded_consecutive_checks ?? 3,
                'critical_alert_after_minutes' => $monitor?->critical_alert_after_minutes ?? 30,
                'downtime_webhook_urls' => $monitor?->downtime_webhook_urls ? implode("\n", $monitor->downtime_webhook_urls) : '',
                'capability_names' => $monitor ? $monitor->capabilities()->orderBy('name')->pluck('name')->implode("\n") : '',
                'ssl_threshold_days' => $monitor?->ssl_threshold_days ?? 21,
                'domain_threshold_days' => $monitor?->domain_threshold_days ?? 30,
                'heartbeat_grace_seconds' => $monitor?->heartbeat_grace_seconds ?? 300,
                'region' => $monitor?->region ?? 'North America',
                'contact_ids' => $contactIds,
            ],
            'contacts' => $user->notificationContacts()->orderByDesc('is_primary')->get()->map(fn ($contact) => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'enabled' => $contact->enabled,
            ])->all(),
            'options' => [
                'types' => [
                    ['value' => Monitor::TYPE_HTTP, 'label' => 'HTTP(S) Monitor'],
                    ['value' => Monitor::TYPE_PORT, 'label' => 'Port Monitor'],
                    ['value' => Monitor::TYPE_PING, 'label' => 'Ping Monitor'],
                ],
                'methods' => ['GET', 'POST'],
                'intervals' => collect([60, 300, 1800, 3600, 43200, 86400])
                    ->filter(fn (int $seconds) => $seconds >= $minimumInterval)
                    ->values()
                    ->all(),
                'regions' => ['North America', 'Europe', 'Asia Pacific'],
                'existingCapabilities' => $user->capabilities()->orderBy('name')->pluck('name')->all(),
                'keywordMatchTypes' => ['contains', 'exact', 'regex'],
                'guardrails' => [
                    'maxTimeoutSeconds' => $maxTimeoutSeconds,
                    'maxRetryLimit' => $maxRetryLimit,
                    'maxContactsPerMonitor' => $maxContactsPerMonitor,
                    'maxDowntimeWebhookUrls' => $maxDowntimeWebhookUrls,
                    'maxCustomHeaderCount' => $maxCustomHeaderCount,
                    'maxCustomHeaderValueLength' => $maxCustomHeaderValueLength,
                ],
            ],
            'membership' => [
                'planLabel' => $plan->label(),
                'planValue' => $plan->value,
                'priceLabel' => $plan->priceLabel(),
                'monitorLimit' => $user->monitorLimit(),
                'monitorLimitLabel' => (string) $user->monitorLimit(),
                'currentMonitorCount' => $user->monitors()->count(),
                'minimumIntervalLabel' => $this->intervalLabel($minimumInterval),
                'advancedFeaturesUnlocked' => $user->allowsAdvancedWorkspaceFeatures(),
                'supportsDowntimeWebhooks' => $user->supportsDowntimeWebhooks(),
                'manageUrl' => $actor && $actor->id !== $user->id ? null : route('membership.show'),
                'canCreate' => ! $user->hasReachedMonitorLimit() || $monitor !== null,
                'standardProfileLabel' => sprintf(
                    'Free workspaces use the standard check profile: North America, 5-minute cadence, %d-second timeout, and %d retries.',
                    $maxTimeoutSeconds,
                    $maxRetryLimit,
                ),
            ],
        ];
    }

    public function incidents(User $user, int $page = 1): array
    {
        $incidents = Incident::query()
            ->whereHas('monitor', fn ($query) => $query->where('user_id', $user->id))
            ->with('monitor.capabilities')
            ->latest('started_at')
            ->paginate(12, ['*'], 'page', $page)
            ->withQueryString();

        $summaryQuery = Incident::query()
            ->whereHas('monitor', fn ($query) => $query->where('user_id', $user->id));

        return [
            'summary' => [
                'open' => (clone $summaryQuery)->whereNull('resolved_at')->count(),
                'resolved' => (clone $summaryQuery)->whereNotNull('resolved_at')->count(),
                'last7Days' => (clone $summaryQuery)->where('started_at', '>=', now()->subDays(7))->count(),
            ],
            'incidents' => $this->paginateData($incidents, fn (Incident $incident) => [
                'id' => $incident->id,
                'monitor' => $incident->monitor->name,
                'monitorUrl' => route('monitors.show', $incident->monitor),
                'showUrl' => route('incidents.show', $incident),
                'startedAt' => $incident->started_at?->format('M j, Y H:i'),
                'endedAt' => $incident->resolved_at?->format('M j, Y H:i') ?? 'Open',
                'duration' => $incident->duration_seconds ? $this->durationLabel((int) $incident->duration_seconds) : 'Open',
                'reason' => $incident->reason,
                'status' => $this->incidentStatusLabel($incident),
                'typeLabel' => $this->incidentTypeLabel($incident),
                'severityLabel' => ucfirst($incident->severity),
                'capabilities' => $incident->monitor?->capabilities->pluck('name')->values()->all() ?? [],
            ]),
        ];
    }

    public function incident(User $user, Incident $incident): array
    {
        abort_unless($incident->monitor()->where('user_id', $user->id)->exists(), 404);

        $incident->load([
            'monitor.capabilities',
            'firstCheckResult',
            'lastGoodCheckResult',
            'latestCheckResult',
            'notificationLogs.notificationContact',
        ]);

        $windowEnd = $incident->resolved_at ? CarbonImmutable::parse($incident->resolved_at) : CarbonImmutable::now();
        $checkResults = CheckResult::query()
            ->where('monitor_id', $incident->monitor_id)
            ->whereBetween('checked_at', [
                CarbonImmutable::parse($incident->started_at)->subMinutes(5),
                $windowEnd->addMinute(),
            ])
            ->orderBy('checked_at')
            ->get();

        return [
            'incident' => [
                'id' => $incident->id,
                'monitorName' => $incident->monitor?->name,
                'monitorUrl' => $incident->monitor ? route('monitors.show', $incident->monitor) : '/monitors',
                'typeLabel' => $this->incidentTypeLabel($incident),
                'severityLabel' => ucfirst($incident->severity),
                'statusLabel' => $this->incidentStatusLabel($incident),
                'reason' => $incident->reason,
                'startedAt' => $incident->started_at?->format('M j, Y H:i'),
                'endedAt' => $incident->resolved_at?->format('M j, Y H:i') ?? 'Open',
                'duration' => $incident->duration_seconds !== null ? $this->durationLabel((int) $incident->duration_seconds) : 'Open',
                'operatorNotes' => $incident->operator_notes ?? '',
                'rootCauseSummary' => $incident->root_cause_summary ?? '',
                'capabilities' => $incident->monitor?->capabilities
                    ->map(fn (Capability $capability) => $this->capabilityItem(
                        $capability->relationLoaded('monitors')
                            ? $capability
                            : $capability->loadMissing([
                                'monitors' => fn ($query) => $query->with(['openIncidents', 'maintenanceWindows']),
                            ])
                    ))
                    ->values()
                    ->all() ?? [],
                'customerImpact' => $this->incidentCustomerImpact($incident),
                'firstFailedCheck' => $this->formatIncidentCheckResult($incident->firstCheckResult),
                'lastGoodCheck' => $this->formatIncidentCheckResult($incident->lastGoodCheckResult),
                'latestCheck' => $this->formatIncidentCheckResult($incident->latestCheckResult),
                'timeline' => $this->incidentTimeline($incident, $checkResults),
                'notificationHistory' => $incident->notificationLogs
                    ->sortByDesc('created_at')
                    ->values()
                    ->map(fn (NotificationLog $log) => [
                        'type' => ucfirst(str_replace('_', ' ', $log->type)),
                        'status' => ucfirst($log->status),
                        'subject' => $log->subject,
                        'contact' => $log->notificationContact?->email
                            ?? data_get($log->payload, 'email')
                            ?? data_get($log->payload, 'url', 'Unknown'),
                        'sentAt' => $log->sent_at?->format('M j, Y H:i') ?? $log->created_at->format('M j, Y H:i'),
                    ])->all(),
            ],
        ];
    }

    public function statusPages(User $user, int $page = 1, string $monitorQuery = '', int $monitorPage = 1): array
    {
        $formDefaultMonitorIds = $user->monitors()
            ->orderBy('created_at')
            ->limit(3)
            ->pluck('id')
            ->all();

        $statusPages = $user->statusPages()
            ->with([
                'monitors' => fn ($query) => $query->with([
                    'maintenanceWindows',
                    'openIncidents',
                    'capabilities',
                ]),
                'incidents' => fn ($query) => $query
                    ->with([
                        'monitors.capabilities',
                        'updates',
                    ])
                    ->latest('started_at'),
            ])
            ->latest('updated_at')
            ->paginate(6, ['*'], 'page', max(1, $page))
            ->withQueryString();
        $visibleStatusPages = collect($statusPages->items());
        $monitorOptions = $this->monitorOptions(
            $user,
            $visibleStatusPages
                ->flatMap(fn (StatusPage $statusPage) => $statusPage->monitors->pluck('id'))
                ->merge($formDefaultMonitorIds)
                ->unique()
                ->values()
                ->all(),
            $monitorQuery,
            $monitorPage,
        );

        return [
            'summary' => [
                'published' => $user->statusPages()->where('published', true)->count(),
                'drafts' => $user->statusPages()->where('published', false)->count(),
                'monitors' => StatusPage::query()
                    ->join('status_page_monitor', 'status_pages.id', '=', 'status_page_monitor.status_page_id')
                    ->where('status_pages.user_id', $user->id)
                    ->count(),
                'activeIncidents' => StatusPageIncident::query()
                    ->where('user_id', $user->id)
                    ->whereNull('resolved_at')
                    ->count(),
            ],
            'pages' => $this->paginateData($statusPages, fn (StatusPage $statusPage) => $this->statusPageItem($statusPage)),
            'monitorOptions' => $monitorOptions['options'],
            'monitorOptionQuery' => $monitorOptions['query'],
            'monitorOptionResults' => $monitorOptions['results'],
            'formDefaults' => [
                'name' => '',
                'slug' => '',
                'headline' => 'System status',
                'description' => 'Live availability and incident information for monitored services.',
                'published' => true,
                'monitor_ids' => $formDefaultMonitorIds,
            ],
        ];
    }

    public function maintenance(User $user, ?int $focusMonitorId = null, int $historyPage = 1, string $monitorQuery = '', int $monitorPage = 1): array
    {
        $now = $this->currentTime();
        $activeQuery = $user->maintenanceWindows()
            ->where('status', '!=', MaintenanceWindow::STATUS_CANCELLED)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now);
        $upcomingQuery = $user->maintenanceWindows()
            ->where('status', '!=', MaintenanceWindow::STATUS_CANCELLED)
            ->where('starts_at', '>', $now);
        $historyQuery = $user->maintenanceWindows()
            ->where(function ($query) use ($now): void {
                $query->where('status', MaintenanceWindow::STATUS_CANCELLED)
                    ->orWhere('ends_at', '<', $now);
            });

        $active = (clone $activeQuery)
            ->with('monitors')
            ->orderBy('starts_at')
            ->limit(6)
            ->get();
        $upcoming = (clone $upcomingQuery)
            ->with('monitors')
            ->orderBy('starts_at')
            ->limit(6)
            ->get();
        $history = (clone $historyQuery)
            ->with('monitors')
            ->orderByDesc('starts_at')
            ->paginate(10, ['*'], 'history_page', max(1, $historyPage))
            ->withQueryString();
        $focusMonitor = $focusMonitorId
            ? $user->monitors()->whereKey($focusMonitorId)->first()
            : null;
        $monitorOptions = $this->monitorOptions(
            $user,
            $active
                ->flatMap(fn (MaintenanceWindow $window) => $window->monitors->pluck('id'))
                ->merge($upcoming->flatMap(fn (MaintenanceWindow $window) => $window->monitors->pluck('id')))
                ->merge(collect($history->items())->flatMap(fn (MaintenanceWindow $window) => $window->monitors->pluck('id')))
                ->merge($focusMonitor ? [$focusMonitor->id] : [])
                ->unique()
                ->values()
                ->all(),
            $monitorQuery,
            $monitorPage,
        );

        return [
            'summary' => [
                'active' => (clone $activeQuery)->count(),
                'upcoming' => (clone $upcomingQuery)->count(),
                'history' => (clone $historyQuery)->count(),
            ],
            'active' => $active->map(fn (MaintenanceWindow $window) => $this->maintenanceWindowItem($window))->all(),
            'upcoming' => $upcoming->map(fn (MaintenanceWindow $window) => $this->maintenanceWindowItem($window))->all(),
            'history' => $this->paginateData($history, fn (MaintenanceWindow $window) => $this->maintenanceWindowItem($window)),
            'monitorOptions' => $monitorOptions['options'],
            'monitorOptionQuery' => $monitorOptions['query'],
            'monitorOptionResults' => $monitorOptions['results'],
            'focusMonitor' => $focusMonitor ? [
                'id' => $focusMonitor->id,
                'name' => $focusMonitor->name,
            ] : null,
            'formDefaults' => [
                'title' => '',
                'message' => $focusMonitor ? sprintf('Planned maintenance for %s.', $focusMonitor->name) : 'Routine maintenance in progress.',
                'starts_at' => now()->addHours(2)->format('Y-m-d\TH:i'),
                'ends_at' => now()->addHours(3)->format('Y-m-d\TH:i'),
                'notify_contacts' => false,
                'monitor_ids' => $focusMonitor ? [$focusMonitor->id] : [],
            ],
        ];
    }

    public function integrations(User $user, int $logsPage = 1): array
    {
        $contacts = $user->notificationContacts()
            ->withCount('notificationLogs')
            ->with('monitors')
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
        $apiTokens = $user->apiTokens()
            ->latest()
            ->get();

        $logQuery = NotificationLog::query()
            ->whereHas('monitor', fn ($query) => $query->where('user_id', $user->id))
            ->with(['monitor', 'notificationContact']);

        $recentLogs = (clone $logQuery)
            ->latest()
            ->paginate(10, ['*'], 'logs_page', $logsPage)
            ->withQueryString();
        $staleBefore = CarbonImmutable::now()
            ->subSeconds(max(60, (int) config('realuptime.dispatch.claim_ttl_seconds', 600)));

        return [
            'summary' => [
                'contacts' => $contacts->count(),
                'enabled' => $contacts->where('enabled', true)->count(),
                'apiTokens' => $apiTokens->count(),
                'emailsSent' => (clone $logQuery)->where('status', 'sent')->count(),
                'emailsPending' => (clone $logQuery)->where('status', 'pending')->count(),
                'emailsFailed' => (clone $logQuery)->where('status', 'failed')->count(),
                'mailer' => config('mail.default'),
            ],
            'runtime' => [
                'appUrl' => rtrim((string) config('app.url'), '/'),
                'apiBaseUrl' => url('/api/v1'),
                'mailer' => config('mail.default'),
                'queueConnection' => config('queue.default'),
                'monitorQueue' => implode(', ', MonitorQueueResolver::monitorCheckQueues()),
                'notificationQueue' => config('realuptime.queues.notifications'),
                'dispatchBatchSize' => (int) config('realuptime.dispatch.batch_size', 250),
                'dispatchMaxBatches' => (int) config('realuptime.dispatch.max_batches', 12),
                'claimTtlSeconds' => (int) config('realuptime.dispatch.claim_ttl_seconds', 600),
                'dueMonitors' => $user->monitors()
                    ->where('status', '!=', Monitor::STATUS_PAUSED)
                    ->where(function ($query): void {
                        $query->whereNull('next_check_at')
                            ->orWhere('next_check_at', '<=', now());
                    })
                    ->count(),
                'claimedMonitors' => $user->monitors()
                    ->whereNotNull('check_claimed_at')
                    ->count(),
                'staleClaims' => $user->monitors()
                    ->whereNotNull('check_claimed_at')
                    ->where('check_claimed_at', '<=', $staleBefore)
                    ->count(),
            ],
            'contacts' => $contacts->map(fn ($contact) => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'enabled' => $contact->enabled,
                'isPrimary' => $contact->is_primary,
                'logsCount' => $contact->notification_logs_count,
                'monitorNames' => $contact->monitors->pluck('name')->take(3)->values()->all(),
            ])->all(),
            'recentLogs' => $this->paginateData($recentLogs, fn (NotificationLog $log) => [
                'monitor' => $log->monitor?->name,
                'contact' => $log->notificationContact?->email,
                'type' => ucfirst(str_replace('_', ' ', $log->type)),
                'status' => ucfirst($log->status),
                'subject' => $log->subject,
                'failureMessage' => $log->failure_message,
                'sentAt' => $log->sent_at?->format('M j, Y H:i') ?? $log->created_at->format('M j, Y H:i'),
            ]),
            'apiTokens' => $apiTokens->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'createdAt' => $token->created_at?->format('M j, Y H:i'),
                'lastUsedAt' => $token->last_used_at?->format('M j, Y H:i'),
                'lastUsedLabel' => $token->last_used_at ? $this->timeAgo($token->last_used_at) : 'Never used',
            ])->all(),
            'formDefaults' => [
                'name' => '',
                'email' => '',
                'enabled' => true,
                'is_primary' => $contacts->isEmpty(),
            ],
            'tokenFormDefaults' => [
                'name' => 'Primary automation',
            ],
        ];
    }

    /**
     * @template TModel of mixed
     * @template TItem of array<string, mixed>
     *
     * @param  LengthAwarePaginator<TModel>  $paginator
     * @param  callable(TModel):TItem  $mapper
     * @return array{
     *     data: array<int, TItem>,
     *     currentPage: int,
     *     lastPage: int,
     *     perPage: int,
     *     total: int,
     *     from: int|null,
     *     to: int|null,
     *     previousPageUrl: string|null,
     *     nextPageUrl: string|null
     * }
     */
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

    public function team(User $actor, User $workspace): array
    {
        $monitors = $workspace->monitors()->count();
        $contacts = $workspace->notificationContacts()->count();
        $statusPages = $workspace->statusPages()->count();
        $memberships = WorkspaceMembership::query()
            ->with(['member', 'inviter'])
            ->where('owner_user_id', $workspace->id)
            ->orderByDesc('accepted_at')
            ->orderByDesc('invited_at')
            ->get();

        return [
            'teamWorkspace' => [
                'name' => $workspace->name,
                'email' => $workspace->email,
                'isOwner' => $workspace->id === $actor->id,
                'isPersonal' => $workspace->id === $actor->id,
            ],
            'owner' => [
                'name' => $workspace->name,
                'email' => $workspace->email,
                'emailVerified' => $workspace->email_verified_at !== null,
                'twoFactorEnabled' => $workspace->two_factor_confirmed_at !== null,
                'memberSince' => $workspace->created_at->format('M j, Y'),
            ],
            'summary' => [
                'monitors' => $monitors,
                'contacts' => $contacts,
                'statusPages' => $statusPages,
                'members' => 1 + $memberships->whereNotNull('accepted_at')->whereNull('revoked_at')->count(),
            ],
            'links' => [
                ['label' => 'Profile settings', 'href' => '/settings/profile'],
                ['label' => 'Password & session security', 'href' => '/settings/password'],
                ['label' => 'Two-factor authentication', 'href' => '/settings/two-factor'],
            ],
            'canInvite' => $actor->id === $workspace->id,
            'formDefaults' => [
                'email' => '',
            ],
            'acceptedMembers' => collect([
                [
                    'id' => 'owner-'.$workspace->id,
                    'name' => $workspace->name,
                    'email' => $workspace->email,
                    'acceptedAt' => $workspace->created_at->format('M j, Y H:i'),
                    'statusLabel' => 'Owner',
                    'isCurrentUser' => $actor->id === $workspace->id,
                    'removable' => false,
                    'invitationId' => null,
                ],
            ])->merge(
                $memberships
                    ->filter(fn (WorkspaceMembership $membership) => $membership->accepted_at !== null && $membership->revoked_at === null)
                    ->map(fn (WorkspaceMembership $membership) => [
                        'id' => $membership->id,
                        'name' => $membership->member?->name ?? $membership->invited_email,
                        'email' => $membership->invited_email,
                        'acceptedAt' => $membership->accepted_at?->format('M j, Y H:i'),
                        'statusLabel' => 'Member',
                        'isCurrentUser' => $membership->member_user_id === $actor->id,
                        'removable' => $actor->id === $workspace->id || $membership->member_user_id === $actor->id,
                        'invitationId' => $membership->id,
                    ])
            )->values()->all(),
            'pendingInvitations' => $memberships
                ->filter(fn (WorkspaceMembership $membership) => $membership->accepted_at === null && $membership->revoked_at === null)
                ->map(fn (WorkspaceMembership $membership) => [
                    'id' => $membership->id,
                    'email' => $membership->invited_email,
                    'invitedAt' => $membership->invited_at?->format('M j, Y H:i') ?? $membership->created_at->format('M j, Y H:i'),
                    'removable' => $actor->id === $workspace->id,
                ])->values()->all(),
        ];
    }

    public function publicStatusPage(StatusPage $statusPage): array
    {
        $statusPage->load([
            'incidents' => fn ($query) => $query->with(['monitors.capabilities', 'updates'])->latest('started_at'),
            'monitors' => fn ($query) => $query->with([
                'maintenanceWindows' => fn ($maintenance) => $maintenance->orderBy('starts_at'),
                'openIncidents',
                'capabilities',
            ]),
        ]);

        $monitors = $statusPage->monitors;
        $now = $this->currentTime();

        $this->preloadDowntimeIncidents($monitors, $now->subDay(), $now);
        $this->preloadCheckResults($monitors, $now->subDay(), $now);

        $monitorIds = $monitors->pluck('id');
        $maintenanceWindows = MaintenanceWindow::query()
            ->where('user_id', $statusPage->user_id)
            ->whereHas('monitors', fn ($query) => $query->whereIn('monitors.id', $monitorIds))
            ->with('monitors')
            ->orderBy('starts_at')
            ->get();
        $monitorIncidents = Incident::query()
            ->whereIn('monitor_id', $monitorIds)
            ->with('monitor.capabilities')
            ->latest('started_at')
            ->limit(8)
            ->get();
        $statusPageIncidents = $statusPage->incidents;
        $recentStatusUpdates = $statusPageIncidents
            ->flatMap(fn (StatusPageIncident $incident) => $incident->updates->map(fn ($update) => [
                'incidentTitle' => $incident->title,
                'status' => $this->statusPageIncidentStatusLabel($update->status),
                'impact' => ucfirst($incident->impact),
                'message' => $update->message,
                'createdAt' => $update->created_at,
            ]))
            ->sortByDesc('createdAt')
            ->values()
            ->take(12)
            ->map(fn (array $update) => [
                ...$update,
                'createdAt' => CarbonImmutable::parse($update['createdAt'])->format('M j, Y H:i'),
            ])
            ->all();
        $capabilities = $monitors
            ->flatMap(fn (Monitor $monitor) => $monitor->capabilities)
            ->unique('id')
            ->values();

        return [
            'statusPage' => [
                'name' => $statusPage->name,
                'headline' => $statusPage->headline ?: $statusPage->name,
                'description' => $statusPage->description,
                'slug' => $statusPage->slug,
                'overallStatus' => $this->publicOverallStatusLabel($monitors, $maintenanceWindows, $statusPageIncidents),
                'overallTone' => $this->publicOverallStatusTone($monitors, $maintenanceWindows, $statusPageIncidents),
                'updatedLabel' => 'Updated '.$this->timeAgo($this->latestStatusPageActivityAt($statusPage, $monitors, $maintenanceWindows, $monitorIncidents)),
                'monitors' => $monitors->map(function (Monitor $monitor) use ($statusPageIncidents): array {
                    $publicStatus = $this->publicMonitorStatus($monitor, $statusPageIncidents);

                    return [
                        'name' => $monitor->name,
                        'type' => $this->typeLabel($monitor),
                        'status' => $publicStatus['label'],
                        'statusTone' => $publicStatus['tone'],
                        'statusDetail' => $publicStatus['detail'],
                        'uptimeLabel' => $this->windowStats($monitor, 1)['uptimeLabel'],
                        'lastCheckedLabel' => $monitor->last_checked_at ? $this->timeAgo($monitor->last_checked_at) : 'Never checked',
                        'responseTimeLabel' => $monitor->last_response_time_ms ? Number::format($monitor->last_response_time_ms).' ms' : 'n/a',
                        'activeMaintenance' => $publicStatus['tone'] === 'maintenance',
                        'capabilities' => $monitor->capabilities->pluck('name')->values()->all(),
                    ];
                })->all(),
                'capabilities' => $capabilities
                    ->map(fn (Capability $capability) => $this->capabilityItemFromMonitors(
                        $capability,
                        $monitors->filter(fn (Monitor $monitor) => $monitor->capabilities->contains('id', $capability->id))->values(),
                    ))
                    ->all(),
                'incidents' => $statusPageIncidents->map(fn (StatusPageIncident $incident) => [
                    'title' => $incident->title,
                    'status' => $this->statusPageIncidentStatusLabel($incident->status),
                    'impact' => ucfirst($incident->impact),
                    'message' => $incident->message,
                    'startedAt' => $incident->started_at?->format('M j, Y H:i'),
                    'endedAt' => $incident->resolved_at?->format('M j, Y H:i') ?? 'Ongoing',
                    'monitors' => $incident->monitors->pluck('name')->values()->all(),
                    'capabilities' => $incident->monitors
                        ->flatMap(fn (Monitor $monitor) => $monitor->capabilities->pluck('name'))
                        ->unique()
                        ->values()
                        ->all(),
                    'updates' => $incident->updates->map(fn ($update) => [
                        'status' => $this->statusPageIncidentStatusLabel($update->status),
                        'message' => $update->message,
                        'createdAt' => $update->created_at?->format('M j, Y H:i'),
                    ])->all(),
                ])->all(),
                'monitorIncidents' => $monitorIncidents->map(fn (Incident $incident) => [
                    'monitor' => $incident->monitor?->name,
                    'status' => $incident->resolved_at ? 'Resolved' : 'Investigating',
                    'reason' => $incident->reason,
                    'startedAt' => $incident->started_at?->format('M j, Y H:i'),
                    'endedAt' => $incident->resolved_at?->format('M j, Y H:i') ?? 'Ongoing',
                    'capabilities' => $incident->monitor?->capabilities->pluck('name')->values()->all() ?? [],
                ])->all(),
                'recentUpdates' => $recentStatusUpdates,
                'maintenance' => $maintenanceWindows
                    ->filter(fn (MaintenanceWindow $window) => $window->ends_at?->gte($now->subDay()))
                    ->values()
                    ->map(fn (MaintenanceWindow $window) => $this->maintenanceWindowItem($window))
                    ->all(),
            ],
        ];
    }

    protected function capabilityOverview(User $user): array
    {
        return $user->capabilities()
            ->with([
                'monitors' => fn (Builder $query) => $this->applyCapabilityMonitorSummaryQuery($query),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Capability $capability) => $this->capabilityItem($capability))
            ->all();
    }

    protected function capabilityItem(Capability $capability): array
    {
        $capability->loadMissing([
            'monitors' => fn (Builder $query) => $this->applyCapabilityMonitorSummaryQuery($query),
        ]);

        return $this->capabilityItemFromMonitors($capability, $capability->monitors->unique('id')->values());
    }

    protected function applyCapabilityMonitorSummaryQuery(Builder $query): void
    {
        $now = $this->currentTime();

        $query
            ->withCount([
                'openIncidents',
                'openIncidents as open_downtime_incidents_count' => fn (Builder $incidentQuery) => $incidentQuery->where('type', Incident::TYPE_DOWNTIME),
                'openIncidents as open_degraded_performance_incidents_count' => fn (Builder $incidentQuery) => $incidentQuery->where('type', Incident::TYPE_DEGRADED_PERFORMANCE),
                'openIncidents as open_ssl_expiry_incidents_count' => fn (Builder $incidentQuery) => $incidentQuery->where('type', Incident::TYPE_SSL_EXPIRY),
                'openIncidents as open_domain_expiry_incidents_count' => fn (Builder $incidentQuery) => $incidentQuery->where('type', Incident::TYPE_DOMAIN_EXPIRY),
                'maintenanceWindows as active_maintenance_windows_count' => fn (Builder $maintenanceQuery) => $maintenanceQuery
                    ->where('maintenance_windows.status', '!=', MaintenanceWindow::STATUS_CANCELLED)
                    ->where('maintenance_windows.starts_at', '<=', $now)
                    ->where('maintenance_windows.ends_at', '>=', $now),
            ])
            ->orderBy('name');
    }

    protected function capabilityItemFromMonitors(Capability $capability, Collection $monitors): array
    {
        $status = $this->capabilityStatus($capability->name, $monitors);

        return [
            'id' => $capability->id,
            'name' => $capability->name,
            'slug' => $capability->slug,
            'status' => $status['label'],
            'tone' => $status['tone'],
            'summary' => $status['summary'],
            'customerImpact' => $status['customerImpact'],
            'linkedChecks' => $monitors->count(),
            'affectedChecks' => $status['affectedChecks'],
            'regions' => $this->capabilityRegionsLabel($monitors),
            'openIncidents' => $monitors->sum(fn (Monitor $monitor) => $this->monitorOpenIncidentCount($monitor)),
            'monitorNames' => $monitors->pluck('name')->take(4)->values()->all(),
        ];
    }

    protected function capabilityStatus(string $capabilityName, Collection $monitors): array
    {
        $total = $monitors->count();
        $down = $monitors->filter(fn (Monitor $monitor) => $monitor->status === Monitor::STATUS_DOWN
            || $this->monitorHasOpenIncidentType($monitor, [Incident::TYPE_DOWNTIME]))->count();
        $warnings = $monitors->filter(fn (Monitor $monitor) => $monitor->status === Monitor::STATUS_PAUSED
            || $this->monitorHasOpenIncidentType($monitor, [
                Incident::TYPE_DEGRADED_PERFORMANCE,
                Incident::TYPE_SSL_EXPIRY,
                Incident::TYPE_DOMAIN_EXPIRY,
            ]))->count();
        $maintenance = $monitors->filter(fn (Monitor $monitor) => $this->monitorHasActiveMaintenanceWindow($monitor))->count();
        $regions = $this->capabilityRegionsLabel($monitors);

        if ($down > 0) {
            return [
                'label' => $down === $total ? 'Unavailable' : 'Partial outage',
                'tone' => 'down',
                'summary' => sprintf('%d of %d linked checks are currently down.', $down, $total),
                'customerImpact' => sprintf('%s is unavailable across %s.', $capabilityName, $regions),
                'affectedChecks' => $down,
            ];
        }

        if ($warnings > 0) {
            return [
                'label' => 'Degraded',
                'tone' => 'warning',
                'summary' => sprintf('%d linked checks are reporting warnings or degraded performance.', $warnings),
                'customerImpact' => sprintf('%s is degraded across %s.', $capabilityName, $regions),
                'affectedChecks' => $warnings,
            ];
        }

        if ($maintenance > 0) {
            return [
                'label' => 'Maintenance',
                'tone' => 'maintenance',
                'summary' => sprintf('%d linked checks are inside an active maintenance window.', $maintenance),
                'customerImpact' => sprintf('%s is currently under planned maintenance in %s.', $capabilityName, $regions),
                'affectedChecks' => $maintenance,
            ];
        }

        return [
            'label' => 'Healthy',
            'tone' => 'up',
            'summary' => sprintf('%d linked checks are operating normally.', $total),
            'customerImpact' => sprintf('%s is healthy across %s.', $capabilityName, $regions),
            'affectedChecks' => 0,
        ];
    }

    protected function capabilityRegionsLabel(Collection $monitors): string
    {
        $regions = $monitors->pluck('region')
            ->filter()
            ->unique()
            ->values();

        if ($regions->isEmpty()) {
            return 'your configured regions';
        }

        if ($regions->count() === 1) {
            return (string) $regions->first();
        }

        if ($regions->count() === 2) {
            return sprintf('%s and %s', $regions[0], $regions[1]);
        }

        return sprintf('%s, %s, and %d more regions', $regions[0], $regions[1], $regions->count() - 2);
    }

    protected function incidentCustomerImpact(Incident $incident): string
    {
        $capabilities = $incident->monitor?->capabilities
            ? $incident->monitor->capabilities->pluck('name')->values()
            : collect();

        if ($capabilities->isEmpty()) {
            return $incident->monitor?->name
                ? sprintf('This incident is currently isolated to the %s check.', $incident->monitor->name)
                : 'This incident is currently isolated to a single check.';
        }

        if ($capabilities->count() === 1) {
            return sprintf('%s is customer-facing and currently impacted by this incident.', $capabilities->first());
        }

        return sprintf(
            'Affected capabilities: %s.',
            $capabilities->take(3)->implode(', ').($capabilities->count() > 3 ? sprintf(' and %d more', $capabilities->count() - 3) : '')
        );
    }

    protected function monitorListItem(Monitor $monitor): array
    {
        $stats = $this->windowStats($monitor, 1);
        $effectiveIntervalSeconds = $this->effectiveIntervalSeconds($monitor);

        return [
            'id' => $monitor->id,
            'name' => $monitor->name,
            'status' => $monitor->status,
            'type' => strtoupper($monitor->type),
            'typeLabel' => $this->typeLabel($monitor),
            'statusSummary' => ucfirst($monitor->status).' '.$this->durationSinceStatusChange($monitor),
            'intervalLabel' => $this->intervalLabel($effectiveIntervalSeconds),
            'lastCheckedLabel' => $monitor->last_checked_at ? $this->timeAgo($monitor->last_checked_at) : 'Never checked',
            'responseTimeLabel' => $monitor->last_response_time_ms ? Number::format($monitor->last_response_time_ms).' ms' : 'n/a',
            'uptimePercentLabel' => $stats['uptimeLabel'],
            'bars' => $this->uptimeBars($monitor, 24, 24),
            'target' => $monitor->target,
            'showUrl' => route('monitors.show', $monitor),
            'editUrl' => route('monitors.edit', $monitor),
        ];
    }

    protected function aggregateMonitorWindow(Collection $monitors, int $days): array
    {
        if ($monitors->isEmpty()) {
            return [
                'uptimeLabel' => '0%',
                'mtbfLabel' => 'N/A',
                'withoutIncidentsLabel' => '0d',
                'incidentsCount' => 0,
            ];
        }

        $stats = $monitors->map(fn (Monitor $monitor) => $this->windowStats($monitor, $days));
        $monitoredSeconds = (int) $stats->sum('monitoredSeconds');
        $downtimeSeconds = (int) $stats->sum('downtimeSeconds');
        $uptimeValue = $monitoredSeconds > 0
            ? round((max(0, $monitoredSeconds - $downtimeSeconds) / $monitoredSeconds) * 100, 2)
            : 0.0;

        return [
            'uptimeLabel' => $this->formatUptimeLabel($uptimeValue),
            'mtbfLabel' => $monitors->contains(fn (Monitor $monitor) => $this->mtbfLabel($monitor, $days) !== 'N/A')
                ? $monitors->map(fn (Monitor $monitor) => $this->mtbfLabel($monitor, $days))->first(fn (string $value) => $value !== 'N/A')
                : 'N/A',
            'withoutIncidentsLabel' => $stats->sum('incidentsCount') === 0 ? $days.'d' : '0d',
            'incidentsCount' => $stats->sum('incidentsCount'),
        ];
    }

    protected function windowStats(Monitor $monitor, int $days): array
    {
        return $this->windowStatsSince($monitor, $this->currentTime()->subDays($days), $days.'d');
    }

    protected function windowStatsByHours(Monitor $monitor, int $hours): array
    {
        return $this->windowStatsSince($monitor, $this->currentTime()->subHours($hours), $hours.'h');
    }

    protected function windowStatsSince(Monitor $monitor, CarbonImmutable $from, string $withoutIncidentsLabel): array
    {
        $to = $this->currentTime();
        $window = $this->downtimeWindowData($monitor, $from, $to);
        $monitoredSeconds = $window['monitoredSeconds'];
        $downtimeSeconds = min($window['downtimeSeconds'], $monitoredSeconds);
        $uptimeValue = $monitoredSeconds > 0
            ? round((max(0, $monitoredSeconds - $downtimeSeconds) / $monitoredSeconds) * 100, 2)
            : 0.0;
        $incidents = $window['incidents'];

        if ($incidents->isEmpty()) {
            $summary = $this->windowCheckResultSummary($monitor, $from, $to);
            $downResults = $summary['downResults'];

            if ($downResults > 0) {
                $effectiveIntervalSeconds = $this->effectiveIntervalSeconds($monitor);
                $totalResults = max(1, $summary['totalResults']);
                $monitoredSeconds = max($monitoredSeconds, $totalResults * $effectiveIntervalSeconds);
                $uptimeValue = round((($totalResults - $downResults) / $totalResults) * 100, 2);
                $downtimeSeconds = min($downResults * $effectiveIntervalSeconds, $monitoredSeconds);
            }
        }

        return [
            'uptimeValue' => $uptimeValue,
            'uptimeLabel' => $this->formatUptimeLabel($uptimeValue),
            'incidentsCount' => $incidents->count(),
            'downtimeLabel' => $this->downtimeLabel($downtimeSeconds),
            'withoutIncidentsLabel' => $incidents->isEmpty() ? $withoutIncidentsLabel : '0'.$this->windowLabelSuffix($withoutIncidentsLabel),
            'monitoredSeconds' => $monitoredSeconds,
            'downtimeSeconds' => $downtimeSeconds,
        ];
    }

    protected function uptimeBars(Monitor $monitor, int $hours, int $segments): array
    {
        $to = $this->currentTime();
        $from = $to->subHours($hours);
        $window = $this->downtimeWindowData($monitor, $from, $to);
        $fallbackResults = $window['incidents']->isEmpty()
            ? $this->windowCheckResults($monitor, $from, $to)
            : collect();

        if (! $window['monitoringStart']) {
            return array_fill(0, $segments, 'unknown');
        }

        $segmentLength = max(1, (int) floor(($hours * 3600) / $segments));

        return collect(range(0, $segments - 1))->map(function (int $segment) use ($from, $to, $segmentLength, $segments, $window, $fallbackResults) {
            $start = $from->addSeconds($segment * $segmentLength);
            $end = $segment === $segments - 1
                ? $to
                : $start->addSeconds($segmentLength);

            if ($end->lessThanOrEqualTo($window['monitoringStart'])) {
                return 'unknown';
            }

            if ($window['incidents']->isEmpty() && $fallbackResults->where('status', 'down')->isNotEmpty()) {
                $slice = $fallbackResults->filter(fn (CheckResult $result) => $result->checked_at?->betweenIncluded($start, $end));

                if ($slice->isEmpty()) {
                    return 'unknown';
                }

                return $slice->contains(fn (CheckResult $result) => $result->status === 'down') ? 'down' : 'up';
            }

            return $this->incidentOverlapSeconds($window['incidents'], $start, $end) > 0 ? 'down' : 'up';
        })->all();
    }

    /**
     * @return array{
     *     incidents: Collection<int, Incident>,
     *     monitoringStart: CarbonImmutable|null,
     *     monitoredSeconds: int,
     *     downtimeSeconds: int
     * }
     */
    protected function downtimeWindowData(Monitor $monitor, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $cacheKey = implode(':', [$monitor->id, $from->timestamp, $to->timestamp]);

        if (array_key_exists($cacheKey, $this->downtimeWindowCache)) {
            return $this->downtimeWindowCache[$cacheKey];
        }

        $incidents = $this->preloadedDowntimeIncidentsForWindow($monitor, $from, $to)
            ?? Incident::query()
                ->where('monitor_id', $monitor->id)
                ->where('type', Incident::TYPE_DOWNTIME)
                ->where('started_at', '<', $to)
                ->where(function ($query) use ($from): void {
                    $query->whereNull('resolved_at')
                        ->orWhere('resolved_at', '>', $from);
                })
                ->orderBy('started_at')
                ->get();

        if (! $monitor->last_checked_at && $incidents->isEmpty()) {
            return $this->downtimeWindowCache[$cacheKey] = [
                'incidents' => $incidents,
                'monitoringStart' => null,
                'monitoredSeconds' => 0,
                'downtimeSeconds' => 0,
            ];
        }

        $createdAt = CarbonImmutable::parse($monitor->created_at);
        $firstCheckAt = $this->windowCheckResultSummary($monitor, $from, $to)['firstCheckedAt'];
        $firstIncidentAt = $incidents->isNotEmpty()
            ? CarbonImmutable::parse($incidents->min('started_at'))
            : null;
        $evidenceStart = collect([$createdAt, $firstCheckAt, $firstIncidentAt])
            ->filter()
            ->sortBy(fn (CarbonImmutable $time) => $time->timestamp)
            ->first() ?? $createdAt;
        $monitoringStart = $evidenceStart->greaterThan($from) ? $evidenceStart : $from;

        if ($monitoringStart->greaterThanOrEqualTo($to)) {
            return $this->downtimeWindowCache[$cacheKey] = [
                'incidents' => $incidents,
                'monitoringStart' => null,
                'monitoredSeconds' => 0,
                'downtimeSeconds' => 0,
            ];
        }

        $monitoredSeconds = $monitoringStart->diffInSeconds($to);
        $downtimeSeconds = $this->incidentOverlapSeconds($incidents, $monitoringStart, $to);

        return $this->downtimeWindowCache[$cacheKey] = [
            'incidents' => $incidents,
            'monitoringStart' => $monitoringStart,
            'monitoredSeconds' => $monitoredSeconds,
            'downtimeSeconds' => min($downtimeSeconds, $monitoredSeconds),
        ];
    }

    /**
     * @param  Collection<int, Incident>  $incidents
     */
    protected function incidentOverlapSeconds(Collection $incidents, CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) $incidents->sum(fn (Incident $incident) => $this->incidentWindowOverlapSeconds($incident, $from, $to));
    }

    /**
     * @return Collection<int, CheckResult>
     */
    protected function windowCheckResults(Monitor $monitor, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $cacheKey = implode(':', ['results', $monitor->id, $from->timestamp, $to->timestamp]);

        if (array_key_exists($cacheKey, $this->windowCheckResultCache)) {
            return $this->windowCheckResultCache[$cacheKey];
        }

        $preloadedResults = $this->preloadedCheckResultsForWindow($monitor, $from, $to);

        if ($preloadedResults !== null) {
            return $this->windowCheckResultCache[$cacheKey] = $preloadedResults;
        }

        return $this->windowCheckResultCache[$cacheKey] = CheckResult::query()
            ->where('monitor_id', $monitor->id)
            ->where('checked_at', '>=', $from)
            ->where('checked_at', '<', $to)
            ->orderBy('checked_at')
            ->get(['id', 'monitor_id', 'status', 'checked_at', 'response_time_ms', 'meta']);
    }

    /**
     * @return array{firstCheckedAt: CarbonImmutable|null, totalResults: int, downResults: int}
     */
    protected function windowCheckResultSummary(Monitor $monitor, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $cacheKey = implode(':', ['summary', $monitor->id, $from->timestamp, $to->timestamp]);

        if (array_key_exists($cacheKey, $this->windowCheckResultSummaryCache)) {
            return $this->windowCheckResultSummaryCache[$cacheKey];
        }

        $preloadedResults = $this->preloadedCheckResultsForWindow($monitor, $from, $to);

        if ($preloadedResults !== null) {
            return $this->windowCheckResultSummaryCache[$cacheKey] = [
                'firstCheckedAt' => $preloadedResults->first()?->checked_at
                    ? CarbonImmutable::parse($preloadedResults->first()->checked_at)
                    : null,
                'totalResults' => $preloadedResults->count(),
                'downResults' => $preloadedResults->where('status', 'down')->count(),
            ];
        }

        $summary = CheckResult::query()
            ->where('monitor_id', $monitor->id)
            ->where('checked_at', '>=', $from)
            ->where('checked_at', '<', $to)
            ->selectRaw(
                "MIN(checked_at) as first_checked_at, COUNT(*) as total_results, SUM(CASE WHEN status = 'down' THEN 1 ELSE 0 END) as down_results"
            )
            ->first();

        return $this->windowCheckResultSummaryCache[$cacheKey] = [
            'firstCheckedAt' => $summary?->first_checked_at
                ? CarbonImmutable::parse($summary->first_checked_at)
                : null,
            'totalResults' => (int) ($summary?->total_results ?? 0),
            'downResults' => (int) ($summary?->down_results ?? 0),
        ];
    }

    protected function incidentWindowOverlapSeconds(Incident $incident, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $startsAt = CarbonImmutable::parse($incident->started_at);
        $endsAt = $incident->resolved_at
            ? CarbonImmutable::parse($incident->resolved_at)
            : $to;

        if ($endsAt->lessThanOrEqualTo($from) || $startsAt->greaterThanOrEqualTo($to)) {
            return 0;
        }

        $overlapStart = $startsAt->greaterThan($from) ? $startsAt : $from;
        $overlapEnd = $endsAt->lessThan($to) ? $endsAt : $to;

        return max(0, $overlapStart->diffInSeconds($overlapEnd));
    }

    protected function formatUptimeLabel(float $uptimeValue): string
    {
        return rtrim(rtrim(number_format($uptimeValue, 2), '0'), '.').'%';
    }

    protected function downtimeLabel(int $seconds): string
    {
        if ($seconds <= 0) {
            return '0m down';
        }

        if ($seconds < 3600) {
            return (int) round($seconds / 60).'m down';
        }

        return $this->durationLabel($seconds).' down';
    }

    protected function mtbfLabel(Monitor $monitor, int $days): string
    {
        $from = $this->currentTime()->subDays($days);
        $incidents = $monitor->incidents->filter(fn ($incident) => $incident->started_at?->gte($from))->values();

        if ($incidents->count() < 2) {
            return 'N/A';
        }

        $diffs = collect(range(1, $incidents->count() - 1))->map(function (int $index) use ($incidents) {
            return $incidents[$index - 1]->started_at?->diffInSeconds($incidents[$index]->started_at);
        })->filter();

        if ($diffs->isEmpty()) {
            return 'N/A';
        }

        return $this->durationLabel((int) round($diffs->avg()));
    }

    protected function durationSinceStatusChange(Monitor $monitor): string
    {
        if (! $monitor->last_status_changed_at) {
            return 'just now';
        }

        return $this->durationLabel($monitor->last_status_changed_at->diffInSeconds(now()));
    }

    protected function intervalLabel(int $seconds): string
    {
        return match (true) {
            $seconds < 60 => $seconds.' sec',
            $seconds < 3600 => (int) round($seconds / 60).' min',
            default => (int) round($seconds / 3600).' hr',
        };
    }

    protected function daysRemainingLabel($time): string
    {
        if (! $time) {
            return 'Unavailable';
        }

        $days = CarbonImmutable::parse($time)->startOfDay()->diffInDays(CarbonImmutable::now()->startOfDay(), false) * -1;

        return match (true) {
            $days < 0 => abs($days).' days overdue',
            $days === 0 => 'Expires today',
            $days === 1 => '1 day left',
            default => $days.' days left',
        };
    }

    protected function windowLabelSuffix(string $label): string
    {
        return str_ends_with($label, 'h') ? 'h' : 'd';
    }

    protected function effectiveIntervalSeconds(Monitor $monitor): int
    {
        $user = $monitor->relationLoaded('user') ? $monitor->user : $monitor->user()->first();

        if (! $user) {
            return (int) $monitor->interval_seconds;
        }

        return max((int) $monitor->interval_seconds, $user->minimumMonitorIntervalSeconds());
    }

    protected function timeAgo($time): string
    {
        $seconds = CarbonImmutable::parse($time)->diffInSeconds(now());

        return $this->durationLabel($seconds).' ago';
    }

    protected function durationLabel(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.' sec';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        $parts = [];

        if ($hours > 0) {
            $parts[] = $hours.'h';
        }

        if ($minutes > 0) {
            $parts[] = $minutes.'m';
        }

        if ($hours === 0 && $remainingSeconds > 0) {
            $parts[] = $remainingSeconds.'s';
        }

        return implode(', ', array_slice($parts, 0, 2));
    }

    protected function typeLabel(Monitor $monitor): string
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

    protected function targetHost(Monitor $monitor): string
    {
        if (! $monitor->target) {
            return 'Heartbeat endpoint';
        }

        return parse_url($monitor->target, PHP_URL_HOST) ?: $monitor->target;
    }

    protected function nextMaintenanceForMonitor(Monitor $monitor): ?MaintenanceWindow
    {
        return $monitor->maintenanceWindows
            ->filter(fn (MaintenanceWindow $window) => $window->ends_at?->gte(now()))
            ->sortBy('starts_at')
            ->first();
    }

    protected function maintenanceWindowItem(MaintenanceWindow $window): array
    {
        return [
            'id' => $window->id,
            'title' => $window->title,
            'message' => $window->message,
            'status' => ucfirst($this->maintenanceWindowStatus($window)),
            'startsAt' => $window->starts_at?->format('M j, Y H:i'),
            'endsAt' => $window->ends_at?->format('M j, Y H:i'),
            'startsAtValue' => $window->starts_at?->format('Y-m-d\\TH:i') ?? '',
            'endsAtValue' => $window->ends_at?->format('Y-m-d\\TH:i') ?? '',
            'duration' => $window->starts_at && $window->ends_at ? $this->durationLabel($window->starts_at->diffInSeconds($window->ends_at)) : 'n/a',
            'monitorIds' => $window->monitors->pluck('id')->all(),
            'monitorNames' => $window->monitors->pluck('name')->values()->all(),
            'notifyContacts' => $window->notify_contacts,
        ];
    }

    protected function maintenanceWindowLabel(MaintenanceWindow $window): string
    {
        return sprintf(
            '%s %s to %s',
            ucfirst($this->maintenanceWindowStatus($window)),
            $window->starts_at?->format('M j, H:i') ?? 'unknown',
            $window->ends_at?->format('M j, H:i') ?? 'unknown',
        );
    }

    protected function maintenanceWindowStatus(MaintenanceWindow $window): string
    {
        if ($window->status === MaintenanceWindow::STATUS_CANCELLED) {
            return MaintenanceWindow::STATUS_CANCELLED;
        }

        if ($window->ends_at?->lt(now())) {
            return MaintenanceWindow::STATUS_COMPLETED;
        }

        if ($window->starts_at?->lte(now()) && $window->ends_at?->gte(now())) {
            return MaintenanceWindow::STATUS_ACTIVE;
        }

        return MaintenanceWindow::STATUS_SCHEDULED;
    }

    protected function overallStatusForMonitors(Collection $monitors, ?Collection $statusPageIncidents = null): string
    {
        if ($monitors->isEmpty()) {
            return 'draft';
        }

        $openStatusPageIncident = $statusPageIncidents?->contains(fn (StatusPageIncident $incident) => $incident->resolved_at === null);
        $down = $monitors->filter(fn (Monitor $monitor) => $monitor->status === Monitor::STATUS_DOWN || $this->monitorHasOpenIncidentType($monitor, [Incident::TYPE_DOWNTIME]))->count();
        $warnings = $monitors->contains(fn (Monitor $monitor) => $this->monitorHasOpenIncidentType($monitor, [
            Incident::TYPE_DEGRADED_PERFORMANCE,
            Incident::TYPE_SSL_EXPIRY,
            Incident::TYPE_DOMAIN_EXPIRY,
        ]));
        $activeMaintenance = $monitors->contains(fn (Monitor $monitor) => $this->monitorHasActiveMaintenanceWindow($monitor));

        if ($openStatusPageIncident) {
            return 'degraded performance';
        }

        if ($down > 0) {
            return $down === $monitors->count() ? 'major outage' : 'degraded performance';
        }

        if ($warnings) {
            return 'degraded performance';
        }

        if ($activeMaintenance) {
            return 'maintenance';
        }

        return 'operational';
    }

    protected function publicOverallStatusLabel(Collection $monitors, Collection $maintenanceWindows, Collection $statusPageIncidents): string
    {
        if ($monitors->isEmpty()) {
            return 'No monitors configured';
        }

        $openStatusPageIncident = $statusPageIncidents->first(fn (StatusPageIncident $incident) => $incident->resolved_at === null);
        $down = $monitors->filter(fn (Monitor $monitor) => $this->publicMonitorStatus($monitor, $statusPageIncidents)['tone'] === 'down')->count();
        $warnings = $monitors->contains(fn (Monitor $monitor) => $this->publicMonitorStatus($monitor, $statusPageIncidents)['tone'] === 'warning');
        $activeMaintenance = $maintenanceWindows->contains(fn (MaintenanceWindow $window) => $this->maintenanceWindowStatus($window) === MaintenanceWindow::STATUS_ACTIVE);
        $maintenanceOnly = $monitors->every(fn (Monitor $monitor) => in_array($this->publicMonitorStatus($monitor, $statusPageIncidents)['tone'], ['maintenance', 'up'], true))
            && $monitors->contains(fn (Monitor $monitor) => $this->publicMonitorStatus($monitor, $statusPageIncidents)['tone'] === 'maintenance');

        if ($openStatusPageIncident) {
            return match ($openStatusPageIncident->impact) {
                StatusPageIncident::IMPACT_CRITICAL => 'Major outage',
                StatusPageIncident::IMPACT_MAJOR => 'Degraded performance',
                default => 'Service advisory',
            };
        }

        if ($down > 0) {
            return $down === $monitors->count() ? 'Major outage' : 'Degraded performance';
        }

        if ($warnings) {
            return 'Degraded performance';
        }

        if ($maintenanceOnly || $activeMaintenance) {
            return 'Scheduled maintenance';
        }

        return 'All systems operational';
    }

    protected function publicMonitorStatus(Monitor $monitor, Collection $statusPageIncidents): array
    {
        $openStatusPageIncident = $statusPageIncidents
            ->filter(fn (StatusPageIncident $incident) => $incident->resolved_at === null)
            ->sortByDesc(fn (StatusPageIncident $incident) => $this->statusPageIncidentImpactRank($incident->impact))
            ->first(fn (StatusPageIncident $incident) => $incident->monitors->contains('id', $monitor->id));

        if ($openStatusPageIncident) {
            return match ($openStatusPageIncident->impact) {
                StatusPageIncident::IMPACT_CRITICAL => [
                    'label' => 'Major outage',
                    'tone' => 'down',
                    'detail' => $this->statusPageIncidentStatusLabel($openStatusPageIncident->status),
                ],
                StatusPageIncident::IMPACT_MAJOR => [
                    'label' => 'Degraded performance',
                    'tone' => 'warning',
                    'detail' => $this->statusPageIncidentStatusLabel($openStatusPageIncident->status),
                ],
                default => [
                    'label' => 'Service advisory',
                    'tone' => 'warning',
                    'detail' => $this->statusPageIncidentStatusLabel($openStatusPageIncident->status),
                ],
            };
        }

        if ($monitor->status === Monitor::STATUS_PAUSED) {
            return [
                'label' => 'Paused',
                'tone' => 'warning',
                'detail' => 'Checks are paused',
            ];
        }

        if ($monitor->status === Monitor::STATUS_DOWN || $this->monitorHasOpenIncidentType($monitor, [Incident::TYPE_DOWNTIME])) {
            return [
                'label' => 'Major outage',
                'tone' => 'down',
                'detail' => $monitor->last_error_message ?: 'Automated outage detected',
            ];
        }

        if ($this->monitorHasOpenIncidentType($monitor, [Incident::TYPE_DEGRADED_PERFORMANCE])) {
            return [
                'label' => 'Degraded performance',
                'tone' => 'warning',
                'detail' => 'Response times are above threshold',
            ];
        }

        if ($this->monitorHasOpenIncidentType($monitor, [Incident::TYPE_SSL_EXPIRY])) {
            return [
                'label' => 'SSL warning',
                'tone' => 'warning',
                'detail' => 'Certificate expiry threshold reached',
            ];
        }

        if ($this->monitorHasOpenIncidentType($monitor, [Incident::TYPE_DOMAIN_EXPIRY])) {
            return [
                'label' => 'Domain warning',
                'tone' => 'warning',
                'detail' => 'Domain expiry threshold reached',
            ];
        }

        if ($this->monitorHasActiveMaintenanceWindow($monitor)) {
            return [
                'label' => 'Maintenance',
                'tone' => 'maintenance',
                'detail' => 'Planned maintenance in progress',
            ];
        }

        return [
            'label' => 'Operational',
            'tone' => 'up',
            'detail' => null,
        ];
    }

    protected function monitorHasOpenIncidentType(Monitor $monitor, array $types): bool
    {
        $aggregatedCount = $this->aggregatedOpenIncidentCount($monitor, $types);

        if ($aggregatedCount !== null) {
            return $aggregatedCount > 0;
        }

        if ($monitor->relationLoaded('openIncidents')) {
            return $monitor->openIncidents->contains(fn (Incident $incident) => in_array($incident->type, $types, true));
        }

        return $monitor->openIncidents()->whereIn('type', $types)->exists();
    }

    protected function monitorOpenIncidentCount(Monitor $monitor): int
    {
        if ($this->monitorHasLoadedAttribute($monitor, 'open_incidents_count')) {
            return (int) $monitor->getAttribute('open_incidents_count');
        }

        return $monitor->relationLoaded('openIncidents')
            ? $monitor->openIncidents->count()
            : $monitor->openIncidents()->count();
    }

    protected function monitorHasActiveMaintenanceWindow(Monitor $monitor): bool
    {
        if ($this->monitorHasLoadedAttribute($monitor, 'active_maintenance_windows_count')) {
            return (int) $monitor->getAttribute('active_maintenance_windows_count') > 0;
        }

        if ($monitor->relationLoaded('maintenanceWindows')) {
            return $monitor->maintenanceWindows->contains(
                fn (MaintenanceWindow $window) => $this->maintenanceWindowStatus($window) === MaintenanceWindow::STATUS_ACTIVE
            );
        }

        return $monitor->maintenanceWindows()
            ->where('maintenance_windows.status', '!=', MaintenanceWindow::STATUS_CANCELLED)
            ->where('maintenance_windows.starts_at', '<=', $this->currentTime())
            ->where('maintenance_windows.ends_at', '>=', $this->currentTime())
            ->exists();
    }

    protected function aggregatedOpenIncidentCount(Monitor $monitor, array $types): ?int
    {
        $attributeMap = [
            Incident::TYPE_DOWNTIME => 'open_downtime_incidents_count',
            Incident::TYPE_DEGRADED_PERFORMANCE => 'open_degraded_performance_incidents_count',
            Incident::TYPE_SSL_EXPIRY => 'open_ssl_expiry_incidents_count',
            Incident::TYPE_DOMAIN_EXPIRY => 'open_domain_expiry_incidents_count',
        ];

        $count = 0;

        foreach (array_unique($types) as $type) {
            $attribute = $attributeMap[$type] ?? null;

            if ($attribute === null || ! $this->monitorHasLoadedAttribute($monitor, $attribute)) {
                return null;
            }

            $count += (int) $monitor->getAttribute($attribute);
        }

        return $count;
    }

    protected function monitorHasLoadedAttribute(Monitor $monitor, string $attribute): bool
    {
        return array_key_exists($attribute, $monitor->getAttributes());
    }

    protected function statusPageIncidentImpactRank(string $impact): int
    {
        return match ($impact) {
            StatusPageIncident::IMPACT_CRITICAL => 3,
            StatusPageIncident::IMPACT_MAJOR => 2,
            default => 1,
        };
    }

    protected function publicOverallStatusTone(Collection $monitors, Collection $maintenanceWindows, Collection $statusPageIncidents): string
    {
        $label = $this->publicOverallStatusLabel($monitors, $maintenanceWindows, $statusPageIncidents);

        return match ($label) {
            'Major outage' => 'down',
            'Degraded performance' => 'warning',
            'Service advisory' => 'warning',
            'Scheduled maintenance' => 'maintenance',
            default => 'up',
        };
    }

    protected function latestStatusPageActivityAt(
        StatusPage $statusPage,
        ?Collection $monitors = null,
        ?Collection $maintenanceWindows = null,
        ?Collection $monitorIncidents = null,
    ): CarbonImmutable {
        $monitors = $monitors ?? ($statusPage->relationLoaded('monitors') ? $statusPage->monitors : collect());
        $maintenanceWindows = $maintenanceWindows ?? collect();
        $monitorIncidents = $monitorIncidents ?? collect();

        $timestamps = collect([$statusPage->updated_at])
            ->merge($statusPage->incidents->map(fn (StatusPageIncident $incident) => $incident->updated_at))
            ->merge($statusPage->incidents->flatMap(fn (StatusPageIncident $incident) => $incident->updates->pluck('updated_at')))
            ->merge($monitors->pluck('last_checked_at'))
            ->merge($monitors->pluck('last_status_changed_at'))
            ->merge($monitors->pluck('updated_at'))
            ->merge($maintenanceWindows->pluck('updated_at'))
            ->merge($monitorIncidents->pluck('updated_at'))
            ->merge($monitorIncidents->pluck('started_at'))
            ->merge($monitorIncidents->pluck('resolved_at'))
            ->filter()
            ->map(fn ($value) => CarbonImmutable::parse($value));

        return $timestamps->sortDesc()->first() ?? CarbonImmutable::now();
    }

    protected function statusPageIncidentItem(StatusPageIncident $incident): array
    {
        return [
            'id' => $incident->id,
            'title' => $incident->title,
            'message' => $incident->message,
            'status' => $incident->status,
            'statusLabel' => $this->statusPageIncidentStatusLabel($incident->status),
            'impact' => $incident->impact,
            'impactLabel' => ucfirst($incident->impact),
            'startedAt' => $incident->started_at?->format('M j, Y H:i'),
            'resolvedAt' => $incident->resolved_at?->format('M j, Y H:i'),
            'isResolved' => $incident->resolved_at !== null,
            'monitorIds' => $incident->monitors->pluck('id')->all(),
            'monitorNames' => $incident->monitors->pluck('name')->values()->all(),
            'updates' => $incident->updates
                ->sortByDesc('created_at')
                ->take(6)
                ->values()
                ->map(fn ($update) => [
                    'id' => $update->id,
                    'status' => $update->status,
                    'statusLabel' => $this->statusPageIncidentStatusLabel($update->status),
                    'message' => $update->message,
                    'createdAt' => $update->created_at?->format('M j, Y H:i'),
                ])->all(),
        ];
    }

    protected function statusPageIncidentStatusLabel(string $status): string
    {
        return match ($status) {
            StatusPageIncident::STATUS_INVESTIGATING => 'Investigating',
            StatusPageIncident::STATUS_IDENTIFIED => 'Identified',
            StatusPageIncident::STATUS_MONITORING => 'Monitoring',
            StatusPageIncident::STATUS_RESOLVED => 'Resolved',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    protected function statusPageItem(StatusPage $statusPage): array
    {
        return [
            'id' => $statusPage->id,
            'name' => $statusPage->name,
            'slug' => $statusPage->slug,
            'headline' => $statusPage->headline,
            'description' => $statusPage->description,
            'published' => $statusPage->published,
            'statusLabel' => ucfirst($this->overallStatusForMonitors($statusPage->monitors, $statusPage->incidents)),
            'monitorCount' => $statusPage->monitors->count(),
            'monitorIds' => $statusPage->monitors->pluck('id')->all(),
            'monitorNames' => $statusPage->monitors->pluck('name')->take(4)->values()->all(),
            'publicUrl' => $this->publicStatusPageUrl($statusPage),
            'updatedLabel' => $this->timeAgo($this->latestStatusPageActivityAt($statusPage, $statusPage->monitors)),
            'incidents' => $statusPage->incidents
                ->take(6)
                ->map(fn (StatusPageIncident $incident) => $this->statusPageIncidentItem($incident))
                ->all(),
            'incidentDefaults' => [
                'title' => '',
                'message' => '',
                'status' => StatusPageIncident::STATUS_INVESTIGATING,
                'impact' => StatusPageIncident::IMPACT_MINOR,
                'monitor_ids' => $statusPage->monitors->pluck('id')->all(),
            ],
            'capabilities' => $statusPage->monitors
                ->flatMap(fn (Monitor $monitor) => $monitor->capabilities)
                ->unique('id')
                ->values()
                ->map(fn (Capability $capability) => $this->capabilityItemFromMonitors(
                    $capability,
                    $statusPage->monitors->filter(fn (Monitor $monitor) => $monitor->capabilities->contains('id', $capability->id))->values(),
                ))
                ->all(),
        ];
    }

    /**
     * @param  array<int, int>  $selectedMonitorIds
     * @return array{
     *     options: array<int, array{id: int, name: string, status: string, type: string}>,
     *     query: string,
     *     results: array{
     *         data: array<int, array{id: int, name: string, status: string, type: string}>,
     *         currentPage: int,
     *         lastPage: int,
     *         perPage: int,
     *         total: int,
     *         from: int|null,
     *         to: int|null,
     *         previousPageUrl: string|null,
     *         nextPageUrl: string|null
     *     }
     * }
     */
    protected function monitorOptions(User $user, array $selectedMonitorIds = [], string $query = '', int $page = 1): array
    {
        $query = trim($query);
        $selectedMonitorIds = collect($selectedMonitorIds)
            ->map(fn (mixed $id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $visibleMonitors = $user->monitors()
            ->select(['id', 'name', 'status', 'type'])
            ->when($query !== '', fn ($monitorQuery) => $monitorQuery->where('name', 'like', '%'.$query.'%'))
            ->orderBy('name')
            ->paginate(24, ['*'], 'monitor_page', max(1, $page))
            ->withQueryString();
        $selectedMonitors = $selectedMonitorIds === []
            ? collect()
            : $user->monitors()
                ->whereKey($selectedMonitorIds)
                ->get(['id', 'name', 'status', 'type']);

        return [
            'options' => $selectedMonitors
                ->merge(collect($visibleMonitors->items()))
                ->unique('id')
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->map(fn (Monitor $monitor) => $this->monitorOptionItem($monitor))
                ->all(),
            'query' => $query,
            'results' => $this->paginateData($visibleMonitors, fn (Monitor $monitor) => $this->monitorOptionItem($monitor)),
        ];
    }

    /**
     * @return array{id: int, name: string, status: string, type: string}
     */
    protected function monitorOptionItem(Monitor $monitor): array
    {
        return [
            'id' => $monitor->id,
            'name' => $monitor->name,
            'status' => $monitor->status,
            'type' => strtoupper($monitor->type),
        ];
    }

    protected function responseTimeData(Monitor $monitor, string $range, string $granularity): array
    {
        $config = $this->responseRangeConfig($range);
        $granularityConfig = $this->responseGranularityConfig($range, $granularity);
        $to = $this->currentTime();
        $from = $to->subSeconds($config['seconds']);
        $windowStats = $this->windowStatsSince($monitor, $from, $range);

        $results = $this->windowCheckResults($monitor, $from, $to);

        $latencies = $results
            ->pluck('response_time_ms')
            ->filter(fn ($value) => $value !== null)
            ->map(fn ($value) => (int) $value)
            ->values()
            ->all();

        $stats = [
            'average' => $latencies !== [] ? (int) round(array_sum($latencies) / count($latencies)) : null,
            'median' => $this->median($latencies),
            'minimum' => $latencies !== [] ? min($latencies) : null,
            'maximum' => $latencies !== [] ? max($latencies) : null,
            'p95' => $this->percentile($latencies, 95),
            'downtimeLabel' => $windowStats['downtimeLabel'],
        ];
        $sampleCount = $results->count();
        $failedChecks = $results->where('status', 'down')->count();
        $slowChecks = $results->filter(fn (CheckResult $result) => (bool) data_get($result->meta, 'slow', false))->count();

        return [
            'label' => $config['label'],
            'granularity_label' => $granularityConfig['label'],
            'points' => $this->responseTimePoints($results, $from, $granularityConfig['seconds'], $granularityConfig['short_label_format']),
            'stats' => $stats,
            'signals' => [
                'sampleCount' => $sampleCount,
                'failedChecks' => $failedChecks,
                'slowChecks' => $slowChecks,
                'successRate' => $sampleCount > 0 ? round((($sampleCount - $failedChecks) / $sampleCount) * 100, 2) : null,
            ],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function responseTimeRangeOptions(): array
    {
        return collect([
            'hour' => 'Last hour',
            '12h' => 'Last 12 hours',
            'day' => 'Last day',
            'week' => 'Last week',
            'month' => 'Last month',
            'year' => 'Last year',
        ])->map(fn (string $label, string $value) => [
            'value' => $value,
            'label' => $label,
        ])->values()->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function responseTimeGranularityOptions(string $range): array
    {
        $rangeConfig = $this->responseRangeConfig($range);
        $granularityConfigMap = $this->responseGranularityConfigMap();

        return collect(['auto', ...$rangeConfig['allowed_granularities']])
            ->map(fn (string $value) => [
                'value' => $value,
                'label' => $value === 'auto'
                    ? 'Auto'
                    : $granularityConfigMap[$value]['label'],
            ])
            ->values()
            ->all();
    }

    protected function normalizeResponseRange(?string $range): string
    {
        return array_key_exists((string) $range, $this->responseRangeConfigMap())
            ? (string) $range
            : 'day';
    }

    /**
     * @return array{seconds: int, default_bucket_seconds: int, label: string, short_label_format: string, allowed_granularities: array<int, string>}
     */
    protected function responseRangeConfig(string $range): array
    {
        return $this->responseRangeConfigMap()[$range] ?? $this->responseRangeConfigMap()['day'];
    }

    /**
     * @return array<string, array{seconds: int, default_bucket_seconds: int, label: string, short_label_format: string, allowed_granularities: array<int, string>}>
     */
    protected function responseRangeConfigMap(): array
    {
        return [
            'hour' => [
                'seconds' => 3600,
                'default_bucket_seconds' => 300,
                'label' => 'Last hour',
                'short_label_format' => 'H:i',
                'allowed_granularities' => ['30s', '1m', '5m', '15m'],
            ],
            '12h' => [
                'seconds' => 43200,
                'default_bucket_seconds' => 1800,
                'label' => 'Last 12 hours',
                'short_label_format' => 'H:i',
                'allowed_granularities' => ['5m', '15m', '30m', '1h'],
            ],
            'day' => [
                'seconds' => 86400,
                'default_bucket_seconds' => 3600,
                'label' => 'Last day',
                'short_label_format' => 'M j, H:i',
                'allowed_granularities' => ['15m', '30m', '1h', '6h'],
            ],
            'week' => [
                'seconds' => 604800,
                'default_bucket_seconds' => 14400,
                'label' => 'Last week',
                'short_label_format' => 'M j',
                'allowed_granularities' => ['1h', '6h', '1d'],
            ],
            'month' => [
                'seconds' => 2592000,
                'default_bucket_seconds' => 86400,
                'label' => 'Last month',
                'short_label_format' => 'M j',
                'allowed_granularities' => ['6h', '1d', '1w'],
            ],
            'year' => [
                'seconds' => 31536000,
                'default_bucket_seconds' => 2592000,
                'label' => 'Last year',
                'short_label_format' => 'M Y',
                'allowed_granularities' => ['1d', '1w', '1mo'],
            ],
        ];
    }

    protected function normalizeResponseGranularity(?string $granularity, string $range): string
    {
        $allowedGranularities = ['auto', ...$this->responseRangeConfig($range)['allowed_granularities']];

        return in_array((string) $granularity, $allowedGranularities, true)
            ? (string) $granularity
            : 'auto';
    }

    /**
     * @return array{seconds: int, label: string, short_label_format: string}
     */
    protected function responseGranularityConfig(string $range, string $granularity): array
    {
        $rangeConfig = $this->responseRangeConfig($range);

        if ($granularity === 'auto') {
            return [
                'seconds' => $rangeConfig['default_bucket_seconds'],
                'label' => $this->responseBucketLabel($rangeConfig['default_bucket_seconds']),
                'short_label_format' => $rangeConfig['short_label_format'],
            ];
        }

        return $this->responseGranularityConfigMap()[$granularity]
            ?? [
                'seconds' => $rangeConfig['default_bucket_seconds'],
                'label' => $this->responseBucketLabel($rangeConfig['default_bucket_seconds']),
                'short_label_format' => $rangeConfig['short_label_format'],
            ];
    }

    /**
     * @return array<string, array{seconds: int, label: string, short_label_format: string}>
     */
    protected function responseGranularityConfigMap(): array
    {
        return [
            '30s' => [
                'seconds' => 30,
                'label' => '30 seconds',
                'short_label_format' => 'H:i:s',
            ],
            '1m' => [
                'seconds' => 60,
                'label' => '1 minute',
                'short_label_format' => 'H:i',
            ],
            '5m' => [
                'seconds' => 300,
                'label' => '5 minutes',
                'short_label_format' => 'H:i',
            ],
            '15m' => [
                'seconds' => 900,
                'label' => '15 minutes',
                'short_label_format' => 'H:i',
            ],
            '30m' => [
                'seconds' => 1800,
                'label' => '30 minutes',
                'short_label_format' => 'H:i',
            ],
            '1h' => [
                'seconds' => 3600,
                'label' => '1 hour',
                'short_label_format' => 'H:i',
            ],
            '6h' => [
                'seconds' => 21600,
                'label' => '6 hours',
                'short_label_format' => 'M j, H:i',
            ],
            '1d' => [
                'seconds' => 86400,
                'label' => '1 day',
                'short_label_format' => 'M j',
            ],
            '1w' => [
                'seconds' => 604800,
                'label' => '1 week',
                'short_label_format' => 'M j',
            ],
            '1mo' => [
                'seconds' => 2592000,
                'label' => '1 month',
                'short_label_format' => 'M Y',
            ],
        ];
    }

    protected function responseBucketLabel(int $seconds): string
    {
        return match ($seconds) {
            30 => '30 seconds',
            60 => '1 minute',
            300 => '5 minutes',
            900 => '15 minutes',
            1800 => '30 minutes',
            3600 => '1 hour',
            14400 => '4 hours',
            21600 => '6 hours',
            86400 => '1 day',
            604800 => '1 week',
            2592000 => '1 month',
            default => $this->intervalLabel($seconds),
        };
    }

    /**
     * @return array<int, array{label: string, shortLabel: string, value: ?int, status: string}>
     */
    protected function responseTimePoints(
        Collection $results,
        CarbonImmutable $from,
        int $bucketSeconds,
        string $shortLabelFormat,
    ): array {
        if ($results->isEmpty()) {
            return [];
        }

        $bucketAnchor = $this->responseTimeBucketAnchor($from, $bucketSeconds);
        $buckets = [];

        foreach ($results as $result) {
            $checkedAt = CarbonImmutable::parse($result->checked_at)->setTimezone(config('app.timezone'));
            $bucketIndex = (int) floor($bucketAnchor->diffInSeconds($checkedAt) / $bucketSeconds);
            $buckets[$bucketIndex] ??= [];
            $buckets[$bucketIndex][] = $result;
        }

        ksort($buckets);

        return collect($buckets)->map(function (array $bucket, int $index) use ($bucketAnchor, $bucketSeconds, $shortLabelFormat): array {
            $bucketStart = $bucketAnchor->addSeconds($index * $bucketSeconds);
            $responseTimes = collect($bucket)
                ->pluck('response_time_ms')
                ->filter(fn ($value) => $value !== null)
                ->map(fn ($value) => (int) $value)
                ->values();

            $status = collect($bucket)->contains(fn (CheckResult $result) => $result->status === 'down')
                ? 'down'
                : (collect($bucket)->contains(fn (CheckResult $result) => (bool) data_get($result->meta, 'slow', false)) ? 'warning' : 'up');

            return [
                'label' => $bucketStart->format('M j, Y H:i'),
                'shortLabel' => $bucketStart->format($shortLabelFormat),
                'value' => $responseTimes->isNotEmpty() ? (int) round($responseTimes->avg()) : null,
                'status' => $status,
            ];
        })->values()->all();
    }

    protected function responseTimeBucketAnchor(CarbonImmutable $from, int $bucketSeconds): CarbonImmutable
    {
        $localFrom = $from->setTimezone(config('app.timezone'));

        return match ($bucketSeconds) {
            86400 => $localFrom->startOfDay(),
            604800 => $localFrom->startOfWeek(),
            2592000 => $localFrom->startOfMonth(),
            default => $localFrom,
        };
    }

    protected function incidentTimeline(Incident $incident, Collection $checkResults): array
    {
        return $checkResults
            ->filter(fn (CheckResult $result) => $this->checkResultRelevantToIncident($incident, $result))
            ->flatMap(function (CheckResult $result) {
                $attemptHistory = collect(data_get($result->meta, 'attempt_history', []));

                if ($attemptHistory->isEmpty()) {
                    $attemptHistory = collect([[
                        'attempt' => 1,
                        'status' => $result->status,
                        'checked_at' => $result->checked_at?->toIso8601String(),
                        'response_time_ms' => $result->response_time_ms,
                        'http_status_code' => $result->http_status_code,
                        'error_type' => $result->error_type,
                        'error_message' => $result->error_message,
                        'slow' => (bool) data_get($result->meta, 'slow', false),
                    ]]);
                }

                return $attemptHistory->map(function (array $attempt) use ($result): array {
                    $checkedAt = CarbonImmutable::parse($attempt['checked_at'] ?? $result->checked_at);

                    return [
                        'checkedAt' => $checkedAt->format('M j, Y H:i:s'),
                        'attemptLabel' => sprintf('Attempt %d of %d', (int) ($attempt['attempt'] ?? 1), (int) $result->attempts),
                        'status' => ucfirst((string) ($attempt['status'] ?? $result->status)),
                        'responseTime' => isset($attempt['response_time_ms']) && $attempt['response_time_ms'] !== null ? $attempt['response_time_ms'].' ms' : 'n/a',
                        'httpStatus' => $attempt['http_status_code'] ?? $result->http_status_code,
                        'error' => $attempt['error_message'] ?? $result->error_message,
                    ];
                });
            })
            ->values()
            ->all();
    }

    protected function checkResultRelevantToIncident(Incident $incident, CheckResult $result): bool
    {
        if ($incident->latest_check_result_id === $result->id || $incident->first_check_result_id === $result->id) {
            return true;
        }

        return match ($incident->type) {
            Incident::TYPE_DOWNTIME => $result->status === 'down',
            Incident::TYPE_DEGRADED_PERFORMANCE => (bool) data_get($result->meta, 'slow', false),
            Incident::TYPE_SSL_EXPIRY => (bool) data_get($result->meta, 'ssl_expiring', false),
            Incident::TYPE_DOMAIN_EXPIRY => (bool) data_get($result->meta, 'domain_expiring', false),
            default => false,
        };
    }

    protected function formatIncidentCheckResult(?CheckResult $result): ?array
    {
        if (! $result) {
            return null;
        }

        return [
            'checkedAt' => $result->checked_at?->format('M j, Y H:i:s') ?? 'Unknown',
            'status' => ucfirst($result->status),
            'responseTime' => $result->response_time_ms !== null ? $result->response_time_ms.' ms' : 'n/a',
            'httpStatus' => $result->http_status_code,
            'error' => $result->error_message,
        ];
    }

    protected function incidentTypeLabel(Incident $incident): string
    {
        return match ($incident->type) {
            Incident::TYPE_DEGRADED_PERFORMANCE => 'Degraded performance',
            Incident::TYPE_SSL_EXPIRY => 'SSL expiry',
            Incident::TYPE_DOMAIN_EXPIRY => 'Domain expiry',
            default => 'Downtime',
        };
    }

    protected function incidentStatusLabel(Incident $incident): string
    {
        if ($incident->resolved_at) {
            return 'Resolved';
        }

        return $incident->severity === Incident::SEVERITY_CRITICAL ? 'Critical' : 'Open';
    }

    protected function publicStatusPageUrl(StatusPage $statusPage): string
    {
        $statusPage->loadMissing('user:id,public_status_key');

        return route('public-status-pages.show', [
            'ownerKey' => $statusPage->user?->public_status_key,
            'statusPage' => $statusPage->slug,
        ]);
    }

    /**
     * @param  array<int, int>  $values
     */
    protected function percentile(array $values, int $percentile): ?int
    {
        if ($values === []) {
            return null;
        }

        sort($values);

        $index = (int) ceil((count($values) * $percentile) / 100) - 1;
        $index = max(0, min(count($values) - 1, $index));

        return $values[$index];
    }

    /**
     * @param  array<int, int>  $values
     */
    protected function median(array $values): ?int
    {
        if ($values === []) {
            return null;
        }

        sort($values);

        $count = count($values);
        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return $values[$middle];
        }

        return (int) round(($values[$middle - 1] + $values[$middle]) / 2);
    }
}

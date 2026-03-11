export type MonitorBarState = 'up' | 'down' | 'unknown';

export type CapabilityHealth = {
    id: number;
    name: string;
    slug: string;
    status: string;
    tone: 'up' | 'down' | 'warning' | 'maintenance';
    summary: string;
    customerImpact: string;
    linkedChecks: number;
    affectedChecks: number;
    regions: string;
    openIncidents: number;
    monitorNames: string[];
};

export type MonitorListItem = {
    id: number;
    name: string;
    status: string;
    type: string;
    typeLabel: string;
    statusSummary: string;
    intervalLabel: string;
    lastCheckedLabel: string;
    responseTimeLabel: string;
    uptimePercentLabel: string;
    bars: MonitorBarState[];
    target: string | null;
    showUrl: string;
    editUrl: string;
};

export type MonitorSummary = {
    up: number;
    down: number;
    paused: number;
    total: number;
    usageLabel: string;
    canCreate: boolean;
};

export type WindowStats = {
    uptimeValue?: number;
    uptimeLabel: string;
    incidentsCount: number;
    downtimeLabel: string;
    withoutIncidentsLabel: string;
};

export type AggregateWindowStats = {
    uptimeLabel: string;
    mtbfLabel: string;
    withoutIncidentsLabel: string;
    incidentsCount: number;
};

export type ResponsePoint = {
    label: string;
    shortLabel: string;
    value: number | null;
    status: 'up' | 'down' | 'warning';
};

export type ResponseRangeOption = {
    value: string;
    label: string;
};

export type MaintenanceWindowItem = {
    id: number;
    title: string;
    message?: string | null;
    status: string;
    startsAt: string;
    endsAt: string;
    startsAtValue: string;
    endsAtValue: string;
    duration: string;
    monitorIds: number[];
    monitorNames: string[];
    notifyContacts: boolean;
};

export type StatusPageLink = {
    name: string;
    slug: string;
    published: boolean;
    publicUrl: string;
};

export type MonitorHistory = {
    last6Bars: MonitorBarState[];
    last24Bars: MonitorBarState[];
    last7Bars: MonitorBarState[];
    last6Hours: WindowStats;
    last24Stats: WindowStats;
    last7Days: WindowStats;
    last30Days: WindowStats;
    last365Days: WindowStats;
    customRange: WindowStats;
    mtbf: string;
    responseTimeRange: string;
    responseTimeRangeLabel: string;
    responseTimeRangeOptions: ResponseRangeOption[];
    responseTimeGranularity: string;
    responseTimeGranularityLabel: string;
    responseTimeGranularityOptions: ResponseRangeOption[];
    responseTimeChart: ResponsePoint[];
    responseTimeStats: {
        average: number | null;
        median: number | null;
        minimum: number | null;
        maximum: number | null;
        p95: number | null;
        downtimeLabel: string;
    };
    responseTimeSignals: {
        sampleCount: number;
        failedChecks: number;
        slowChecks: number;
        successRate: number | null;
    };
};

export type DetailedMonitor = {
    id: number;
    name: string;
    type: string;
    typeLabel: string;
    status: string;
    statusLabel: string;
    target: string | null;
    targetLabel: string;
    targetHost: string;
    lastCheckLabel: string;
    checkedEveryLabel: string;
    currentStatusLabel: string;
    currentStatusDurationValue: string;
    currentStatusDurationLabel: string;
    domainSsl: {
        host: string;
        domainValidUntil: string;
        domainRegistrar: string;
        domainDaysRemaining: string;
        domainCheckedAt: string;
        sslValidUntil: string;
        issuer: string;
        sslDaysRemaining: string;
        sslCheckedAt: string;
    };
    nextMaintenance: string;
    maintenanceDefaults: MaintenanceFormData;
    maintenanceWindows: MaintenanceWindowItem[];
    region: string;
    heartbeatUrl: string | null;
    lastHeartbeatLabel: string | null;
    nextHeartbeatDeadlineLabel: string | null;
    recentIncidents: Array<{
        id: number;
        startedAt: string;
        endedAt: string;
        duration: string;
        reason: string;
        typeLabel: string;
        severityLabel: string;
        statusLabel: string;
        showUrl: string;
    }>;
    notificationLog: Array<{
        type: string;
        channel: string;
        status: string;
        subject: string;
        recipient?: string | null;
        time: string;
    }>;
    statusPages: StatusPageLink[];
};

export type MonitorFormData = {
    id?: number | null;
    name: string;
    type: string;
    target: string;
    request_method: string;
    interval_seconds: number;
    timeout_seconds: number;
    retry_limit: number;
    follow_redirects: boolean;
    custom_headers: string;
    auth_username: string;
    auth_password: string;
    expected_status_code: number;
    expected_keyword: string;
    keyword_match_type: string;
    packet_count: number;
    synthetic_steps: string;
    latency_threshold_ms: number;
    degraded_consecutive_checks: number;
    critical_alert_after_minutes: number;
    downtime_webhook_urls: string;
    capability_names: string;
    ssl_threshold_days: number;
    domain_threshold_days: number;
    heartbeat_grace_seconds: number;
    region: string;
    contact_ids: number[];
};

export type MonitorFormOptions = {
    types: Array<{ value: string; label: string }>;
    methods: string[];
    intervals: number[];
    regions: string[];
    existingCapabilities: string[];
    keywordMatchTypes: string[];
    guardrails: {
        maxTimeoutSeconds: number;
        maxRetryLimit: number;
        maxContactsPerMonitor: number;
        maxDowntimeWebhookUrls: number;
        maxCustomHeaderCount: number;
        maxCustomHeaderValueLength: number;
    };
};

export type MonitorFormMembership = {
    planLabel: string;
    planValue: string;
    priceLabel: string;
    monitorLimit: number;
    monitorLimitLabel: string;
    currentMonitorCount: number;
    minimumIntervalLabel: string;
    advancedFeaturesUnlocked: boolean;
    supportsDowntimeWebhooks: boolean;
    manageUrl: string | null;
    canCreate: boolean;
    standardProfileLabel: string;
};

export type NotificationContactOption = {
    id: number;
    name: string;
    email: string;
    enabled: boolean;
};

export type MonitorOption = {
    id: number;
    name: string;
    status: string;
    type: string;
};

export type StatusPageItem = {
    id: number;
    name: string;
    slug: string;
    headline: string | null;
    description: string | null;
    published: boolean;
    statusLabel: string;
    monitorCount: number;
    monitorIds: number[];
    monitorNames: string[];
    publicUrl: string;
    updatedLabel: string;
    incidents: StatusPageIncidentItem[];
    incidentDefaults: StatusPageIncidentFormData;
};

export type StatusPageFormData = {
    name: string;
    slug: string;
    headline: string;
    description: string;
    published: boolean;
    monitor_ids: number[];
};

export type StatusPageIncidentUpdateItem = {
    id: number;
    status: string;
    statusLabel: string;
    message: string;
    createdAt: string | null;
};

export type StatusPageIncidentItem = {
    id: number;
    title: string;
    message: string | null;
    status: string;
    statusLabel: string;
    impact: string;
    impactLabel: string;
    startedAt: string | null;
    resolvedAt: string | null;
    isResolved: boolean;
    monitorIds: number[];
    monitorNames: string[];
    updates: StatusPageIncidentUpdateItem[];
};

export type StatusPageIncidentFormData = {
    title: string;
    message: string;
    status: string;
    impact: string;
    monitor_ids: number[];
};

export type StatusPageIncidentUpdateFormData = {
    status: string;
    message: string;
};

export type IntegrationContact = {
    id: number;
    name: string;
    email: string;
    enabled: boolean;
    isPrimary: boolean;
    logsCount: number;
    monitorNames: string[];
};

export type ContactFormData = {
    name: string;
    email: string;
    enabled: boolean;
    is_primary: boolean;
};

export type NotificationLogItem = {
    monitor: string | null;
    contact: string | null;
    type: string;
    status: string;
    subject: string;
    failureMessage: string | null;
    sentAt: string;
};

export type PaginatedData<T> = {
    data: T[];
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
    from: number | null;
    to: number | null;
    previousPageUrl: string | null;
    nextPageUrl: string | null;
};

export type ApiTokenItem = {
    id: number;
    name: string;
    createdAt: string | null;
    lastUsedAt: string | null;
    lastUsedLabel: string;
};

export type ApiTokenFormData = {
    name: string;
};

export type IntegrationSummary = {
    contacts: number;
    enabled: number;
    apiTokens: number;
    emailsSent: number;
    emailsPending: number;
    emailsFailed: number;
    mailer: string;
};

export type IntegrationRuntime = {
    appUrl: string;
    apiBaseUrl: string;
    mailer: string;
    queueConnection: string;
    monitorQueue: string;
    notificationQueue: string;
    dispatchBatchSize: number;
    dispatchMaxBatches: number;
    claimTtlSeconds: number;
    dueMonitors: number;
    claimedMonitors: number;
    staleClaims: number;
};

export type MaintenanceFormData = {
    title: string;
    message: string;
    starts_at: string;
    ends_at: string;
    notify_contacts: boolean;
    monitor_ids: number[];
};

export type PublicStatusPageData = {
    name: string;
    headline: string;
    description: string | null;
    slug: string;
    overallStatus: string;
    overallTone: 'up' | 'down' | 'warning' | 'maintenance';
    updatedLabel: string;
    capabilities: CapabilityHealth[];
    monitors: Array<{
        name: string;
        type: string;
        status: string;
        statusTone: 'up' | 'down' | 'warning' | 'maintenance';
        statusDetail: string | null;
        uptimeLabel: string;
        lastCheckedLabel: string;
        responseTimeLabel: string;
        activeMaintenance: boolean;
        capabilities: string[];
    }>;
    incidents: Array<{
        title: string;
        status: string;
        impact: string;
        message: string | null;
        startedAt: string | null;
        endedAt: string | null;
        monitors: string[];
        capabilities: string[];
        updates: Array<{
            status: string;
            message: string;
            createdAt: string | null;
        }>;
    }>;
    monitorIncidents: Array<{
        monitor: string | null;
        status: string;
        reason: string;
        startedAt: string | null;
        endedAt: string | null;
        capabilities: string[];
    }>;
    recentUpdates: Array<{
        incidentTitle: string;
        status: string;
        impact: string;
        message: string;
        createdAt: string | null;
    }>;
    maintenance: MaintenanceWindowItem[];
};

export type IncidentCheckSummary = {
    checkedAt: string;
    status: string;
    responseTime: string;
    httpStatus: number | null;
    error: string | null;
};

export type IncidentTimelineEntry = {
    checkedAt: string;
    attemptLabel: string;
    status: string;
    responseTime: string;
    httpStatus: number | null;
    error: string | null;
};

export type IncidentDetail = {
    id: number;
    monitorName: string | null;
    monitorUrl: string;
    typeLabel: string;
    severityLabel: string;
    statusLabel: string;
    reason: string;
    startedAt: string;
    endedAt: string;
    duration: string;
    operatorNotes: string;
    rootCauseSummary: string;
    capabilities: CapabilityHealth[];
    customerImpact: string;
    firstFailedCheck: IncidentCheckSummary | null;
    lastGoodCheck: IncidentCheckSummary | null;
    latestCheck: IncidentCheckSummary | null;
    timeline: IncidentTimelineEntry[];
    notificationHistory: Array<{
        type: string;
        status: string;
        subject: string;
        contact: string;
        sentAt: string;
    }>;
};

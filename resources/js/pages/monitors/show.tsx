import { Deferred, Head, Link, router, useForm } from '@inertiajs/react';
import { BellRing, CalendarDays, ChevronLeft, Copy, ExternalLink, Pause, Pencil, Play, ShieldCheck, Trash2 } from 'lucide-react';
import type { ChangeEvent } from 'react';
import { useState } from 'react';
import { PageCard } from '@/components/monitoring/page-card';
import { RegionMap } from '@/components/monitoring/region-map';
import { ResponseTimeChart } from '@/components/monitoring/response-time-chart';
import { StatusChip } from '@/components/monitoring/status-chip';
import { UptimeBars } from '@/components/monitoring/uptime-bars';
import { usePageAutoRefresh } from '@/hooks/use-page-auto-refresh';
import MonitoringLayout from '@/layouts/monitoring-layout';
import type { CapabilityHealth, DetailedMonitor, MaintenanceFormData, MonitorHistory } from '@/types/monitoring';

type MonitorShowProps = {
    monitor: DetailedMonitor;
    monitorHistory?: MonitorHistory;
    monitorCapabilities?: CapabilityHealth[];
};

function StatBlock({
    label,
    value,
    subtext,
    valueClassName = 'text-[#57c7c2]',
}: {
    label: string;
    value: string;
    subtext: string;
    valueClassName?: string;
}) {
    return (
        <div>
            <div className="text-[15px] uppercase tracking-[0.18em] text-[#8e9aac]">{label}</div>
            <div className={`mt-3 text-[38px] font-semibold tracking-[-0.06em] lg:text-[42px] ${valueClassName}`}>{value}</div>
            <div className="mt-2 text-[15px] text-[#9ca7b9] lg:text-[16px]">{subtext}</div>
        </div>
    );
}

function MetricPanel({ title, value, caption }: { title: string; value: string; caption: string }) {
    return (
        <div>
            <div className="text-[14px] uppercase tracking-[0.18em] text-[#8e9aac]">{title}</div>
            <div className="mt-2 text-[30px] font-semibold tracking-[-0.05em] text-white lg:text-[34px]">{value}</div>
            <div className="text-[15px] text-[#9ca7b9] lg:text-[16px]">{caption}</div>
        </div>
    );
}

function SignalWindowCard({
    title,
    uptimeLabel,
    bars,
    incidentsCount,
    downtimeLabel,
    note,
}: {
    title: string;
    uptimeLabel: string;
    bars: MonitorHistory['last24Bars'];
    incidentsCount: number;
    downtimeLabel: string;
    note: string;
}) {
    return (
        <PageCard className="p-5">
            <div className="flex items-center justify-between gap-3">
                <div className="text-[14px] uppercase tracking-[0.18em] text-[#8e9aac]">{title}</div>
                <div className="text-[22px] font-semibold tracking-[-0.04em] text-white">{uptimeLabel}</div>
            </div>
            <div className="mt-5">
                <UptimeBars bars={bars} />
            </div>
            <div className="mt-3 text-[13px] text-[#6d7889]">{note}</div>
            <div className="mt-4 text-[15px] text-[#9ca7b9] lg:text-[16px]">
                {incidentsCount} incidents, {downtimeLabel}
            </div>
        </PageCard>
    );
}

function InfoTile({ label, value, hint }: { label: string; value: string; hint: string }) {
    return (
        <div className="rounded-[18px] border border-[#2b3544] bg-[#121821] px-4 py-4">
            <div className="text-[13px] uppercase tracking-[0.18em] text-[#8e9aac]">{label}</div>
            <div className="mt-2 text-[18px] font-semibold tracking-[-0.03em] text-white">{value}</div>
            <div className="mt-1 text-[14px] text-[#9ca7b9]">{hint}</div>
        </div>
    );
}

function LoadingPanel({
    title,
    description,
    className = 'p-6',
}: {
    title: string;
    description: string;
    className?: string;
}) {
    return (
        <PageCard className={className}>
            <div className="text-[17px] font-semibold text-white">{title}</div>
            <div className="mt-2 text-sm text-[#9ca7b9]">{description}</div>
        </PageCard>
    );
}

function formatMilliseconds(value: number | null) {
    return value !== null ? `${value} ms` : 'N/A';
}

function formatPercentage(value: number | null) {
    if (value === null) {
        return 'N/A';
    }

    return `${value.toFixed(value % 1 === 0 ? 0 : 2)}%`;
}

export default function MonitorShow({ monitor, monitorHistory, monitorCapabilities }: MonitorShowProps) {
    const [showMaintenanceForm, setShowMaintenanceForm] = useState(false);
    const maintenanceForm = useForm<MaintenanceFormData>(monitor.maintenanceDefaults);

    usePageAutoRefresh({ only: ['monitor'] });

    const updateResponseRange = (event: ChangeEvent<HTMLSelectElement>) => {
        if (!monitorHistory) {
            return;
        }

        router.get(
            `/monitors/${monitor.id}`,
            {
                response_range: event.target.value,
                response_granularity: monitorHistory.responseTimeGranularity,
            },
            { only: ['monitorHistory'], preserveScroll: true, preserveState: true, replace: true },
        );
    };

    const updateResponseGranularity = (event: ChangeEvent<HTMLSelectElement>) => {
        if (!monitorHistory) {
            return;
        }

        router.get(
            `/monitors/${monitor.id}`,
            {
                response_range: monitorHistory.responseTimeRange,
                response_granularity: event.target.value,
            },
            { only: ['monitorHistory'], preserveScroll: true, preserveState: true, replace: true },
        );
    };

    return (
        <MonitoringLayout>
            <Head title={monitor.name} />
            <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_308px]">
                <section className="space-y-4">
                    <PageCard className="p-4 lg:p-5">
                        <Link
                            href="/monitors"
                            className="inline-flex items-center gap-3 rounded-[16px] border border-[#2b3544] bg-[#171d28] px-4 py-2.5 text-base text-[#d5def3]"
                        >
                            <ChevronLeft className="size-4" />
                            Checks
                        </Link>

                        <div className="mt-4 flex items-start gap-4">
                            <StatusChip status={monitor.status} large />
                            <div className="min-w-0">
                                <div className="text-[11px] font-semibold uppercase tracking-[0.26em] text-[#7f8b9b]">
                                    Check profile
                                </div>
                                <h1 className="mt-2 text-[34px] font-semibold tracking-[-0.06em] text-white lg:text-[38px]">
                                    {monitor.name}
                                </h1>
                                <div className="mt-2.5 flex flex-wrap items-center gap-2 text-[14px] text-[#cfd8ec]">
                                    <span className="rounded-full border border-[#2b3544] bg-[#121821] px-3 py-1.5 uppercase tracking-[0.18em] text-[#aebadc]">
                                        {monitor.typeLabel}
                                    </span>
                                    <span className="rounded-full border border-[#2b3544] bg-[#121821] px-3 py-1.5 uppercase tracking-[0.18em] text-[#aebadc]">
                                        {monitor.region}
                                    </span>
                                    <span className="rounded-full border border-[#57c7c2]/20 bg-[#15222a] px-3 py-1.5 text-[#def8f4]">
                                        {monitor.currentStatusLabel}
                                    </span>
                                </div>
                                <div className="mt-3 flex flex-wrap items-center gap-3 text-[15px] text-[#9eacc7] lg:text-[16px]">
                                    {monitor.heartbeatUrl ? (
                                        <span className="text-[#dbe4f9]">{monitor.targetLabel}</span>
                                    ) : monitor.target ? (
                                        <a
                                            href={monitor.target}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex items-center gap-2 text-[#57c7c2] underline decoration-[#57c7c2]/25 underline-offset-4"
                                        >
                                            {monitor.target}
                                            <ExternalLink className="size-4" />
                                        </a>
                                    ) : (
                                        <span className="text-[#dbe4f9]">Heartbeat endpoint</span>
                                    )}
                                </div>
                            </div>
                        </div>

                        <div className="mt-4 flex flex-wrap items-center gap-3">
                            <button
                                type="button"
                                className="inline-flex items-center gap-3 rounded-[16px] border border-[#2b3544] bg-[#171d28] px-4 py-2.5 text-base text-[#d5def3]"
                                onClick={() => router.post(`/monitors/${monitor.id}/run-now`)}
                            >
                                <Play className="size-4 text-[#57c7c2]" />
                                Run check
                            </button>
                            <button
                                type="button"
                                className="inline-flex items-center gap-3 rounded-[16px] border border-[#2b3544] bg-[#171d28] px-4 py-2.5 text-base text-[#d5def3]"
                                onClick={() => router.post(`/monitors/${monitor.id}/test-notification`)}
                            >
                                <BellRing className="size-4 text-[#57c7c2]" />
                                Test alert
                            </button>
                            <button
                                type="button"
                                className="inline-flex items-center gap-3 rounded-[16px] border border-[#2b3544] bg-[#171d28] px-4 py-2.5 text-base text-[#d5def3]"
                                onClick={() => router.post(`/monitors/${monitor.id}/toggle`)}
                            >
                                <Pause className="size-4 text-[#7c8cff]" />
                                {monitor.status === 'paused' ? 'Resume' : 'Pause'}
                            </button>
                            <Link
                                href={`/monitors/${monitor.id}/edit`}
                                className="inline-flex items-center gap-3 rounded-[16px] border border-[#2b3544] bg-[#171d28] px-4 py-2.5 text-base text-[#d5def3]"
                            >
                                <Pencil className="size-4 text-[#7c8cff]" />
                                Edit
                            </Link>
                        </div>
                    </PageCard>

                    <div className="grid gap-4 xl:grid-cols-3">
                        <PageCard className="p-5">
                            <StatBlock
                                label="Live state"
                                value={monitor.currentStatusLabel}
                                subtext={monitor.currentStatusDurationLabel}
                                valueClassName="text-[#57c7c2]"
                            />
                        </PageCard>
                        <PageCard className="p-5">
                            <StatBlock
                                label="Last check"
                                value={monitor.lastCheckLabel}
                                subtext={`Checked every ${monitor.checkedEveryLabel}`}
                                valueClassName="text-white"
                            />
                        </PageCard>
                        <PageCard className="p-5">
                            <StatBlock
                                label="Current streak"
                                value={monitor.currentStatusDurationValue}
                                subtext={`${monitor.currentStatusLabel} since the last status change`}
                                valueClassName="text-white"
                            />
                        </PageCard>
                    </div>

                    <Deferred
                        data="monitorHistory"
                        fallback={
                            <div className="grid gap-4 xl:grid-cols-3">
                                <LoadingPanel title="6h signal" description="Loading recent uptime segments…" className="p-5" />
                                <LoadingPanel title="24h signal" description="Loading daily uptime segments…" className="p-5" />
                                <LoadingPanel title="7d signal" description="Loading weekly uptime segments…" className="p-5" />
                            </div>
                        }
                    >
                        {monitorHistory ? (
                            <div className="grid gap-4 xl:grid-cols-3">
                                <SignalWindowCard
                                    title="6h signal"
                                    uptimeLabel={monitorHistory.last6Hours.uptimeLabel}
                                    bars={monitorHistory.last6Bars}
                                    incidentsCount={monitorHistory.last6Hours.incidentsCount}
                                    downtimeLabel={monitorHistory.last6Hours.downtimeLabel}
                                    note="Twelve 30-minute segments"
                                />
                                <SignalWindowCard
                                    title="24h signal"
                                    uptimeLabel={monitorHistory.last24Stats.uptimeLabel}
                                    bars={monitorHistory.last24Bars}
                                    incidentsCount={monitorHistory.last24Stats.incidentsCount}
                                    downtimeLabel={monitorHistory.last24Stats.downtimeLabel}
                                    note="Twenty-four 1-hour segments"
                                />
                                <SignalWindowCard
                                    title="7d signal"
                                    uptimeLabel={monitorHistory.last7Days.uptimeLabel}
                                    bars={monitorHistory.last7Bars}
                                    incidentsCount={monitorHistory.last7Days.incidentsCount}
                                    downtimeLabel={monitorHistory.last7Days.downtimeLabel}
                                    note="Four segments per day"
                                />
                            </div>
                        ) : null}
                    </Deferred>

                    <Deferred
                        data="monitorCapabilities"
                        fallback={
                            <LoadingPanel
                                title="Customer-facing capabilities."
                                description="Loading capability impact for this check…"
                            />
                        }
                    >
                        {(() => {
                            const capabilities = monitorCapabilities ?? [];

                            return (
                                <PageCard className="p-6">
                                    <div className="flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
                                        <div>
                                            <div className="text-[17px] font-semibold text-white">
                                                Customer-facing capabilities<span className="text-[#7c8cff]">.</span>
                                            </div>
                                            <div className="mt-1 text-sm text-[#9ca7b9]">
                                                Use capabilities to understand which customer workflows are impacted when this check changes state.
                                            </div>
                                        </div>
                                    </div>

                                    {capabilities.length === 0 ? (
                                        <div className="mt-5 rounded-[18px] border border-[#2b3544] bg-[#121821] px-4 py-4 text-sm text-[#9ca7b9]">
                                            This check is not mapped to a customer-facing capability yet. Add capability labels from the check editor to connect it to sign in, checkout, API delivery, or any other user-facing workflow.
                                        </div>
                                    ) : (
                                        <div className="mt-5 grid gap-4 xl:grid-cols-2">
                                            {capabilities.map((capability) => (
                                                <div key={capability.id} className="rounded-[18px] border border-[#2b3544] bg-[#121821] px-4 py-4">
                                                    <div className="flex items-start justify-between gap-3">
                                                        <div>
                                                            <div className="text-[18px] font-semibold text-white">{capability.name}</div>
                                                            <div className="mt-1 text-sm text-[#9ca7b9]">{capability.regions}</div>
                                                        </div>
                                                        <span
                                                            className={
                                                                capability.tone === 'up'
                                                                    ? 'rounded-full bg-[#57c7c2]/15 px-3 py-1 text-xs font-medium text-[#57c7c2]'
                                                                    : capability.tone === 'down'
                                                                      ? 'rounded-full bg-[#ff7a72]/15 px-3 py-1 text-xs font-medium text-[#ffb2ad]'
                                                                      : capability.tone === 'maintenance'
                                                                        ? 'rounded-full bg-[#7483a5]/15 px-3 py-1 text-xs font-medium text-[#bfc9da]'
                                                                        : 'rounded-full bg-[#7c8cff]/15 px-3 py-1 text-xs font-medium text-[#d0d8ff]'
                                                            }
                                                        >
                                                            {capability.status}
                                                        </span>
                                                    </div>
                                                    <div className="mt-3 text-sm text-[#dce6fb]">{capability.customerImpact}</div>
                                                    <div className="mt-3 text-sm text-[#9ca7b9]">{capability.summary}</div>
                                                    <div className="mt-4 flex flex-wrap gap-2 text-xs text-[#aebadc]">
                                                        <span className="rounded-full bg-[#171d28] px-3 py-1">{capability.linkedChecks} linked checks</span>
                                                        <span className="rounded-full bg-[#171d28] px-3 py-1">{capability.openIncidents} open incidents</span>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </PageCard>
                            );
                        })()}
                    </Deferred>

                    <PageCard className="p-6">
                        <div className="text-[17px] font-semibold text-white">
                            Domain posture<span className="text-[#7c8cff]">.</span>
                        </div>
                        <div className="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            <InfoTile label="Endpoint" value={monitor.targetLabel} hint="Configured target for this check" />
                            <InfoTile label="Observed host" value={monitor.domainSsl.host} hint="Resolved host currently being evaluated" />
                            <InfoTile label="Registrar" value={monitor.domainSsl.domainRegistrar} hint={monitor.domainSsl.domainCheckedAt} />
                            <InfoTile label="Domain expiry" value={monitor.domainSsl.domainValidUntil} hint={monitor.domainSsl.domainDaysRemaining} />
                            <InfoTile label="Domain refresh" value={monitor.domainSsl.domainCheckedAt} hint={`Collected on a ${monitor.checkedEveryLabel} cadence`} />
                            <InfoTile label="Certificate issuer" value={monitor.domainSsl.issuer} hint="Issuer on the latest successful TLS fetch" />
                            <InfoTile label="Certificate expiry" value={monitor.domainSsl.sslValidUntil} hint={monitor.domainSsl.sslDaysRemaining} />
                            <InfoTile label="TLS refresh" value={monitor.domainSsl.sslCheckedAt} hint="Certificate metadata refresh status" />
                        </div>
                    </PageCard>

                    <Deferred
                        data="monitorHistory"
                        fallback={
                            <>
                                <LoadingPanel title="Historical reliability." description="Loading longer-range uptime summaries…" />
                                <LoadingPanel title="Latency profile." description="Loading response-time history…" />
                            </>
                        }
                    >
                        {monitorHistory ? (
                            <>
                                <PageCard className="grid gap-5 p-6 xl:grid-cols-[1fr_1fr_1fr_200px_200px]">
                                    <StatBlock
                                        label="Last 30 days"
                                        value={monitorHistory.last30Days.uptimeLabel}
                                        subtext={`${monitorHistory.last30Days.incidentsCount} incidents, ${monitorHistory.last30Days.downtimeLabel}`}
                                        valueClassName="text-white"
                                    />
                                    <StatBlock
                                        label="Last 365 days"
                                        value={monitorHistory.last365Days.uptimeLabel}
                                        subtext={`${monitorHistory.last365Days.incidentsCount} incidents, ${monitorHistory.last365Days.downtimeLabel}`}
                                        valueClassName="text-white"
                                    />
                                    <StatBlock
                                        label="Last 14 days"
                                        value={monitorHistory.customRange.uptimeLabel}
                                        subtext={`${monitorHistory.customRange.incidentsCount} incidents, ${monitorHistory.customRange.downtimeLabel}`}
                                        valueClassName="text-white"
                                    />
                                    <div>
                                        <div className="text-[15px] uppercase tracking-[0.18em] text-[#8e9aac]">MTBF</div>
                                        <div className="mt-3 text-[38px] font-semibold tracking-[-0.06em] text-[#57c7c2] lg:text-[42px]">
                                            {monitorHistory.mtbf}
                                        </div>
                                        <div className="mt-2 text-[15px] text-[#9ca7b9] lg:text-[16px]">Last 7 days</div>
                                    </div>
                                    <div className="rounded-[20px] border border-[#2b3544] bg-[#121821] px-4 py-4">
                                        <div className="text-[15px] uppercase tracking-[0.18em] text-[#8e9aac]">Current mix</div>
                                        <div className="mt-4 flex items-center gap-3">
                                            <StatusChip status={monitor.status} />
                                            <div>
                                                <div className="text-[18px] font-semibold text-white">{monitor.statusLabel}</div>
                                                <div className="text-[14px] text-[#9ca7b9]">{monitor.checkedEveryLabel}</div>
                                            </div>
                                        </div>
                                    </div>
                                </PageCard>

                                <PageCard className="p-6">
                                    <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <h2 className="text-[26px] font-semibold tracking-[-0.05em] text-white lg:text-[28px]">
                                                Latency profile<span className="text-[#7c8cff]">.</span>
                                            </h2>
                                        </div>
                                        <div className="flex flex-wrap items-center gap-3">
                                            <label className="inline-flex items-center gap-3 rounded-[14px] border border-[#2b3544] bg-[#171d28] px-3 py-2 text-sm text-[#d5def3]">
                                                <span className="text-[#8fa0bf]">Window</span>
                                                <select
                                                    value={monitorHistory.responseTimeRange}
                                                    onChange={updateResponseRange}
                                                    className="bg-transparent text-sm text-white outline-none"
                                                >
                                                    {monitorHistory.responseTimeRangeOptions.map((option) => (
                                                        <option key={option.value} value={option.value} className="bg-[#171d28]">
                                                            {option.label}
                                                        </option>
                                                    ))}
                                                </select>
                                            </label>
                                            <label className="inline-flex items-center gap-3 rounded-[14px] border border-[#2b3544] bg-[#171d28] px-3 py-2 text-sm text-[#d5def3]">
                                                <span className="text-[#8fa0bf]">Bucket</span>
                                                <select
                                                    value={monitorHistory.responseTimeGranularity}
                                                    onChange={updateResponseGranularity}
                                                    className="bg-transparent text-sm text-white outline-none"
                                                >
                                                    {monitorHistory.responseTimeGranularityOptions.map((option) => (
                                                        <option key={option.value} value={option.value} className="bg-[#171d28]">
                                                            {option.label}
                                                        </option>
                                                    ))}
                                                </select>
                                            </label>
                                        </div>
                                    </div>

                                    <ResponseTimeChart points={monitorHistory.responseTimeChart} />

                                    <div className="mt-7 grid gap-5 md:grid-cols-2 xl:grid-cols-5">
                                        <MetricPanel
                                            title="Average"
                                            value={formatMilliseconds(monitorHistory.responseTimeStats.average)}
                                            caption={`Across ${monitorHistory.responseTimeRangeLabel.toLowerCase()} in ${monitorHistory.responseTimeGranularityLabel.toLowerCase()} buckets`}
                                        />
                                        <MetricPanel
                                            title="Median"
                                            value={formatMilliseconds(monitorHistory.responseTimeStats.median)}
                                            caption={`Middle response across ${monitorHistory.responseTimeRangeLabel.toLowerCase()}`}
                                        />
                                        <MetricPanel
                                            title="Minimum"
                                            value={formatMilliseconds(monitorHistory.responseTimeStats.minimum)}
                                            caption={`Best observed in ${monitorHistory.responseTimeRangeLabel.toLowerCase()}`}
                                        />
                                        <MetricPanel
                                            title="Maximum"
                                            value={formatMilliseconds(monitorHistory.responseTimeStats.maximum)}
                                            caption={`Slowest observed in ${monitorHistory.responseTimeRangeLabel.toLowerCase()}`}
                                        />
                                        <MetricPanel
                                            title="95th percentile"
                                            value={formatMilliseconds(monitorHistory.responseTimeStats.p95)}
                                            caption={`Tail latency for ${monitorHistory.responseTimeRangeLabel.toLowerCase()}`}
                                        />
                                    </div>

                                    <div className="mt-7 grid gap-4 border-t border-white/8 pt-6 md:grid-cols-2 xl:grid-cols-4">
                                        <MetricPanel
                                            title="Samples"
                                            value={monitorHistory.responseTimeSignals.sampleCount.toString()}
                                            caption={`Checks captured in ${monitorHistory.responseTimeRangeLabel.toLowerCase()}`}
                                        />
                                        <MetricPanel
                                            title="Failed checks"
                                            value={monitorHistory.responseTimeSignals.failedChecks.toString()}
                                            caption="Down results in the selected range"
                                        />
                                        <MetricPanel
                                            title="Slow checks"
                                            value={monitorHistory.responseTimeSignals.slowChecks.toString()}
                                            caption="Checks flagged above the latency threshold"
                                        />
                                        <MetricPanel
                                            title="Success rate"
                                            value={formatPercentage(monitorHistory.responseTimeSignals.successRate)}
                                            caption="Healthy checks versus total samples"
                                        />
                                        <MetricPanel
                                            title="Downtime"
                                            value={monitorHistory.responseTimeStats.downtimeLabel}
                                            caption={`Total downtime in ${monitorHistory.responseTimeRangeLabel.toLowerCase()}`}
                                        />
                                    </div>
                                </PageCard>
                            </>
                        ) : null}
                    </Deferred>

                    <PageCard className="p-6">
                        <div className="flex items-center justify-between gap-3">
                            <h2 className="text-[24px] font-semibold tracking-[-0.05em] text-white lg:text-[26px]">
                                Recent events<span className="text-[#7c8cff]">.</span>
                            </h2>
                            <Link href="/incidents" className="text-[15px] text-[#57c7c2] lg:text-[16px]">
                                View event log
                            </Link>
                        </div>
                        <div className="mt-5 space-y-4">
                            {monitor.recentIncidents.length === 0 ? (
                                <div className="rounded-[18px] border border-dashed border-[#2b3544] bg-[#171d28] px-5 py-6 text-[15px] text-[#9ca7b9] lg:text-[16px]">
                                    No incidents recorded for this check.
                                </div>
                            ) : (
                                monitor.recentIncidents.map((incident) => (
                                    <Link
                                        key={incident.id}
                                        href={incident.showUrl}
                                        className="block rounded-[18px] border border-[#2b3544] bg-[#171d28] px-4 py-4 transition hover:border-[#57c7c2]/25 hover:bg-[#1b2330]"
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-3 text-[15px] text-[#dfe8fb] lg:text-[16px]">
                                            <div className="flex flex-wrap items-center gap-3">
                                                <span>{incident.reason}</span>
                                                <span className="rounded-full bg-[#121821] px-3 py-1 text-[12px] text-[#a7b6cb]">
                                                    {incident.typeLabel}
                                                </span>
                                                <span className="rounded-full bg-[#2a1818] px-3 py-1 text-[12px] text-[#ffd4d7]">
                                                    {incident.severityLabel}
                                                </span>
                                            </div>
                                            <div className="text-[#9ca7b9]">{incident.duration}</div>
                                        </div>
                                        <div className="mt-2 text-[16px] text-[#9ca7b9]">
                                            {incident.startedAt} to {incident.endedAt} • {incident.statusLabel}
                                        </div>
                                    </Link>
                                ))
                            )}
                        </div>
                    </PageCard>

                    {monitor.heartbeatUrl ? (
                        <PageCard className="p-6">
                            <div className="flex items-center justify-between gap-3">
                                <h2 className="text-[24px] font-semibold tracking-[-0.05em] text-white lg:text-[26px]">
                                    Heartbeat endpoint<span className="text-[#7c8cff]">.</span>
                                </h2>
                                <button
                                    type="button"
                                    className="inline-flex items-center gap-2 rounded-[14px] border border-[#2b3544] bg-[#171d28] px-4 py-2 text-sm text-[#dce6fb]"
                                    onClick={() => {
                                        if (monitor.heartbeatUrl) {
                                            void navigator.clipboard.writeText(monitor.heartbeatUrl);
                                        }
                                    }}
                                >
                                    <Copy className="size-4" />
                                    Copy endpoint
                                </button>
                            </div>
                            <div className="mt-5 rounded-[18px] bg-[#171d28] px-4 py-3.5">
                                <div className="text-xs uppercase tracking-[0.22em] text-[#7f8eab]">POST URL</div>
                                <code className="mt-3 block break-all text-[15px] text-[#dce6fb]">{monitor.heartbeatUrl}</code>
                            </div>
                            <div className="mt-5 grid gap-4 md:grid-cols-2">
                                <div className="rounded-[18px] bg-[#171d28] px-4 py-3.5">
                                    <div className="text-sm text-[#9ca7b9]">Last heartbeat</div>
                                    <div className="mt-2 text-[20px] font-semibold text-white">{monitor.lastHeartbeatLabel}</div>
                                </div>
                                <div className="rounded-[18px] bg-[#171d28] px-4 py-3.5">
                                    <div className="text-sm text-[#9ca7b9]">Next expected deadline</div>
                                    <div className="mt-2 text-[20px] font-semibold text-white">
                                        {monitor.nextHeartbeatDeadlineLabel ?? 'Waiting for first ping'}
                                    </div>
                                </div>
                            </div>
                            <div className="mt-5 rounded-[18px] border border-[#2b3544] bg-[#121821] px-4 py-3.5 text-[14px] text-[#9eacc7]">
                                Send a `POST` request from your cron job or worker after it completes:
                                <code className="mt-2 block break-all text-[#dce6fb]">{`curl -X POST "${monitor.heartbeatUrl}"`}</code>
                            </div>
                        </PageCard>
                    ) : null}
                </section>

                <aside className="space-y-4">
                    <PageCard className="p-6">
                        <div className="flex items-center justify-between gap-3">
                            <div className="text-[17px] font-semibold text-white">
                                Change window<span className="text-[#7c8cff]">.</span>
                            </div>
                            <CalendarDays className="size-4 text-[#7d8aa7]" />
                        </div>
                        <div className="mt-5 text-[15px] text-[#9ca7b9] lg:text-[16px]">{monitor.nextMaintenance}</div>
                        <div className="mt-5 flex gap-3">
                            <button
                                type="button"
                                className="inline-flex flex-1 items-center justify-center rounded-[16px] border border-[#2b3544] bg-[#171d28] px-5 py-3 text-base text-white"
                                onClick={() => setShowMaintenanceForm((current) => !current)}
                            >
                                {showMaintenanceForm ? 'Hide form' : 'Set up maintenance'}
                            </button>
                            <Link
                                href={`/maintenance?monitor_id=${monitor.id}`}
                                className="inline-flex items-center justify-center rounded-[16px] border border-[#2b3544] px-4 py-3 text-sm text-[#d5def3]"
                            >
                                Manage all
                            </Link>
                        </div>
                        {showMaintenanceForm ? (
                            <form
                                className="mt-5 space-y-4 rounded-[18px] bg-[#171d28] px-4 py-3.5"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    maintenanceForm.post('/maintenance-windows', {
                                        preserveScroll: true,
                                        onSuccess: () => {
                                            maintenanceForm.reset();
                                            setShowMaintenanceForm(false);
                                        },
                                    });
                                }}
                            >
                                {Object.values(maintenanceForm.errors).length > 0 ? (
                                    <div className="rounded-[14px] border border-[#ff7a72]/20 bg-[#2a1818] px-3 py-3 text-sm text-[#ffd4d7]">
                                        {Object.values(maintenanceForm.errors).join(' ')}
                                    </div>
                                ) : null}
                                <label className="block space-y-2">
                                    <span className="text-sm text-[#dce6fb]">Title</span>
                                    <input
                                        value={maintenanceForm.data.title}
                                        onChange={(event) => maintenanceForm.setData('title', event.target.value)}
                                        className="h-10 w-full rounded-[14px] border border-white/10 bg-[#0b1425] px-3 text-sm text-white outline-none"
                                        placeholder={`Maintenance for ${monitor.name}`}
                                    />
                                </label>
                                <div className="grid gap-3">
                                    <label className="block space-y-2">
                                        <span className="text-sm text-[#dce6fb]">Starts at</span>
                                        <input
                                            type="datetime-local"
                                            value={maintenanceForm.data.starts_at}
                                            onChange={(event) => maintenanceForm.setData('starts_at', event.target.value)}
                                            className="h-10 w-full rounded-[14px] border border-white/10 bg-[#0b1425] px-3 text-sm text-white outline-none"
                                        />
                                    </label>
                                    <label className="block space-y-2">
                                        <span className="text-sm text-[#dce6fb]">Ends at</span>
                                        <input
                                            type="datetime-local"
                                            value={maintenanceForm.data.ends_at}
                                            onChange={(event) => maintenanceForm.setData('ends_at', event.target.value)}
                                            className="h-10 w-full rounded-[14px] border border-white/10 bg-[#0b1425] px-3 text-sm text-white outline-none"
                                        />
                                    </label>
                                </div>
                                <label className="block space-y-2">
                                    <span className="text-sm text-[#dce6fb]">Message</span>
                                    <textarea
                                        value={maintenanceForm.data.message}
                                        onChange={(event) => maintenanceForm.setData('message', event.target.value)}
                                        className="min-h-[88px] w-full rounded-[14px] border border-white/10 bg-[#0b1425] px-3 py-3 text-sm text-white outline-none"
                                    />
                                </label>
                                <label className="flex items-center gap-3 rounded-[14px] bg-[#0d1628] px-3 py-3 text-sm text-[#dce6fb]">
                                    <input
                                        type="checkbox"
                                        checked={maintenanceForm.data.notify_contacts}
                                        onChange={(event) => maintenanceForm.setData('notify_contacts', event.target.checked)}
                                        className="size-4 rounded border-white/15 bg-[#091426]"
                                    />
                                    Notify email contacts
                                </label>
                                <button
                                    type="submit"
                                    className="inline-flex w-full items-center justify-center rounded-[14px] bg-[#7c8cff] px-4 py-2.5 text-sm font-medium text-white"
                                >
                                    Schedule maintenance
                                </button>
                            </form>
                        ) : null}
                        {monitor.maintenanceWindows.length > 0 ? (
                            <div className="mt-5 space-y-3">
                                {monitor.maintenanceWindows.map((window) => (
                                    <div key={window.id} className="rounded-[16px] bg-[#171d28] px-4 py-3.5">
                                        <div className="flex items-center justify-between gap-3 text-[14px] text-white">
                                            <span>{window.title}</span>
                                            <span className="text-[#57c7c2]">{window.status}</span>
                                        </div>
                                        <div className="mt-2 text-[13px] text-[#7f8eab]">
                                            {window.startsAt} to {window.endsAt}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : null}
                    </PageCard>

                    <PageCard className="p-6">
                        <div className="text-[17px] font-semibold text-white">
                            Coverage region<span className="text-[#7c8cff]">.</span>
                        </div>
                        <div className="mt-5 rounded-[22px] bg-[#171d28] p-4">
                            <RegionMap region={monitor.region} />
                        </div>
                        <div className="mt-4 text-[30px] font-semibold tracking-[-0.05em] text-white lg:text-[34px]">
                            {monitor.region}
                        </div>
                    </PageCard>

                    <PageCard className="p-6">
                        <div className="text-[17px] font-semibold text-white">
                            Published status<span className="text-[#7c8cff]">.</span>
                        </div>
                        <div className="mt-5 space-y-3">
                            {monitor.statusPages.length === 0 ? (
                                <div className="text-[15px] text-[#9ca7b9]">This check is not currently published on any status page.</div>
                            ) : (
                                monitor.statusPages.map((page) => (
                                    <a
                                        key={page.slug}
                                        href={page.publicUrl}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="block rounded-[16px] bg-[#171d28] px-4 py-4 transition hover:bg-[#1b2330]"
                                    >
                                        <div className="flex items-center justify-between gap-3 text-[15px] text-white">
                                            <span>{page.name}</span>
                                            <ExternalLink className="size-4 text-[#7f8eab]" />
                                        </div>
                                        <div className="mt-1 text-[13px] text-[#7f8eab]">
                                            {page.published ? 'Published' : 'Draft'}
                                        </div>
                                    </a>
                                ))
                            )}
                        </div>
                    </PageCard>

                    <PageCard className="p-6">
                        <div className="flex items-center justify-between gap-3">
                            <div className="text-[17px] font-semibold text-white">
                                Delivery log<span className="text-[#7c8cff]">.</span>
                            </div>
                            <ShieldCheck className="size-4 text-[#7d8aa7]" />
                        </div>
                        <div className="mt-5 space-y-4">
                            {monitor.notificationLog.length === 0 ? (
                                <div className="text-[15px] text-[#9ca7b9] lg:text-[16px]">No delivery attempts have been recorded yet.</div>
                            ) : (
                                monitor.notificationLog.map((entry) => (
                                    <div key={`${entry.time}-${entry.subject}`} className="rounded-[18px] bg-[#171d28] px-4 py-4">
                                        <div className="flex items-center justify-between gap-3 text-[15px] text-white">
                                            <span>{entry.type}</span>
                                            <span className="text-[#57c7c2]">{entry.status}</span>
                                        </div>
                                        <div className="mt-2 flex items-center gap-2 text-[12px] uppercase tracking-[0.18em] text-[#7f8eab]">
                                            <span>{entry.channel}</span>
                                        </div>
                                        <div className="mt-2 text-[14px] text-[#8fa0bf]">{entry.subject}</div>
                                        {entry.recipient ? <div className="mt-1 text-sm text-[#667590]">{entry.recipient}</div> : null}
                                        <div className="mt-1 text-sm text-[#667590]">{entry.time}</div>
                                    </div>
                                ))
                            )}
                        </div>
                    </PageCard>

                    <PageCard className="p-6">
                        <div className="text-[17px] font-semibold text-white">
                            Destructive actions<span className="text-[#7c8cff]">.</span>
                        </div>
                        <div className="mt-3 text-[14px] text-[#9ca7b9]">
                            Deleting this check removes its checks, incidents, and notification history.
                        </div>
                        <button
                            type="button"
                            className="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-[16px] border border-[#ff7a72]/25 bg-[#231817] px-5 py-3 text-sm font-medium text-[#ffd4d7]"
                            onClick={() => {
                                if (window.confirm(`Delete monitor "${monitor.name}"? This cannot be undone.`)) {
                                    router.delete(`/monitors/${monitor.id}`);
                                }
                            }}
                        >
                            <Trash2 className="size-4" />
                            Delete check
                        </button>
                    </PageCard>
                </aside>
            </div>
        </MonitoringLayout>
    );
}

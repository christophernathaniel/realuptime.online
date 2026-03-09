import { Head, Link, router, useForm } from '@inertiajs/react';
import { BellRing, CalendarDays, Copy, ExternalLink, Pause, Pencil, Play, ShieldCheck, Trash2 } from 'lucide-react';
import type { ChangeEvent } from 'react';
import { useState } from 'react';
import { PageCard } from '@/components/monitoring/page-card';
import { RegionMap } from '@/components/monitoring/region-map';
import { ResponseTimeChart } from '@/components/monitoring/response-time-chart';
import { StatusChip } from '@/components/monitoring/status-chip';
import { UptimeBars } from '@/components/monitoring/uptime-bars';
import { usePageAutoRefresh } from '@/hooks/use-page-auto-refresh';
import MonitoringLayout from '@/layouts/monitoring-layout';
import type { DetailedMonitor, MaintenanceFormData } from '@/types/monitoring';

type MonitorShowProps = {
    monitor: DetailedMonitor;
};

function StatBlock({ label, value, subtext }: { label: string; value: string; subtext: string }) {
    return (
        <div>
            <div className="text-[17px] text-[#cfd8ec]">{label}</div>
            <div className="mt-3 text-[38px] font-semibold tracking-[-0.06em] text-[#3ee072] lg:text-[42px]">{value}</div>
            <div className="mt-2 text-[15px] text-[#8fa0bf] lg:text-[16px]">{subtext}</div>
        </div>
    );
}

function MetricPanel({ title, value, caption }: { title: string; value: string; caption: string }) {
    return (
        <div>
            <div className="text-[16px] text-[#cfd8ec]">{title}</div>
            <div className="mt-2 text-[30px] font-semibold tracking-[-0.05em] text-white lg:text-[34px]">{value}</div>
            <div className="text-[15px] text-[#8fa0bf] lg:text-[16px]">{caption}</div>
        </div>
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

export default function MonitorShow({ monitor }: MonitorShowProps) {
    const [showMaintenanceForm, setShowMaintenanceForm] = useState(false);
    const maintenanceForm = useForm<MaintenanceFormData>(monitor.maintenanceDefaults);

    usePageAutoRefresh({ only: ['monitor'] });

    const updateResponseRange = (event: ChangeEvent<HTMLSelectElement>) => {
        router.get(
            `/monitors/${monitor.id}`,
            {
                response_range: event.target.value,
                response_granularity: monitor.responseTimeGranularity,
            },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    const updateResponseGranularity = (event: ChangeEvent<HTMLSelectElement>) => {
        router.get(
            `/monitors/${monitor.id}`,
            {
                response_range: monitor.responseTimeRange,
                response_granularity: event.target.value,
            },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    return (
        <MonitoringLayout>
            <Head title={monitor.name} />
            <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_308px]">
                <section className="space-y-5">
                    <div className="flex flex-wrap items-center gap-4">
                        <Link
                            href="/monitors"
                            className="inline-flex items-center gap-3 rounded-[16px] border border-white/6 bg-[#1a2339]/95 px-4 py-2.5 text-base text-[#d5def3]"
                        >
                            Monitoring
                        </Link>
                    </div>

                    <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                        <div className="flex items-start gap-5">
                            <StatusChip status={monitor.status} large />
                            <div>
                                <h1 className="text-[40px] font-semibold tracking-[-0.06em] text-white lg:text-[48px]">
                                    {monitor.name}
                                </h1>
                                <div className="mt-2 flex flex-wrap items-center gap-3 text-[16px] text-[#9eacc7] lg:text-[18px]">
                                    <span>{monitor.typeLabel} for</span>
                                    {monitor.heartbeatUrl ? (
                                        <span className="text-[#dbe4f9]">{monitor.targetLabel}</span>
                                    ) : monitor.target ? (
                                        <a
                                            href={monitor.target}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex items-center gap-2 text-[#3ee072] underline-offset-4 hover:underline"
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

                        <div className="flex flex-wrap gap-3">
                            <button
                                type="button"
                                className="inline-flex items-center gap-3 rounded-[16px] border border-white/6 bg-[#1a2339]/95 px-4 py-2.5 text-base text-[#d5def3]"
                                onClick={() => router.post(`/monitors/${monitor.id}/run-now`)}
                            >
                                <Play className="size-4 text-[#8fa0bf]" />
                                Run check
                            </button>
                            <button
                                type="button"
                                className="inline-flex items-center gap-3 rounded-[16px] border border-white/6 bg-[#1a2339]/95 px-4 py-2.5 text-base text-[#d5def3]"
                                onClick={() => router.post(`/monitors/${monitor.id}/test-notification`)}
                            >
                                <BellRing className="size-4 text-[#8fa0bf]" />
                                Test Notification
                            </button>
                            <button
                                type="button"
                                className="inline-flex items-center gap-3 rounded-[16px] border border-white/6 bg-[#1a2339]/95 px-4 py-2.5 text-base text-[#d5def3]"
                                onClick={() => router.post(`/monitors/${monitor.id}/toggle`)}
                            >
                                <Pause className="size-4 text-[#8fa0bf]" />
                                {monitor.status === 'paused' ? 'Resume' : 'Pause'}
                            </button>
                            <Link
                                href={`/monitors/${monitor.id}/edit`}
                                className="inline-flex items-center gap-3 rounded-[16px] border border-white/6 bg-[#1a2339]/95 px-4 py-2.5 text-base text-[#d5def3]"
                            >
                                <Pencil className="size-4 text-[#8fa0bf]" />
                                Edit
                            </Link>
                        </div>
                    </div>

                    <div className="grid gap-4 xl:grid-cols-[repeat(4,minmax(0,1fr))_minmax(260px,1.35fr)]">
                        <PageCard className="p-6 xl:col-span-1">
                            <div className="text-[17px] text-[#d5def3]">Current status</div>
                            <div className="mt-3 text-[38px] font-semibold tracking-[-0.06em] text-[#3ee072] lg:text-[42px]">
                                {monitor.currentStatusLabel}
                            </div>
                            <div className="mt-2 text-[15px] text-[#8fa0bf] lg:text-[16px]">{monitor.currentStatusDurationLabel}</div>
                        </PageCard>

                        <PageCard className="p-6 xl:col-span-1">
                            <div className="text-[17px] text-[#d5def3]">Last check</div>
                            <div className="mt-3 text-[38px] font-semibold tracking-[-0.06em] text-white lg:text-[42px]">
                                {monitor.lastCheckLabel}
                            </div>
                            <div className="mt-2 text-[15px] text-[#8fa0bf] lg:text-[16px]">Checked every {monitor.checkedEveryLabel}</div>
                        </PageCard>

                        <PageCard className="p-6 xl:col-span-1">
                            <div className="flex items-center justify-between gap-3">
                                <div className="text-[17px] text-[#d5def3]">Last 24 hours</div>
                                <div className="text-[20px] font-semibold text-white">{monitor.last24Stats.uptimeLabel}</div>
                            </div>
                            <div className="mt-6">
                                <UptimeBars bars={monitor.last24Bars} />
                            </div>
                            <div className="mt-4 text-[15px] text-[#8fa0bf] lg:text-[16px]">
                                {monitor.last24Stats.incidentsCount} incidents, {monitor.last24Stats.downtimeLabel}
                            </div>
                        </PageCard>

                        <PageCard className="p-6 xl:col-span-1">
                            <div className="flex items-center justify-between gap-3">
                                <div className="text-[17px] text-[#d5def3]">Last 7 days</div>
                                <div className="text-[20px] font-semibold text-white">{monitor.last7Days.uptimeLabel}</div>
                            </div>
                            <div className="mt-6">
                                <UptimeBars bars={monitor.last7Bars} />
                            </div>
                            <div className="mt-2 text-[13px] text-[#647391]">
                                Four segments per day
                            </div>
                            <div className="mt-4 text-[15px] text-[#8fa0bf] lg:text-[16px]">
                                {monitor.last7Days.incidentsCount} incidents, {monitor.last7Days.downtimeLabel}
                            </div>
                        </PageCard>

                        <PageCard className="p-6 xl:col-span-1">
                            <div className="text-[17px] font-semibold text-white">
                                Domain &amp; SSL<span className="text-[#3ee072]">.</span>
                            </div>
                            <div className="mt-6 space-y-5">
                                <div>
                                    <div className="text-[15px] text-[#8fa0bf] lg:text-[16px]">Domain valid until</div>
                                    <div className="mt-2 text-[19px] font-semibold text-white">{monitor.domainSsl.domainValidUntil}</div>
                                    <div className="mt-1 text-[15px] text-[#8fa0bf]">Registrar: {monitor.domainSsl.domainRegistrar}</div>
                                </div>
                                <div className="border-t border-white/8 pt-5">
                                    <div className="text-[15px] text-[#8fa0bf] lg:text-[16px]">SSL certificate valid until</div>
                                    <div className="mt-2 text-[19px] font-semibold text-white">{monitor.domainSsl.sslValidUntil}</div>
                                    <div className="mt-1 text-[15px] text-[#8fa0bf]">Issuer: {monitor.domainSsl.issuer}</div>
                                </div>
                            </div>
                        </PageCard>
                    </div>

                    <PageCard className="grid gap-5 p-7 xl:grid-cols-[1fr_1fr_1fr_1fr_200px]">
                        <StatBlock
                            label="Current streak"
                            value={monitor.currentStatusDurationValue}
                            subtext={`${monitor.currentStatusLabel} since the last status change`}
                        />
                        <StatBlock
                            label="Last 30 days"
                            value={monitor.last30Days.uptimeLabel}
                            subtext={`${monitor.last30Days.incidentsCount} incidents, ${monitor.last30Days.downtimeLabel}`}
                        />
                        <StatBlock
                            label="Last 365 days"
                            value={monitor.last365Days.uptimeLabel}
                            subtext={`${monitor.last365Days.incidentsCount} incidents, ${monitor.last365Days.downtimeLabel}`}
                        />
                        <StatBlock
                            label="Last 14 days"
                            value={monitor.customRange.uptimeLabel}
                            subtext={`${monitor.customRange.incidentsCount} incidents, ${monitor.customRange.downtimeLabel}`}
                        />
                        <div>
                            <div className="text-[17px] text-[#d5def3]">MTBF</div>
                            <div className="mt-3 text-[38px] font-semibold tracking-[-0.06em] text-[#3ee072] lg:text-[42px]">
                                {monitor.mtbf}
                            </div>
                            <div className="mt-2 text-[15px] text-[#8fa0bf] lg:text-[16px]">Last 7 days</div>
                        </div>
                    </PageCard>

                    <PageCard className="p-7">
                        <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 className="text-[26px] font-semibold tracking-[-0.05em] text-white lg:text-[28px]">
                                    Response time<span className="text-[#3ee072]">.</span>
                                </h2>
                            </div>
                            <div className="flex flex-wrap items-center gap-3">
                                <label className="inline-flex items-center gap-3 rounded-[14px] bg-[#0d1628] px-3 py-2 text-sm text-[#d5def3]">
                                    <span className="text-[#8fa0bf]">Range</span>
                                    <select
                                        value={monitor.responseTimeRange}
                                        onChange={updateResponseRange}
                                        className="bg-transparent text-sm text-white outline-none"
                                    >
                                        {monitor.responseTimeRangeOptions.map((option) => (
                                            <option key={option.value} value={option.value} className="bg-[#0d1628]">
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <label className="inline-flex items-center gap-3 rounded-[14px] bg-[#0d1628] px-3 py-2 text-sm text-[#d5def3]">
                                    <span className="text-[#8fa0bf]">Granularity</span>
                                    <select
                                        value={monitor.responseTimeGranularity}
                                        onChange={updateResponseGranularity}
                                        className="bg-transparent text-sm text-white outline-none"
                                    >
                                        {monitor.responseTimeGranularityOptions.map((option) => (
                                            <option key={option.value} value={option.value} className="bg-[#0d1628]">
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                            </div>
                        </div>

                        <ResponseTimeChart points={monitor.responseTimeChart} />

                        <div className="mt-7 grid gap-5 md:grid-cols-2 xl:grid-cols-5">
                            <MetricPanel
                                title="Average"
                                value={formatMilliseconds(monitor.responseTimeStats.average)}
                                caption={`Across ${monitor.responseTimeRangeLabel.toLowerCase()} in ${monitor.responseTimeGranularityLabel.toLowerCase()} buckets`}
                            />
                            <MetricPanel
                                title="Median"
                                value={formatMilliseconds(monitor.responseTimeStats.median)}
                                caption={`Middle response across ${monitor.responseTimeRangeLabel.toLowerCase()}`}
                            />
                            <MetricPanel
                                title="Minimum"
                                value={formatMilliseconds(monitor.responseTimeStats.minimum)}
                                caption={`Best observed in ${monitor.responseTimeRangeLabel.toLowerCase()}`}
                            />
                            <MetricPanel
                                title="Maximum"
                                value={formatMilliseconds(monitor.responseTimeStats.maximum)}
                                caption={`Slowest observed in ${monitor.responseTimeRangeLabel.toLowerCase()}`}
                            />
                            <MetricPanel
                                title="95th percentile"
                                value={formatMilliseconds(monitor.responseTimeStats.p95)}
                                caption={`Tail latency for ${monitor.responseTimeRangeLabel.toLowerCase()}`}
                            />
                        </div>

                        <div className="mt-7 grid gap-4 border-t border-white/8 pt-6 md:grid-cols-2 xl:grid-cols-4">
                            <MetricPanel
                                title="Samples"
                                value={monitor.responseTimeSignals.sampleCount.toString()}
                                caption={`Checks captured in ${monitor.responseTimeRangeLabel.toLowerCase()}`}
                            />
                            <MetricPanel
                                title="Failed checks"
                                value={monitor.responseTimeSignals.failedChecks.toString()}
                                caption="Down results in the selected range"
                            />
                            <MetricPanel
                                title="Slow checks"
                                value={monitor.responseTimeSignals.slowChecks.toString()}
                                caption="Checks flagged above the latency threshold"
                            />
                            <MetricPanel
                                title="Success rate"
                                value={formatPercentage(monitor.responseTimeSignals.successRate)}
                                caption="Healthy checks versus total samples"
                            />
                        </div>
                    </PageCard>

                    <PageCard className="p-7">
                        <div className="flex items-center justify-between gap-3">
                            <h2 className="text-[24px] font-semibold tracking-[-0.05em] text-white lg:text-[26px]">
                                Incident timeline<span className="text-[#3ee072]">.</span>
                            </h2>
                            <Link href="/incidents" className="text-[15px] text-[#3ee072] lg:text-[16px]">
                                View all incidents
                            </Link>
                        </div>
                        <div className="mt-5 space-y-4">
                            {monitor.recentIncidents.length === 0 ? (
                                <div className="rounded-[18px] border border-dashed border-white/10 bg-[#111a2c] px-5 py-6 text-[15px] text-[#8fa0bf] lg:text-[16px]">
                                    No incidents recorded for this monitor.
                                </div>
                            ) : (
                                monitor.recentIncidents.map((incident) => (
                                    <Link
                                        key={incident.id}
                                        href={incident.showUrl}
                                        className="block rounded-[18px] border border-white/6 bg-[#111a2c] px-5 py-5 transition hover:border-[#3ee072]/25 hover:bg-[#152039]"
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-3 text-[15px] text-[#dfe8fb] lg:text-[16px]">
                                            <div className="flex flex-wrap items-center gap-3">
                                                <span>{incident.reason}</span>
                                                <span className="rounded-full bg-[#0d1628] px-3 py-1 text-[12px] text-[#9bb4ff]">
                                                    {incident.typeLabel}
                                                </span>
                                                <span className="rounded-full bg-[#261826] px-3 py-1 text-[12px] text-[#ffd4d7]">
                                                    {incident.severityLabel}
                                                </span>
                                            </div>
                                            <div className="text-[#8fa0bf]">{incident.duration}</div>
                                        </div>
                                        <div className="mt-2 text-[16px] text-[#8fa0bf]">
                                            {incident.startedAt} to {incident.endedAt} • {incident.statusLabel}
                                        </div>
                                    </Link>
                                ))
                            )}
                        </div>
                    </PageCard>

                    {monitor.heartbeatUrl ? (
                        <PageCard className="p-7">
                            <div className="flex items-center justify-between gap-3">
                                <h2 className="text-[24px] font-semibold tracking-[-0.05em] text-white lg:text-[26px]">
                                    Heartbeat endpoint<span className="text-[#3ee072]">.</span>
                                </h2>
                                <button
                                    type="button"
                                    className="inline-flex items-center gap-2 rounded-[14px] bg-[#0d1628] px-4 py-2 text-sm text-[#dce6fb]"
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
                            <div className="mt-5 rounded-[18px] bg-[#111a2c] px-5 py-4">
                                <div className="text-xs uppercase tracking-[0.22em] text-[#7f8eab]">POST URL</div>
                                <code className="mt-3 block break-all text-[15px] text-[#dce6fb]">{monitor.heartbeatUrl}</code>
                            </div>
                            <div className="mt-5 grid gap-4 md:grid-cols-2">
                                <div className="rounded-[18px] bg-[#111a2c] px-5 py-4">
                                    <div className="text-sm text-[#8fa0bf]">Last heartbeat</div>
                                    <div className="mt-2 text-[20px] font-semibold text-white">{monitor.lastHeartbeatLabel}</div>
                                </div>
                                <div className="rounded-[18px] bg-[#111a2c] px-5 py-4">
                                    <div className="text-sm text-[#8fa0bf]">Next expected deadline</div>
                                    <div className="mt-2 text-[20px] font-semibold text-white">
                                        {monitor.nextHeartbeatDeadlineLabel ?? 'Waiting for first ping'}
                                    </div>
                                </div>
                            </div>
                            <div className="mt-5 rounded-[18px] border border-white/6 bg-[#0d1628] px-5 py-4 text-[14px] text-[#9eacc7]">
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
                                Next maintenance<span className="text-[#3ee072]">.</span>
                            </div>
                            <CalendarDays className="size-4 text-[#7d8aa7]" />
                        </div>
                        <div className="mt-5 text-[15px] text-[#8fa0bf] lg:text-[16px]">{monitor.nextMaintenance}</div>
                        <div className="mt-5 flex gap-3">
                            <button
                                type="button"
                                className="inline-flex flex-1 items-center justify-center rounded-[16px] bg-[#0d1628] px-5 py-3 text-base text-white"
                                onClick={() => setShowMaintenanceForm((current) => !current)}
                            >
                                {showMaintenanceForm ? 'Hide form' : 'Set up maintenance'}
                            </button>
                            <Link
                                href={`/maintenance?monitor_id=${monitor.id}`}
                                className="inline-flex items-center justify-center rounded-[16px] border border-white/8 px-4 py-3 text-sm text-[#d5def3]"
                            >
                                Manage all
                            </Link>
                        </div>
                        {showMaintenanceForm ? (
                            <form
                                className="mt-5 space-y-4 rounded-[18px] bg-[#111a2c] px-4 py-4"
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
                                    <div className="rounded-[14px] border border-[#ff6269]/20 bg-[#2a1621] px-3 py-3 text-sm text-[#ffd4d7]">
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
                                    className="inline-flex w-full items-center justify-center rounded-[14px] bg-[#352ef6] px-4 py-2.5 text-sm font-medium text-white"
                                >
                                    Schedule maintenance
                                </button>
                            </form>
                        ) : null}
                        {monitor.maintenanceWindows.length > 0 ? (
                            <div className="mt-5 space-y-3">
                                {monitor.maintenanceWindows.map((window) => (
                                    <div key={window.id} className="rounded-[16px] bg-[#111a2c] px-4 py-4">
                                        <div className="flex items-center justify-between gap-3 text-[14px] text-white">
                                            <span>{window.title}</span>
                                            <span className="text-[#9bb4ff]">{window.status}</span>
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
                            Regions<span className="text-[#3ee072]">.</span>
                        </div>
                        <div className="mt-5 rounded-[22px] bg-[#121c30] p-4">
                            <RegionMap region={monitor.region} />
                        </div>
                        <div className="mt-4 text-[30px] font-semibold tracking-[-0.05em] text-white lg:text-[34px]">
                            {monitor.region}
                        </div>
                    </PageCard>

                    <PageCard className="p-6">
                        <div className="text-[17px] font-semibold text-white">
                            Public status pages<span className="text-[#3ee072]">.</span>
                        </div>
                        <div className="mt-5 space-y-3">
                            {monitor.statusPages.length === 0 ? (
                                <div className="text-[15px] text-[#8fa0bf]">This monitor is not currently published on any status page.</div>
                            ) : (
                                monitor.statusPages.map((page) => (
                                    <a
                                        key={page.slug}
                                        href={page.publicUrl}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="block rounded-[16px] bg-[#111a2c] px-4 py-4 transition hover:bg-[#162139]"
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
                                Notification log<span className="text-[#3ee072]">.</span>
                            </div>
                            <ShieldCheck className="size-4 text-[#7d8aa7]" />
                        </div>
                        <div className="mt-5 space-y-4">
                            {monitor.notificationLog.length === 0 ? (
                                <div className="text-[15px] text-[#8fa0bf] lg:text-[16px]">No notification deliveries have been recorded yet.</div>
                            ) : (
                                monitor.notificationLog.map((entry) => (
                                    <div key={`${entry.time}-${entry.subject}`} className="rounded-[18px] bg-[#111a2c] px-4 py-4">
                                        <div className="flex items-center justify-between gap-3 text-[15px] text-white">
                                            <span>{entry.type}</span>
                                            <span className="text-[#3ee072]">{entry.status}</span>
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
                            Danger zone<span className="text-[#3ee072]">.</span>
                        </div>
                        <div className="mt-3 text-[14px] text-[#8fa0bf]">
                            Deleting a monitor removes its checks, incidents, and notification history.
                        </div>
                        <button
                            type="button"
                            className="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-[16px] border border-[#ff6269]/25 bg-[#231320] px-5 py-3 text-sm font-medium text-[#ffd4d7]"
                            onClick={() => {
                                if (window.confirm(`Delete monitor "${monitor.name}"? This cannot be undone.`)) {
                                    router.delete(`/monitors/${monitor.id}`);
                                }
                            }}
                        >
                            <Trash2 className="size-4" />
                            Delete monitor
                        </button>
                    </PageCard>
                </aside>
            </div>
        </MonitoringLayout>
    );
}

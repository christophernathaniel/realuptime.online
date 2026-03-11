import { Deferred, Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowUpDown,
    Filter,
    Plus,
    Search,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { PageCard } from '@/components/monitoring/page-card';
import { StatusChip } from '@/components/monitoring/status-chip';
import { UptimeBars } from '@/components/monitoring/uptime-bars';
import { usePageAutoRefresh } from '@/hooks/use-page-auto-refresh';
import MonitoringLayout from '@/layouts/monitoring-layout';
import { cn } from '@/lib/utils';
import type {
    AggregateWindowStats,
    CapabilityHealth,
    MonitorListItem,
    MonitorSummary,
} from '@/types/monitoring';

type MonitorsIndexProps = {
    summary: MonitorSummary;
    last24Hours: AggregateWindowStats;
    capabilities?: CapabilityHealth[];
    monitors: MonitorListItem[];
};

export default function MonitorsIndex({
    summary,
    last24Hours,
    capabilities,
    monitors,
}: MonitorsIndexProps) {
    const page = usePage<{
        workspace?: {
            current?: {
                membership?: {
                    manageUrl: string | null;
                };
            };
        } | null;
    }>();
    const [search, setSearch] = useState('');
    const [showOnlyDown, setShowOnlyDown] = useState(false);
    const [sortMode, setSortMode] = useState<'down' | 'name'>('down');
    const membershipManageUrl = page.props.workspace?.current?.membership?.manageUrl ?? null;

    usePageAutoRefresh({ only: ['summary', 'last24Hours', 'monitors'] });

    const filtered = useMemo(() => {
        const normalized = search.trim().toLowerCase();

        return [...monitors]
            .filter((monitor) => {
                if (showOnlyDown && monitor.status !== 'down') {
                    return false;
                }

                if (!normalized) {
                    return true;
                }

                return (
                    monitor.name.toLowerCase().includes(normalized) ||
                    (monitor.target ?? '').toLowerCase().includes(normalized)
                );
            })
            .sort((left, right) => {
                if (sortMode === 'name') {
                    return left.name.localeCompare(right.name);
                }

                if (left.status === right.status) {
                    return left.name.localeCompare(right.name);
                }

                if (left.status === 'down') {
                    return -1;
                }

                if (right.status === 'down') {
                    return 1;
                }

                return left.name.localeCompare(right.name);
            });
    }, [monitors, search, showOnlyDown, sortMode]);

    return (
        <MonitoringLayout>
            <Head title="Checks" />
            <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_300px]">
                <section className="space-y-5">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <div className="text-[11px] font-semibold uppercase tracking-[0.26em] text-[#7f8b9b]">
                                Operations workspace
                            </div>
                            <h1 className="mt-2 text-[40px] font-semibold tracking-[-0.06em] text-white lg:text-[46px]">
                                Checks<span className="text-[#7c8cff]">.</span>
                            </h1>
                            <div className="mt-2 max-w-[720px] text-[15px] text-[#9ca7b9]">
                                Live watchlist for websites, TCP services, and ping targets. {summary.usageLabel}
                            </div>
                        </div>

                        <div className="flex items-center gap-3 self-start lg:self-auto">
                            {summary.canCreate ? (
                                <Link
                                    href="/monitors/create"
                                    className="inline-flex items-center gap-3 rounded-[16px] bg-[#7c8cff] px-5 py-3 text-base font-medium text-white transition hover:bg-[#95a3ff]"
                                >
                                    <Plus className="size-[18px]" />
                                    New check
                                </Link>
                            ) : membershipManageUrl ? (
                                <Link
                                    href={membershipManageUrl}
                                    className="inline-flex items-center gap-3 rounded-[16px] border border-[#7c8cff]/25 bg-[#171c33] px-5 py-3 text-base font-medium text-[#dbe1ff] transition hover:border-[#7c8cff]/40"
                                >
                                    <Plus className="size-[18px]" />
                                    Upgrade workspace
                                </Link>
                            ) : (
                                <div className="inline-flex items-center gap-3 rounded-[16px] border border-[#2b3544] bg-[#171d28] px-5 py-3 text-base font-medium text-[#9ca7b9]">
                                    <Plus className="size-[18px]" />
                                    Limit reached
                                </div>
                            )}
                            <button
                                type="button"
                                className="inline-flex size-[48px] items-center justify-center rounded-[16px] border border-[#2b3544] bg-[#171d28] text-[#aab7d4]"
                                onClick={() => setSortMode((current) => (current === 'down' ? 'name' : 'down'))}
                            >
                                <ArrowUpDown className="size-4" />
                            </button>
                        </div>
                    </div>

                    <PageCard className="space-y-4 p-5">
                        <div className="flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
                            <div>
                                <div className="text-[11px] font-semibold uppercase tracking-[0.26em] text-[#7f8b9b]">
                                    Customer-facing capabilities
                                </div>
                                <div className="mt-2 text-[22px] font-semibold tracking-[-0.05em] text-white">
                                    User impact view<span className="text-[#7c8cff]">.</span>
                                </div>
                                <div className="mt-1 text-sm text-[#9ca7b9]">
                                    Group checks into capabilities like sign in, checkout, or webhook delivery so incidents show customer impact, not just raw failures.
                                </div>
                            </div>
                        </div>

                        <Deferred
                            data="capabilities"
                            fallback={
                                <div className="rounded-[18px] border border-[#2b3544] bg-[#121821] px-4 py-4 text-sm text-[#9ca7b9]">
                                    Loading customer impact view…
                                </div>
                            }
                        >
                            {(() => {
                                const capabilityList = capabilities ?? [];

                                if (capabilityList.length === 0) {
                                    return (
                                        <div className="rounded-[18px] border border-[#2b3544] bg-[#121821] px-4 py-4 text-sm text-[#9ca7b9]">
                                            No capabilities have been mapped yet. Add capability labels from a check editor to build an impact-oriented status layer.
                                        </div>
                                    );
                                }

                                return (
                                    <div className="grid gap-4 xl:grid-cols-3">
                                        {capabilityList.slice(0, 6).map((capability) => (
                                            <div key={capability.id} className="rounded-[18px] border border-[#2b3544] bg-[#121821] px-4 py-4">
                                                <div className="flex items-start justify-between gap-3">
                                                    <div>
                                                        <div className="text-[17px] font-semibold text-white">{capability.name}</div>
                                                        <div className="mt-1 text-sm text-[#9ca7b9]">{capability.regions}</div>
                                                    </div>
                                                    <span
                                                        className={cn(
                                                            'rounded-full px-3 py-1 text-xs font-medium',
                                                            capability.tone === 'up' && 'bg-[#57c7c2]/15 text-[#57c7c2]',
                                                            capability.tone === 'down' && 'bg-[#ff7a72]/15 text-[#ffb2ad]',
                                                            capability.tone === 'warning' && 'bg-[#7c8cff]/15 text-[#d0d8ff]',
                                                            capability.tone === 'maintenance' && 'bg-[#7483a5]/15 text-[#bfc9da]',
                                                        )}
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
                                );
                            })()}
                        </Deferred>
                    </PageCard>

                    <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-end">
                        <div className="flex flex-col gap-3 sm:flex-row">
                            <label className="relative block min-w-[280px]">
                                <Search className="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-[#7f8b9b]" />
                                <input
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    placeholder="Search by name or URL"
                                    className="h-12 w-full rounded-[16px] border border-[#2b3544] bg-[#121821] pl-11 pr-4 text-base text-white outline-none placeholder:text-[#7f8b9b] focus:border-[#57c7c2]"
                                />
                            </label>

                            <button
                                type="button"
                                className={cn(
                                    'inline-flex h-12 items-center gap-3 rounded-[16px] border border-[#2b3544] px-4 text-base text-[#cfd8ec] transition',
                                    showOnlyDown ? 'bg-[#211817] text-[#ffe3e0]' : 'bg-[#171d28]',
                                )}
                                onClick={() => setShowOnlyDown((current) => !current)}
                            >
                                <Filter className="size-4 text-[#7d8aa7]" />
                                {showOnlyDown ? 'Down only' : 'Filter'}
                            </button>

                            <button
                                type="button"
                                className="inline-flex h-12 items-center gap-3 rounded-[16px] border border-[#2b3544] bg-[#171d28] px-4 text-base text-[#cfd8ec]"
                                onClick={() => setSortMode((current) => (current === 'down' ? 'name' : 'down'))}
                            >
                                <ArrowUpDown className="size-4 text-[#7d8aa7]" />
                                {sortMode === 'down' ? 'Down first' : 'Name'}
                            </button>
                        </div>
                    </div>

                    {!summary.canCreate ? (
                        <PageCard className="flex flex-col gap-3 rounded-[20px] border border-[#7c8cff]/18 bg-[#171c33] px-5 py-4 text-[#d6ddff] sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div className="text-[17px] font-semibold text-white">Monitor limit reached</div>
                                <div className="mt-1 text-sm text-[#d3daff]">
                                    This workspace has reached its allowance. Upgrade the plan or retire an existing check before adding another one.
                                </div>
                            </div>
                            {membershipManageUrl ? (
                                <Link
                                    href={membershipManageUrl}
                                    className="inline-flex h-11 items-center justify-center rounded-[14px] bg-[#7c8cff] px-4 text-sm font-medium text-white"
                                >
                                    Compare plans
                                </Link>
                            ) : null}
                        </PageCard>
                    ) : null}

                    <div className="space-y-4">
                        {filtered.length === 0 ? (
                            <PageCard className="p-6 text-[#9ca7b9]">
                                No checks matched the current filters.
                            </PageCard>
                        ) : (
                            filtered.map((monitor) => (
                                <Link key={monitor.id} href={monitor.showUrl} className="block">
                                    <PageCard className="rounded-[22px] px-5 py-4 transition hover:border-[#3b4656] hover:bg-[#161d29]">
                                        <div className="flex flex-col gap-4 xl:flex-row xl:items-center">
                                            <div className="flex min-w-0 flex-1 items-center gap-4">
                                                <StatusChip status={monitor.status} />
                                                <div className="min-w-0">
                                                    <div className="truncate text-[26px] font-semibold tracking-[-0.05em] text-white lg:text-[28px]">
                                                        {monitor.name}
                                                    </div>
                                                    <div className="mt-1 flex flex-wrap items-center gap-3 text-[15px] text-[#9eacc7] lg:text-[16px]">
                                                        <span className="rounded-[10px] border border-[#2b3544] bg-[#151d28] px-2.5 py-1 text-[11px] uppercase tracking-[0.18em] text-[#aebadc]">
                                                            {monitor.type}
                                                        </span>
                                                        <span>{monitor.statusSummary}</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="flex items-center gap-4 xl:min-w-[390px] xl:justify-end">
                                                <div className="text-right text-[15px] text-[#8fa0bf] lg:text-base">
                                                    <div>{monitor.intervalLabel}</div>
                                                    <div className="mt-1 text-sm">{monitor.lastCheckedLabel}</div>
                                                </div>
                                                <div className="w-[92px] text-right text-[15px] text-[#8fa0bf] lg:text-base">
                                                    {monitor.responseTimeLabel}
                                                </div>
                                                <div className="w-full max-w-[190px] space-y-2 xl:w-[190px]">
                                                    <UptimeBars bars={monitor.bars} compact />
                                                    <div className="text-right text-base font-medium text-[#dfe7fa]">
                                                        {monitor.uptimePercentLabel}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </PageCard>
                                </Link>
                            ))
                        )}
                    </div>

                    <PageCard className="flex flex-col gap-4 rounded-[22px] px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div className="text-[22px] font-semibold tracking-[-0.04em] text-white lg:text-[24px]">
                                Alert routing is live.
                            </div>
                            <div className="mt-2 max-w-[760px] text-[15px] text-[#98a6c4] lg:text-[16px]">
                                Contacts are configured for this workspace. Validate delivery from any check detail page before sending traffic through production.
                            </div>
                        </div>
                        <div className="rounded-[14px] bg-[#57c7c2] px-4 py-2.5 text-base font-semibold text-[#08131a]">
                            Email active
                        </div>
                    </PageCard>
                </section>

                <aside className="space-y-4">
                    <PageCard className="px-6 py-6">
                        <h2 className="text-[20px] font-semibold tracking-[-0.04em] text-white">
                            Workspace summary<span className="text-[#7c8cff]">.</span>
                        </h2>
                        <div className="mt-7 flex justify-center">
                            <StatusChip status="up" large />
                        </div>
                        <div className="mt-7 grid grid-cols-3 gap-3 text-center">
                            <div>
                                <div className="text-[34px] font-semibold tracking-[-0.05em] text-white">{summary.down}</div>
                                <div className="text-base text-[#8fa0bf]">Down</div>
                            </div>
                            <div>
                                <div className="text-[34px] font-semibold tracking-[-0.05em] text-white">{summary.up}</div>
                                <div className="text-base text-[#8fa0bf]">Up</div>
                            </div>
                            <div>
                                <div className="text-[34px] font-semibold tracking-[-0.05em] text-white">{summary.paused}</div>
                                <div className="text-base text-[#8fa0bf]">Paused</div>
                            </div>
                        </div>
                        <div className="mt-7 text-center text-[15px] text-[#8fa0bf]">{summary.usageLabel}</div>
                    </PageCard>

                    <PageCard className="px-6 py-6">
                        <h2 className="text-[20px] font-semibold tracking-[-0.04em] text-white">
                            24h health<span className="text-[#7c8cff]">.</span>
                        </h2>
                        <div className="mt-7 grid grid-cols-2 gap-x-6 gap-y-6">
                            <div>
                                <div className="text-[24px] font-semibold tracking-[-0.04em] text-[#57c7c2]">
                                    {last24Hours.uptimeLabel}
                                </div>
                                <div className="text-base text-[#8fa0bf]">Overall uptime</div>
                            </div>
                            <div>
                                <div className="text-[24px] font-semibold tracking-[-0.04em] text-white">
                                    {last24Hours.mtbfLabel}
                                </div>
                                <div className="text-base text-[#8fa0bf]">MTBF</div>
                            </div>
                            <div>
                                <div className="text-[24px] font-semibold tracking-[-0.04em] text-white">
                                    {last24Hours.withoutIncidentsLabel}
                                </div>
                                <div className="text-base text-[#8fa0bf]">Without inc.</div>
                            </div>
                            <div>
                                <div className="text-[24px] font-semibold tracking-[-0.04em] text-white">
                                    {last24Hours.incidentsCount}
                                </div>
                                <div className="text-base text-[#8fa0bf]">Incidents</div>
                            </div>
                        </div>
                    </PageCard>
                </aside>
            </div>
        </MonitoringLayout>
    );
}

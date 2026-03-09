import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowUpDown,
    Filter,
    MoreHorizontal,
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
    MonitorListItem,
    MonitorSummary,
} from '@/types/monitoring';

type MonitorsIndexProps = {
    summary: MonitorSummary;
    last24Hours: AggregateWindowStats;
    monitors: MonitorListItem[];
};

export default function MonitorsIndex({
    summary,
    last24Hours,
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
            <Head title="Monitors" />
            <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_300px]">
                <section className="space-y-5">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h1 className="text-[40px] font-semibold tracking-[-0.06em] text-white lg:text-[46px]">
                                Monitors<span className="text-[#3ee072]">.</span>
                            </h1>
                            <div className="mt-2 text-[15px] text-[#8fa0bf]">{summary.usageLabel}</div>
                        </div>

                        <div className="flex items-center gap-3 self-start lg:self-auto">
                            {summary.canCreate ? (
                                <Link
                                    href="/monitors/create"
                                    className="inline-flex items-center gap-3 rounded-[16px] bg-[#352ef6] px-5 py-3 text-base font-medium text-white shadow-[0_14px_36px_rgba(53,46,246,0.35)] transition hover:bg-[#4038ff]"
                                >
                                    <Plus className="size-[18px]" />
                                    New
                                </Link>
                            ) : membershipManageUrl ? (
                                <Link
                                    href={membershipManageUrl}
                                    className="inline-flex items-center gap-3 rounded-[16px] border border-[#3ee072]/25 bg-[#10273a] px-5 py-3 text-base font-medium text-[#dfffe9] transition hover:border-[#3ee072]/40"
                                >
                                    <Plus className="size-[18px]" />
                                    Upgrade to add monitors
                                </Link>
                            ) : (
                                <div className="inline-flex items-center gap-3 rounded-[16px] border border-white/10 bg-[#111a2c] px-5 py-3 text-base font-medium text-[#8fa0bf]">
                                    <Plus className="size-[18px]" />
                                    Limit reached
                                </div>
                            )}
                            <button
                                type="button"
                                className="inline-flex size-[48px] items-center justify-center rounded-[16px] border border-white/6 bg-[#1a2339]/95 text-[#aab7d4]"
                                onClick={() => setSortMode((current) => (current === 'down' ? 'name' : 'down'))}
                            >
                                <ArrowUpDown className="size-4" />
                            </button>
                        </div>
                    </div>

                    <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                        <div className="flex flex-wrap items-center gap-3">
                            <PageCard className="flex items-center gap-3 px-4 py-3 text-[#dfe8fb]">
                                <div className="size-4 rounded-[6px] border border-white/10 bg-[#0b1425]" />
                                <span className="text-base">0 / {summary.total}</span>
                            </PageCard>
                            <PageCard className="flex items-center gap-3 px-4 py-3 text-[#8fa0bf]">
                                <span className="inline-flex size-4 rounded-[6px] border border-[#ffb454]/50 text-[#ffb454]" />
                                <span className="text-base">Show groups</span>
                            </PageCard>
                        </div>

                        <div className="flex flex-col gap-3 sm:flex-row">
                            <label className="relative block min-w-[280px]">
                                <Search className="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-[#6f7d98]" />
                                <input
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    placeholder="Search by name or URL"
                                    className="h-12 w-full rounded-[16px] border border-white/10 bg-[#0c1628] pl-11 pr-4 text-base text-white outline-none placeholder:text-[#6f7d98] focus:border-[#3a4b6b]"
                                />
                            </label>

                            <button
                                type="button"
                                className={cn(
                                    'inline-flex h-12 items-center gap-3 rounded-[16px] border border-white/6 px-4 text-base text-[#cfd8ec] transition',
                                    showOnlyDown ? 'bg-[#1e2941]' : 'bg-[#1a2339]/95',
                                )}
                                onClick={() => setShowOnlyDown((current) => !current)}
                            >
                                <Filter className="size-4 text-[#7d8aa7]" />
                                Filter
                            </button>

                            <button
                                type="button"
                                className="inline-flex h-12 items-center gap-3 rounded-[16px] border border-white/6 bg-[#1a2339]/95 px-4 text-base text-[#cfd8ec]"
                                onClick={() => setSortMode((current) => (current === 'down' ? 'name' : 'down'))}
                            >
                                <ArrowUpDown className="size-4 text-[#7d8aa7]" />
                                {sortMode === 'down' ? 'Down first' : 'Name'}
                            </button>
                        </div>
                    </div>

                    {!summary.canCreate ? (
                        <PageCard className="flex flex-col gap-3 rounded-[20px] border border-[#ffb454]/18 bg-[#2b2110] px-5 py-4 text-[#ffe3b0] sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div className="text-[17px] font-semibold text-white">Monitor limit reached</div>
                                <div className="mt-1 text-sm text-[#ffd88c]">
                                    This workspace has reached its monitor allowance. Upgrade the membership plan to create additional monitors or remove an existing one.
                                </div>
                            </div>
                            {membershipManageUrl ? (
                                <Link
                                    href={membershipManageUrl}
                                    className="inline-flex h-11 items-center justify-center rounded-[14px] bg-[#352ef6] px-4 text-sm font-medium text-white"
                                >
                                    Compare plans
                                </Link>
                            ) : null}
                        </PageCard>
                    ) : null}

                    <div className="space-y-4">
                        {filtered.length === 0 ? (
                            <PageCard className="p-8 text-[#8fa0bf]">
                                No monitors matched the current filters.
                            </PageCard>
                        ) : (
                            filtered.map((monitor) => (
                                <Link key={monitor.id} href={monitor.showUrl} className="block">
                                    <PageCard className="rounded-[20px] px-5 py-4 transition hover:border-white/12 hover:bg-[#1d2740]">
                                        <div className="flex flex-col gap-4 xl:flex-row xl:items-center">
                                            <div className="flex min-w-0 flex-1 items-center gap-4">
                                                <StatusChip status={monitor.status} />
                                                <div className="min-w-0">
                                                    <div className="truncate text-[26px] font-semibold tracking-[-0.05em] text-white lg:text-[28px]">
                                                        {monitor.name}
                                                    </div>
                                                    <div className="mt-1 flex flex-wrap items-center gap-3 text-[15px] text-[#9eacc7] lg:text-[16px]">
                                                        <span className="rounded-[10px] border border-white/8 bg-[#162139] px-2.5 py-1 text-[11px] uppercase tracking-[0.18em] text-[#aebadc]">
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
                                                <div className="w-full max-w-[190px] space-y-2 xl:w-[190px]">
                                                    <UptimeBars bars={monitor.bars} compact />
                                                    <div className="text-right text-base font-medium text-[#dfe7fa]">
                                                        {monitor.uptimePercentLabel}
                                                    </div>
                                                </div>
                                                <div className="text-right text-[15px] text-[#8fa0bf] lg:text-base">{monitor.responseTimeLabel}</div>
                                                <MoreHorizontal className="size-4 text-[#7d8aa7]" />
                                            </div>
                                        </div>
                                    </PageCard>
                                </Link>
                            ))
                        )}
                    </div>

                    <PageCard className="flex flex-col gap-4 rounded-[24px] px-7 py-6 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div className="text-[22px] font-semibold tracking-[-0.04em] text-white lg:text-[24px]">
                                Email notifications are active.
                            </div>
                            <div className="mt-2 max-w-[760px] text-[15px] text-[#98a6c4] lg:text-[16px]">
                                Alert routing is configured for your primary contact. Test delivery from any monitor detail page.
                            </div>
                        </div>
                        <div className="rounded-[14px] bg-[#3ee072] px-4 py-2.5 text-base font-semibold text-[#0b1730]">
                            Email only
                        </div>
                    </PageCard>
                </section>

                <aside className="space-y-4">
                    <PageCard className="px-7 py-8">
                        <h2 className="text-[20px] font-semibold tracking-[-0.04em] text-white">
                            Current status<span className="text-[#3ee072]">.</span>
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

                    <PageCard className="px-7 py-8">
                        <h2 className="text-[20px] font-semibold tracking-[-0.04em] text-white">
                            Last 24 hours<span className="text-[#3ee072]">.</span>
                        </h2>
                        <div className="mt-7 grid grid-cols-2 gap-x-6 gap-y-6">
                            <div>
                                <div className="text-[24px] font-semibold tracking-[-0.04em] text-[#3ee072]">
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

import { Head, router, useForm } from '@inertiajs/react';
import { ExternalLink, Globe2, Save, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { PaginationStrip } from '@/components/monitoring/pagination-strip';
import { PageCard } from '@/components/monitoring/page-card';
import { StatusDot } from '@/components/monitoring/status-chip';
import MonitoringLayout from '@/layouts/monitoring-layout';
import type {
    MonitorOption,
    PaginatedData,
    StatusPageFormData,
    StatusPageIncidentFormData,
    StatusPageIncidentItem,
    StatusPageIncidentUpdateFormData,
    StatusPageItem,
} from '@/types/monitoring';

const incidentStatuses = [
    { value: 'investigating', label: 'Investigating' },
    { value: 'identified', label: 'Identified' },
    { value: 'monitoring', label: 'Monitoring' },
    { value: 'resolved', label: 'Resolved' },
];

const incidentImpacts = [
    { value: 'minor', label: 'Minor' },
    { value: 'major', label: 'Major' },
    { value: 'critical', label: 'Critical' },
];

function StatusPageIncidentCard({ incident }: { incident: StatusPageIncidentItem }) {
    const form = useForm<StatusPageIncidentUpdateFormData>({
        status: incident.isResolved ? 'resolved' : incident.status,
        message: '',
    });
    const errors = Object.values(form.errors);

    return (
        <div className="space-y-4 rounded-[18px] border border-white/8 bg-[#171d28] px-5 py-5">
            <div className="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
                <div>
                    <div className="flex flex-wrap items-center gap-3 text-[18px] font-semibold text-white">
                        <span>{incident.title}</span>
                        <span className="rounded-full bg-[#121821] px-3 py-1 text-[12px] text-[#9bb4ff]">
                            {incident.statusLabel}
                        </span>
                        <span className="rounded-full bg-[#261826] px-3 py-1 text-[12px] text-[#ffd4d7]">
                            {incident.impactLabel}
                        </span>
                    </div>
                    <div className="mt-2 text-[14px] text-[#9ca7b9]">
                        Started {incident.startedAt ?? 'Unknown'}
                        {incident.resolvedAt ? ` • Resolved ${incident.resolvedAt}` : ''}
                    </div>
                    <div className="mt-2 text-[15px] text-[#dce6fb]">{incident.message}</div>
                    <div className="mt-2 text-[13px] text-[#7f8eab]">Affected monitors: {incident.monitorNames.join(', ')}</div>
                </div>
                <button
                    type="button"
                    className="inline-flex items-center gap-2 rounded-[14px] border border-[#ff6269]/25 bg-[#231320] px-4 py-2.5 text-sm text-[#ffd4d7]"
                    onClick={() => {
                        if (window.confirm(`Delete incident post "${incident.title}"?`)) {
                            router.delete(`/status-page-incidents/${incident.id}`, { preserveScroll: true });
                        }
                    }}
                >
                    <Trash2 className="size-4" />
                    Delete
                </button>
            </div>

            <div className="space-y-3">
                {incident.updates.map((update) => (
                    <div key={update.id} className="rounded-[14px] bg-[#121821] px-4 py-4">
                        <div className="flex items-center justify-between gap-3 text-[14px] text-white">
                            <span>{update.statusLabel}</span>
                            <span className="text-[#7f8eab]">{update.createdAt}</span>
                        </div>
                        <div className="mt-2 text-[14px] text-[#dce6fb]">{update.message}</div>
                    </div>
                ))}
            </div>

            <form
                className="grid gap-4 rounded-[16px] border border-white/6 bg-[#121821] px-4 py-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(`/status-page-incidents/${incident.id}/updates`, {
                        preserveScroll: true,
                        onSuccess: () => form.reset('message'),
                    });
                }}
            >
                {errors.length > 0 ? (
                    <div className="rounded-[14px] border border-[#ff6269]/20 bg-[#2a1621] px-4 py-3 text-sm text-[#ffd4d7]">
                        {errors.join(' ')}
                    </div>
                ) : null}
                <div className="grid gap-4 lg:grid-cols-[220px_minmax(0,1fr)]">
                    <label className="space-y-2">
                        <span className="text-[14px] text-[#dce6fb]">Status</span>
                        <select
                            value={form.data.status}
                            onChange={(event) => form.setData('status', event.target.value)}
                            className="h-11 w-full rounded-[14px] border border-white/10 bg-[#121821] px-4 text-sm text-white outline-none"
                        >
                            {incidentStatuses.map((status) => (
                                <option key={status.value} value={status.value} className="bg-[#121821]">
                                    {status.label}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="space-y-2">
                        <span className="text-[14px] text-[#dce6fb]">Manual update</span>
                        <textarea
                            value={form.data.message}
                            onChange={(event) => form.setData('message', event.target.value)}
                            className="min-h-[88px] w-full rounded-[14px] border border-white/10 bg-[#121821] px-4 py-3 text-sm text-white outline-none"
                            placeholder="Post an update to the public timeline"
                        />
                    </label>
                </div>
                <div className="flex justify-end">
                    <button type="submit" className="inline-flex items-center gap-2 rounded-[14px] bg-[#7c8cff] px-4 py-2.5 text-sm font-medium text-white">
                        <Save className="size-4" />
                        Publish update
                    </button>
                </div>
            </form>
        </div>
    );
}

function StatusPageEditor({
    page,
    monitorOptions,
}: {
    page: StatusPageItem;
    monitorOptions: MonitorOption[];
}) {
    const form = useForm<StatusPageFormData>({
        name: page.name,
        slug: page.slug,
        headline: page.headline ?? page.name,
        description: page.description ?? '',
        published: page.published,
        monitor_ids: page.monitorIds,
    });
    const incidentForm = useForm<StatusPageIncidentFormData>(page.incidentDefaults);
    const errors = Object.values(form.errors);
    const incidentErrors = Object.values(incidentForm.errors);
    const availableMonitors = monitorOptions.filter((monitor) => page.monitorIds.includes(monitor.id));

    return (
        <PageCard className="space-y-6 p-6">
            <div className="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div>
                    <div className="flex items-center gap-3 text-[20px] font-semibold text-white">
                        <StatusDot status={/(outage|degraded)/.test(page.statusLabel.toLowerCase()) ? 'down' : 'up'} />
                        {page.name}
                    </div>
                    <div className="mt-2 text-[15px] text-[#9ca7b9]">
                        {page.monitorCount} monitors • Updated {page.updatedLabel}
                    </div>
                </div>
                <div className="flex flex-wrap gap-3">
                    <a
                        href={page.publicUrl}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center gap-2 rounded-[14px] border border-white/6 bg-[#0e1729] px-4 py-2.5 text-sm text-[#dce6fb]"
                    >
                        <ExternalLink className="size-4" />
                        Open public page
                    </a>
                    <button
                        type="button"
                        className="inline-flex items-center gap-2 rounded-[14px] border border-[#ff6269]/25 bg-[#231320] px-4 py-2.5 text-sm text-[#ffd4d7]"
                        onClick={() => {
                            if (window.confirm(`Delete status page "${page.name}"?`)) {
                                router.delete(`/status-pages/${page.id}`, { preserveScroll: true });
                            }
                        }}
                    >
                        <Trash2 className="size-4" />
                        Delete
                    </button>
                </div>
            </div>

            <form
                className="grid gap-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(`/status-pages/${page.id}`, { preserveScroll: true });
                }}
            >
                {errors.length > 0 ? (
                    <div className="rounded-[16px] border border-[#ff6269]/20 bg-[#2a1621] px-4 py-3 text-sm text-[#ffd4d7]">
                        {errors.join(' ')}
                    </div>
                ) : null}
                <div className="grid gap-4 lg:grid-cols-2">
                    <label className="space-y-2">
                        <span className="text-[15px] text-[#dce6fb]">Name</span>
                        <input
                            value={form.data.name}
                            onChange={(event) => form.setData('name', event.target.value)}
                            className="h-11 w-full rounded-[14px] border border-white/10 bg-[#0b1425] px-4 text-sm text-white outline-none"
                        />
                    </label>
                    <label className="space-y-2">
                        <span className="text-[15px] text-[#dce6fb]">Slug</span>
                        <input
                            value={form.data.slug}
                            onChange={(event) => form.setData('slug', event.target.value)}
                            className="h-11 w-full rounded-[14px] border border-white/10 bg-[#0b1425] px-4 text-sm text-white outline-none"
                        />
                    </label>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <label className="space-y-2">
                        <span className="text-[15px] text-[#dce6fb]">Headline</span>
                        <input
                            value={form.data.headline}
                            onChange={(event) => form.setData('headline', event.target.value)}
                            className="h-11 w-full rounded-[14px] border border-white/10 bg-[#0b1425] px-4 text-sm text-white outline-none"
                        />
                    </label>
                    <label className="flex items-center gap-3 rounded-[16px] border border-white/8 bg-[#171d28] px-4 py-3 text-sm text-[#dce6fb]">
                        <input
                            type="checkbox"
                            checked={form.data.published}
                            onChange={(event) => form.setData('published', event.target.checked)}
                            className="size-4 rounded border-white/15 bg-[#121821]"
                        />
                        Published and publicly accessible
                    </label>
                </div>

                <label className="space-y-2">
                    <span className="text-[15px] text-[#dce6fb]">Description</span>
                    <textarea
                        value={form.data.description}
                        onChange={(event) => form.setData('description', event.target.value)}
                        className="min-h-[96px] w-full rounded-[14px] border border-white/10 bg-[#0b1425] px-4 py-3 text-sm text-white outline-none"
                    />
                </label>

                <div className="space-y-3">
                    <div className="text-[15px] text-[#dce6fb]">Monitors included</div>
                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {monitorOptions.map((monitor) => {
                            const checked = form.data.monitor_ids.includes(monitor.id);

                            return (
                                <label key={monitor.id} className="flex items-center gap-3 rounded-[14px] border border-white/8 bg-[#171d28] px-4 py-3 text-sm text-[#dce6fb]">
                                    <input
                                        type="checkbox"
                                        checked={checked}
                                        onChange={(event) => {
                                            form.setData(
                                                'monitor_ids',
                                                event.target.checked
                                                    ? [...form.data.monitor_ids, monitor.id]
                                                    : form.data.monitor_ids.filter((id) => id !== monitor.id),
                                            );
                                        }}
                                        className="size-4 rounded border-white/15 bg-[#121821]"
                                    />
                                    <span className="min-w-0 flex-1 truncate">{monitor.name}</span>
                                    <span className="text-[#7f8eab]">{monitor.type}</span>
                                </label>
                            );
                        })}
                    </div>
                </div>

                <div className="flex justify-end">
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="inline-flex items-center gap-2 rounded-[14px] bg-[#7c8cff] px-4 py-2.5 text-sm font-medium text-white"
                    >
                        <Save className="size-4" />
                        Save changes
                    </button>
                </div>
            </form>

            <div className="space-y-4 border-t border-white/8 pt-6">
                <div>
                    <div className="text-[20px] font-semibold text-white">Public incident updates</div>
                    <div className="mt-1 text-[14px] text-[#9ca7b9]">
                        Publish manual status communications like investigating, identified, monitoring, and resolved.
                    </div>
                </div>

                <form
                    className="grid gap-4 rounded-[18px] border border-white/6 bg-[#121821] px-4 py-4"
                    onSubmit={(event: FormEvent) => {
                        event.preventDefault();
                        incidentForm.post(`/status-pages/${page.id}/incidents`, {
                            preserveScroll: true,
                            onSuccess: () => incidentForm.reset(),
                        });
                    }}
                >
                    {incidentErrors.length > 0 ? (
                        <div className="rounded-[14px] border border-[#ff6269]/20 bg-[#2a1621] px-4 py-3 text-sm text-[#ffd4d7]">
                            {incidentErrors.join(' ')}
                        </div>
                    ) : null}
                    <div className="grid gap-4 lg:grid-cols-2">
                        <label className="space-y-2">
                            <span className="text-[14px] text-[#dce6fb]">Title</span>
                            <input
                                value={incidentForm.data.title}
                                onChange={(event) => incidentForm.setData('title', event.target.value)}
                                className="h-11 w-full rounded-[14px] border border-white/10 bg-[#121821] px-4 text-sm text-white outline-none"
                                placeholder="API latency incident"
                            />
                        </label>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <label className="space-y-2">
                                <span className="text-[14px] text-[#dce6fb]">Status</span>
                                <select
                                    value={incidentForm.data.status}
                                    onChange={(event) => incidentForm.setData('status', event.target.value)}
                                    className="h-11 w-full rounded-[14px] border border-white/10 bg-[#121821] px-4 text-sm text-white outline-none"
                                >
                                    {incidentStatuses.map((status) => (
                                        <option key={status.value} value={status.value} className="bg-[#121821]">
                                            {status.label}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label className="space-y-2">
                                <span className="text-[14px] text-[#dce6fb]">Impact</span>
                                <select
                                    value={incidentForm.data.impact}
                                    onChange={(event) => incidentForm.setData('impact', event.target.value)}
                                    className="h-11 w-full rounded-[14px] border border-white/10 bg-[#121821] px-4 text-sm text-white outline-none"
                                >
                                    {incidentImpacts.map((impact) => (
                                        <option key={impact.value} value={impact.value} className="bg-[#121821]">
                                            {impact.label}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        </div>
                    </div>

                    <label className="space-y-2">
                        <span className="text-[14px] text-[#dce6fb]">Public message</span>
                        <textarea
                            value={incidentForm.data.message}
                            onChange={(event) => incidentForm.setData('message', event.target.value)}
                            className="min-h-[88px] w-full rounded-[14px] border border-white/10 bg-[#121821] px-4 py-3 text-sm text-white outline-none"
                            placeholder="We are investigating elevated response times."
                        />
                    </label>

                    <div className="space-y-3">
                        <div className="text-[14px] text-[#dce6fb]">Affected monitors</div>
                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            {availableMonitors.map((monitor) => {
                                const checked = incidentForm.data.monitor_ids.includes(monitor.id);

                                return (
                                    <label key={monitor.id} className="flex items-center gap-3 rounded-[14px] border border-white/8 bg-[#171d28] px-4 py-3 text-sm text-[#dce6fb]">
                                        <input
                                            type="checkbox"
                                            checked={checked}
                                            onChange={(event) => {
                                                incidentForm.setData(
                                                    'monitor_ids',
                                                    event.target.checked
                                                        ? [...incidentForm.data.monitor_ids, monitor.id]
                                                        : incidentForm.data.monitor_ids.filter((id) => id !== monitor.id),
                                                );
                                            }}
                                            className="size-4 rounded border-white/15 bg-[#121821]"
                                        />
                                        <span className="min-w-0 flex-1 truncate">{monitor.name}</span>
                                    </label>
                                );
                            })}
                        </div>
                    </div>

                    <div className="flex justify-end">
                        <button type="submit" className="inline-flex items-center gap-2 rounded-[14px] bg-[#7c8cff] px-4 py-2.5 text-sm font-medium text-white">
                            <Save className="size-4" />
                            Publish incident
                        </button>
                    </div>
                </form>

                <div className="space-y-4">
                    {page.incidents.length === 0 ? (
                        <div className="rounded-[16px] border border-dashed border-white/10 bg-[#171d28] px-4 py-5 text-[14px] text-[#9ca7b9]">
                            No public incident posts yet.
                        </div>
                    ) : (
                        page.incidents.map((incident) => <StatusPageIncidentCard key={incident.id} incident={incident} />)
                    )}
                </div>
            </div>
        </PageCard>
    );
}

type StatusPagesPageProps = {
    summary: {
        published: number;
        drafts: number;
        monitors: number;
        activeIncidents: number;
    };
    pages: PaginatedData<StatusPageItem>;
    monitorOptions: MonitorOption[];
    monitorOptionQuery: string;
    monitorOptionResults: PaginatedData<MonitorOption>;
    formDefaults: StatusPageFormData;
};

export default function StatusPagesPage({
    summary,
    pages,
    monitorOptions,
    monitorOptionQuery,
    monitorOptionResults,
    formDefaults,
}: StatusPagesPageProps) {
    const form = useForm<StatusPageFormData>(formDefaults);
    const errors = Object.values(form.errors);
    const [monitorSearch, setMonitorSearch] = useState(monitorOptionQuery);

    const syncSlug = (value: string) => {
        form.setData('name', value);
        form.setData('slug', value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, ''));
    };

    const submitMonitorSearch = (query: string) => {
        router.get(
            '/status-pages',
            {
                page: pages.currentPage,
                monitor_query: query,
                monitor_page: 1,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    return (
        <MonitoringLayout>
            <Head title="Status hub" />
            <div className="space-y-5">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h1 className="text-[40px] font-semibold tracking-[-0.06em] text-white lg:text-[46px]">
                            Status hub<span className="text-[#7c8cff]">.</span>
                        </h1>
                        <div className="mt-2 max-w-[760px] text-[16px] text-[#9ca7b9]">
                            Publish customer-facing service status pages for any subset of checks. Each page gets its own shareable URL and incident update stream.
                        </div>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-4">
                        <PageCard className="px-5 py-4">
                            <div className="text-sm text-[#9ca7b9]">Published</div>
                            <div className="mt-1 text-[30px] font-semibold text-white">{summary.published}</div>
                        </PageCard>
                        <PageCard className="px-5 py-4">
                            <div className="text-sm text-[#9ca7b9]">Drafts</div>
                            <div className="mt-1 text-[30px] font-semibold text-white">{summary.drafts}</div>
                        </PageCard>
                        <PageCard className="px-5 py-4">
                            <div className="text-sm text-[#9ca7b9]">Checks exposed</div>
                            <div className="mt-1 text-[30px] font-semibold text-white">{summary.monitors}</div>
                        </PageCard>
                        <PageCard className="px-5 py-4">
                            <div className="text-sm text-[#9ca7b9]">Active posts</div>
                            <div className="mt-1 text-[30px] font-semibold text-white">{summary.activeIncidents}</div>
                        </PageCard>
                    </div>
                </div>

                <PageCard className="space-y-5 p-6">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <div className="text-[20px] font-semibold text-white">Monitor browser</div>
                            <div className="mt-1 text-[14px] text-[#9ca7b9]">
                                Showing {monitorOptionResults.from ?? 0}-{monitorOptionResults.to ?? 0} of {monitorOptionResults.total} checks for the create and edit forms on this page.
                            </div>
                        </div>
                        <form
                            className="flex flex-col gap-3 sm:flex-row"
                            onSubmit={(event: FormEvent) => {
                                event.preventDefault();
                                submitMonitorSearch(monitorSearch);
                            }}
                        >
                            <input
                                value={monitorSearch}
                                onChange={(event) => setMonitorSearch(event.target.value)}
                                className="h-11 min-w-[220px] rounded-[14px] border border-white/10 bg-[#0b1425] px-4 text-sm text-white outline-none"
                                placeholder="Search checks by name"
                            />
                            <div className="flex gap-3">
                                <button type="submit" className="inline-flex h-11 items-center justify-center rounded-[14px] bg-[#7c8cff] px-4 text-sm font-medium text-white">
                                    Search
                                </button>
                                {monitorOptionQuery ? (
                                    <button
                                        type="button"
                                        className="inline-flex h-11 items-center justify-center rounded-[14px] border border-white/10 bg-[#171d28] px-4 text-sm text-[#dce6fb]"
                                        onClick={() => {
                                            setMonitorSearch('');
                                            submitMonitorSearch('');
                                        }}
                                    >
                                        Clear
                                    </button>
                                ) : null}
                            </div>
                        </form>
                    </div>
                    <PaginationStrip
                        currentPage={monitorOptionResults.currentPage}
                        lastPage={monitorOptionResults.lastPage}
                        from={monitorOptionResults.from}
                        to={monitorOptionResults.to}
                        total={monitorOptionResults.total}
                        previousPageUrl={monitorOptionResults.previousPageUrl}
                        nextPageUrl={monitorOptionResults.nextPageUrl}
                    />
                </PageCard>

                <PageCard className="space-y-5 p-6">
                    <div className="flex items-center gap-3 text-[22px] font-semibold text-white">
                        <Globe2 className="size-5 text-[#7c8cff]" />
                        Create status page
                    </div>
                    <form
                        className="grid gap-4"
                        onSubmit={(event: FormEvent) => {
                            event.preventDefault();
                            form.post('/status-pages', {
                                preserveScroll: true,
                                onSuccess: () => form.reset(),
                            });
                        }}
                    >
                        {errors.length > 0 ? (
                            <div className="rounded-[16px] border border-[#ff6269]/20 bg-[#2a1621] px-4 py-3 text-sm text-[#ffd4d7]">
                                {errors.join(' ')}
                            </div>
                        ) : null}
                        <div className="grid gap-4 lg:grid-cols-2">
                            <label className="space-y-2">
                                <span className="text-[15px] text-[#dce6fb]">Name</span>
                                <input
                                    value={form.data.name}
                                    onChange={(event) => syncSlug(event.target.value)}
                                    className="h-11 w-full rounded-[14px] border border-white/10 bg-[#0b1425] px-4 text-sm text-white outline-none"
                                    placeholder="Primary status page"
                                />
                            </label>
                            <label className="space-y-2">
                                <span className="text-[15px] text-[#dce6fb]">Slug</span>
                                <input
                                    value={form.data.slug}
                                    onChange={(event) => form.setData('slug', event.target.value)}
                                    className="h-11 w-full rounded-[14px] border border-white/10 bg-[#0b1425] px-4 text-sm text-white outline-none"
                                    placeholder="primary-status"
                                />
                            </label>
                        </div>
                        <div className="grid gap-4 lg:grid-cols-2">
                            <label className="space-y-2">
                                <span className="text-[15px] text-[#dce6fb]">Headline</span>
                                <input
                                    value={form.data.headline}
                                    onChange={(event) => form.setData('headline', event.target.value)}
                                    className="h-11 w-full rounded-[14px] border border-white/10 bg-[#0b1425] px-4 text-sm text-white outline-none"
                                />
                            </label>
                            <label className="flex items-center gap-3 rounded-[16px] border border-white/8 bg-[#171d28] px-4 py-3 text-sm text-[#dce6fb]">
                                <input
                                    type="checkbox"
                                    checked={form.data.published}
                                    onChange={(event) => form.setData('published', event.target.checked)}
                                    className="size-4 rounded border-white/15 bg-[#121821]"
                                />
                                Publish immediately
                            </label>
                        </div>
                        <label className="space-y-2">
                            <span className="text-[15px] text-[#dce6fb]">Description</span>
                            <textarea
                                value={form.data.description}
                                onChange={(event) => form.setData('description', event.target.value)}
                                className="min-h-[96px] w-full rounded-[14px] border border-white/10 bg-[#0b1425] px-4 py-3 text-sm text-white outline-none"
                            />
                        </label>
                        <div className="space-y-3">
                            <div className="text-[15px] text-[#dce6fb]">Included monitors</div>
                            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                {monitorOptions.map((monitor) => {
                                    const checked = form.data.monitor_ids.includes(monitor.id);

                                    return (
                                        <label key={monitor.id} className="flex items-center gap-3 rounded-[14px] border border-white/8 bg-[#171d28] px-4 py-3 text-sm text-[#dce6fb]">
                                            <input
                                                type="checkbox"
                                                checked={checked}
                                                onChange={(event) => {
                                                    form.setData(
                                                        'monitor_ids',
                                                        event.target.checked
                                                            ? [...form.data.monitor_ids, monitor.id]
                                                            : form.data.monitor_ids.filter((id) => id !== monitor.id),
                                                    );
                                                }}
                                                className="size-4 rounded border-white/15 bg-[#121821]"
                                            />
                                            <span className="min-w-0 flex-1 truncate">{monitor.name}</span>
                                            <span className="text-[#7f8eab]">{monitor.type}</span>
                                        </label>
                                    );
                                })}
                            </div>
                        </div>
                        <div className="flex justify-end">
                            <button type="submit" className="inline-flex items-center gap-2 rounded-[14px] bg-[#7c8cff] px-4 py-2.5 text-sm font-medium text-white">
                                <Save className="size-4" />
                                Create page
                            </button>
                        </div>
                    </form>
                </PageCard>

                <div className="space-y-4">
                    {pages.data.length === 0 ? (
                        <PageCard className="p-6 text-[15px] text-[#9ca7b9]">
                            No status pages yet. Create one above to publish monitor health publicly.
                        </PageCard>
                    ) : (
                        <>
                            {pages.data.map((page) => (
                                <StatusPageEditor key={page.id} page={page} monitorOptions={monitorOptions} />
                            ))}
                            <PaginationStrip
                                currentPage={pages.currentPage}
                                lastPage={pages.lastPage}
                                from={pages.from}
                                to={pages.to}
                                total={pages.total}
                                previousPageUrl={pages.previousPageUrl}
                                nextPageUrl={pages.nextPageUrl}
                            />
                        </>
                    )}
                </div>

                {pages.data.length > 0 ? (
                    <div className="text-sm text-[#7f8eab]">
                        Public URLs are available even without authentication. Draft pages return a 404 until published.
                    </div>
                ) : null}
            </div>
        </MonitoringLayout>
    );
}

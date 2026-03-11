import { Head, router, useForm } from '@inertiajs/react';
import { CalendarRange, Save, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { PaginationStrip } from '@/components/monitoring/pagination-strip';
import { PageCard } from '@/components/monitoring/page-card';
import MonitoringLayout from '@/layouts/monitoring-layout';
import type {
    MaintenanceFormData,
    MaintenanceWindowItem,
    MonitorOption,
    PaginatedData,
} from '@/types/monitoring';

function MaintenanceEditor({
    window,
    monitorOptions,
}: {
    window: MaintenanceWindowItem;
    monitorOptions: MonitorOption[];
}) {
    const form = useForm<MaintenanceFormData>({
        title: window.title,
        message: window.message ?? '',
        starts_at: window.startsAtValue,
        ends_at: window.endsAtValue,
        notify_contacts: window.notifyContacts,
        monitor_ids: window.monitorIds,
    });
    const errors = Object.values(form.errors);

    return (
        <PageCard className="space-y-5 p-6">
            <div className="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
                <div>
                    <div className="text-[20px] font-semibold text-white">{window.title}</div>
                    <div className="mt-2 text-[15px] text-[#9ca7b9]">
                        {window.status} • {window.startsAt} to {window.endsAt}
                    </div>
                </div>
                <button
                    type="button"
                    className="inline-flex items-center gap-2 rounded-[14px] border border-[#ff6269]/25 bg-[#231320] px-4 py-2.5 text-sm text-[#ffd4d7]"
                    onClick={() => {
                        if (globalThis.confirm(`Delete maintenance window "${window.title}"?`)) {
                            router.delete(`/maintenance-windows/${window.id}`, { preserveScroll: true });
                        }
                    }}
                >
                    <Trash2 className="size-4" />
                    Delete
                </button>
            </div>
            <form
                className="grid gap-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(`/maintenance-windows/${window.id}`, { preserveScroll: true });
                }}
            >
                {errors.length > 0 ? (
                    <div className="rounded-[16px] border border-[#ff6269]/20 bg-[#2a1621] px-4 py-3 text-sm text-[#ffd4d7]">
                        {errors.join(' ')}
                    </div>
                ) : null}
                <div className="grid gap-4 lg:grid-cols-2">
                    <label className="space-y-2">
                        <span className="text-[15px] text-[#dce6fb]">Title</span>
                        <input
                            value={form.data.title}
                            onChange={(event) => form.setData('title', event.target.value)}
                            className="h-11 w-full rounded-[14px] border border-white/10 bg-[#0b1425] px-4 text-sm text-white outline-none"
                        />
                    </label>
                    <label className="flex items-center gap-3 rounded-[16px] border border-white/8 bg-[#171d28] px-4 py-3 text-sm text-[#dce6fb]">
                        <input
                            type="checkbox"
                            checked={form.data.notify_contacts}
                            onChange={(event) => form.setData('notify_contacts', event.target.checked)}
                            className="size-4 rounded border-white/15 bg-[#121821]"
                        />
                        Notify contacts when this maintenance is created
                    </label>
                </div>
                <div className="grid gap-4 lg:grid-cols-2">
                    <label className="space-y-2">
                        <span className="text-[15px] text-[#dce6fb]">Starts at</span>
                        <input
                            type="datetime-local"
                            value={form.data.starts_at}
                            onChange={(event) => form.setData('starts_at', event.target.value)}
                            className="h-11 w-full rounded-[14px] border border-white/10 bg-[#0b1425] px-4 text-sm text-white outline-none"
                        />
                    </label>
                    <label className="space-y-2">
                        <span className="text-[15px] text-[#dce6fb]">Ends at</span>
                        <input
                            type="datetime-local"
                            value={form.data.ends_at}
                            onChange={(event) => form.setData('ends_at', event.target.value)}
                            className="h-11 w-full rounded-[14px] border border-white/10 bg-[#0b1425] px-4 text-sm text-white outline-none"
                        />
                    </label>
                </div>
                <label className="space-y-2">
                    <span className="text-[15px] text-[#dce6fb]">Message</span>
                    <textarea
                        value={form.data.message}
                        onChange={(event) => form.setData('message', event.target.value)}
                        className="min-h-[96px] w-full rounded-[14px] border border-white/10 bg-[#0b1425] px-4 py-3 text-sm text-white outline-none"
                    />
                </label>
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
                            </label>
                        );
                    })}
                </div>
                <div className="flex justify-end">
                    <button type="submit" className="inline-flex items-center gap-2 rounded-[14px] bg-[#7c8cff] px-4 py-2.5 text-sm font-medium text-white">
                        <Save className="size-4" />
                        Save window
                    </button>
                </div>
            </form>
        </PageCard>
    );
}

type MaintenancePageProps = {
    summary: {
        active: number;
        upcoming: number;
        history: number;
    };
    active: MaintenanceWindowItem[];
    upcoming: MaintenanceWindowItem[];
    history: PaginatedData<MaintenanceWindowItem>;
    monitorOptions: MonitorOption[];
    monitorOptionQuery: string;
    monitorOptionResults: PaginatedData<MonitorOption>;
    focusMonitor: {
        id: number;
        name: string;
    } | null;
    formDefaults: MaintenanceFormData;
};

export default function MaintenancePage({
    summary,
    active,
    upcoming,
    history,
    monitorOptions,
    monitorOptionQuery,
    monitorOptionResults,
    focusMonitor,
    formDefaults,
}: MaintenancePageProps) {
    const form = useForm<MaintenanceFormData>(formDefaults);
    const errors = Object.values(form.errors);
    const [monitorSearch, setMonitorSearch] = useState(monitorOptionQuery);

    const submitMonitorSearch = (query: string) => {
        router.get(
            '/maintenance',
            {
                ...(focusMonitor ? { monitor_id: focusMonitor.id } : {}),
                history_page: history.currentPage,
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
            <Head title="Change windows" />
            <div className="space-y-5">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h1 className="text-[40px] font-semibold tracking-[-0.06em] text-white lg:text-[46px]">
                            Change windows<span className="text-[#7c8cff]">.</span>
                        </h1>
                        <div className="mt-2 max-w-[760px] text-[16px] text-[#9ca7b9]">
                            Schedule planned work, associate affected checks, and keep upcoming maintenance visible across the dashboard and public status pages.
                        </div>
                        {focusMonitor ? (
                            <div className="mt-3 inline-flex items-center gap-2 rounded-[14px] border border-[#7c8cff]/20 bg-[#171c33] px-4 py-2 text-sm text-[#dbe1ff]">
                                Scheduling for
                                <span className="font-semibold text-white">{focusMonitor.name}</span>
                            </div>
                        ) : null}
                    </div>
                    <div className="grid gap-3 sm:grid-cols-3">
                        <PageCard className="px-5 py-4">
                            <div className="text-sm text-[#9ca7b9]">Active</div>
                            <div className="mt-1 text-[30px] font-semibold text-white">{summary.active}</div>
                        </PageCard>
                        <PageCard className="px-5 py-4">
                            <div className="text-sm text-[#9ca7b9]">Upcoming</div>
                            <div className="mt-1 text-[30px] font-semibold text-white">{summary.upcoming}</div>
                        </PageCard>
                        <PageCard className="px-5 py-4">
                            <div className="text-sm text-[#9ca7b9]">History</div>
                            <div className="mt-1 text-[30px] font-semibold text-white">{summary.history}</div>
                        </PageCard>
                    </div>
                </div>

                <PageCard className="space-y-5 p-6">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <div className="text-[20px] font-semibold text-white">Monitor browser</div>
                            <div className="mt-1 text-[14px] text-[#9ca7b9]">
                                Showing {monitorOptionResults.from ?? 0}-{monitorOptionResults.to ?? 0} of {monitorOptionResults.total} checks for the maintenance forms on this page.
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
                        <CalendarRange className="size-5 text-[#7c8cff]" />
                        Schedule maintenance window
                    </div>
                    <form
                        className="grid gap-4"
                        onSubmit={(event: FormEvent) => {
                            event.preventDefault();
                            form.post('/maintenance-windows', {
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
                                <span className="text-[15px] text-[#dce6fb]">Title</span>
                                <input
                                    value={form.data.title}
                                    onChange={(event) => form.setData('title', event.target.value)}
                                    className="h-11 w-full rounded-[14px] border border-white/10 bg-[#0b1425] px-4 text-sm text-white outline-none"
                                    placeholder="Database upgrade"
                                />
                            </label>
                            <label className="flex items-center gap-3 rounded-[16px] border border-white/8 bg-[#171d28] px-4 py-3 text-sm text-[#dce6fb]">
                                <input
                                    type="checkbox"
                                    checked={form.data.notify_contacts}
                                    onChange={(event) => form.setData('notify_contacts', event.target.checked)}
                                    className="size-4 rounded border-white/15 bg-[#121821]"
                                />
                                Notify email contacts
                            </label>
                        </div>
                        <div className="grid gap-4 lg:grid-cols-2">
                            <label className="space-y-2">
                                <span className="text-[15px] text-[#dce6fb]">Starts at</span>
                                <input
                                    type="datetime-local"
                                    value={form.data.starts_at}
                                    onChange={(event) => form.setData('starts_at', event.target.value)}
                                    className="h-11 w-full rounded-[14px] border border-white/10 bg-[#0b1425] px-4 text-sm text-white outline-none"
                                />
                            </label>
                            <label className="space-y-2">
                                <span className="text-[15px] text-[#dce6fb]">Ends at</span>
                                <input
                                    type="datetime-local"
                                    value={form.data.ends_at}
                                    onChange={(event) => form.setData('ends_at', event.target.value)}
                                    className="h-11 w-full rounded-[14px] border border-white/10 bg-[#0b1425] px-4 text-sm text-white outline-none"
                                />
                            </label>
                        </div>
                        <label className="space-y-2">
                            <span className="text-[15px] text-[#dce6fb]">Message</span>
                            <textarea
                                value={form.data.message}
                                onChange={(event) => form.setData('message', event.target.value)}
                                className="min-h-[96px] w-full rounded-[14px] border border-white/10 bg-[#0b1425] px-4 py-3 text-sm text-white outline-none"
                            />
                        </label>
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
                                        {focusMonitor?.id === monitor.id ? (
                                            <span className="rounded-full bg-[#7c8cff]/12 px-2 py-0.5 text-[11px] text-[#7c8cff]">
                                                selected
                                            </span>
                                        ) : null}
                                    </label>
                                );
                            })}
                        </div>
                        <div className="flex justify-end">
                            <button type="submit" className="inline-flex items-center gap-2 rounded-[14px] bg-[#7c8cff] px-4 py-2.5 text-sm font-medium text-white">
                                <Save className="size-4" />
                                Create window
                            </button>
                        </div>
                    </form>
                </PageCard>

                <section className="space-y-4">
                    {active.length > 0 ? (
                        <div className="space-y-4">
                            <div className="text-[20px] font-semibold text-white">
                                Active now
                                {summary.active > active.length ? ` • showing ${active.length} of ${summary.active}` : ''}
                            </div>
                            {active.map((window) => (
                                <MaintenanceEditor key={window.id} window={window} monitorOptions={monitorOptions} />
                            ))}
                        </div>
                    ) : null}

                    <div className="space-y-4">
                        <div className="text-[20px] font-semibold text-white">
                            Upcoming
                            {summary.upcoming > upcoming.length ? ` • showing ${upcoming.length} of ${summary.upcoming}` : ''}
                        </div>
                        {upcoming.length === 0 ? (
                            <PageCard className="p-6 text-[15px] text-[#9ca7b9]">
                                No upcoming maintenance windows.
                            </PageCard>
                        ) : (
                            upcoming.map((window) => (
                                <MaintenanceEditor key={window.id} window={window} monitorOptions={monitorOptions} />
                            ))
                        )}
                    </div>

                    <div className="space-y-4">
                        <div className="text-[20px] font-semibold text-white">History</div>
                        {history.data.length === 0 ? (
                            <PageCard className="p-6 text-[15px] text-[#9ca7b9]">
                                Completed maintenance windows will appear here.
                            </PageCard>
                        ) : (
                            <>
                                {history.data.map((window) => (
                                    <MaintenanceEditor key={window.id} window={window} monitorOptions={monitorOptions} />
                                ))}
                                <PaginationStrip
                                    currentPage={history.currentPage}
                                    lastPage={history.lastPage}
                                    from={history.from}
                                    to={history.to}
                                    total={history.total}
                                    previousPageUrl={history.previousPageUrl}
                                    nextPageUrl={history.nextPageUrl}
                                />
                            </>
                        )}
                    </div>
                </section>
            </div>
        </MonitoringLayout>
    );
}

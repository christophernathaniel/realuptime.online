import { Head, router, useForm } from '@inertiajs/react';
import { CalendarRange, Save, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { PageCard } from '@/components/monitoring/page-card';
import MonitoringLayout from '@/layouts/monitoring-layout';
import type {
    MaintenanceFormData,
    MaintenanceWindowItem,
    MonitorOption,
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
                    <div className="mt-2 text-[15px] text-[#8fa0bf]">
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
                    <label className="flex items-center gap-3 rounded-[16px] border border-white/8 bg-[#111a2c] px-4 py-3 text-sm text-[#dce6fb]">
                        <input
                            type="checkbox"
                            checked={form.data.notify_contacts}
                            onChange={(event) => form.setData('notify_contacts', event.target.checked)}
                            className="size-4 rounded border-white/15 bg-[#091426]"
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
                            <label key={monitor.id} className="flex items-center gap-3 rounded-[14px] border border-white/8 bg-[#111a2c] px-4 py-3 text-sm text-[#dce6fb]">
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
                                    className="size-4 rounded border-white/15 bg-[#091426]"
                                />
                                <span className="min-w-0 flex-1 truncate">{monitor.name}</span>
                            </label>
                        );
                    })}
                </div>
                <div className="flex justify-end">
                    <button type="submit" className="inline-flex items-center gap-2 rounded-[14px] bg-[#352ef6] px-4 py-2.5 text-sm font-medium text-white">
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
    history: MaintenanceWindowItem[];
    monitorOptions: MonitorOption[];
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
    focusMonitor,
    formDefaults,
}: MaintenancePageProps) {
    const form = useForm<MaintenanceFormData>(formDefaults);
    const errors = Object.values(form.errors);

    return (
        <MonitoringLayout>
            <Head title="Maintenance" />
            <div className="space-y-5">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h1 className="text-[40px] font-semibold tracking-[-0.06em] text-white lg:text-[46px]">
                            Maintenance<span className="text-[#3ee072]">.</span>
                        </h1>
                        <div className="mt-2 max-w-[760px] text-[16px] text-[#8fa0bf]">
                            Schedule planned work, associate affected monitors, and keep upcoming maintenance visible across the dashboard and public status pages.
                        </div>
                        {focusMonitor ? (
                            <div className="mt-3 inline-flex items-center gap-2 rounded-[14px] border border-[#3ee072]/20 bg-[#10273a] px-4 py-2 text-sm text-[#dfffe9]">
                                Scheduling for
                                <span className="font-semibold text-white">{focusMonitor.name}</span>
                            </div>
                        ) : null}
                    </div>
                    <div className="grid gap-3 sm:grid-cols-3">
                        <PageCard className="px-5 py-4">
                            <div className="text-sm text-[#8fa0bf]">Active</div>
                            <div className="mt-1 text-[30px] font-semibold text-white">{summary.active}</div>
                        </PageCard>
                        <PageCard className="px-5 py-4">
                            <div className="text-sm text-[#8fa0bf]">Upcoming</div>
                            <div className="mt-1 text-[30px] font-semibold text-white">{summary.upcoming}</div>
                        </PageCard>
                        <PageCard className="px-5 py-4">
                            <div className="text-sm text-[#8fa0bf]">History</div>
                            <div className="mt-1 text-[30px] font-semibold text-white">{summary.history}</div>
                        </PageCard>
                    </div>
                </div>

                <PageCard className="space-y-5 p-6">
                    <div className="flex items-center gap-3 text-[22px] font-semibold text-white">
                        <CalendarRange className="size-5 text-[#3ee072]" />
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
                            <label className="flex items-center gap-3 rounded-[16px] border border-white/8 bg-[#111a2c] px-4 py-3 text-sm text-[#dce6fb]">
                                <input
                                    type="checkbox"
                                    checked={form.data.notify_contacts}
                                    onChange={(event) => form.setData('notify_contacts', event.target.checked)}
                                    className="size-4 rounded border-white/15 bg-[#091426]"
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
                                    <label key={monitor.id} className="flex items-center gap-3 rounded-[14px] border border-white/8 bg-[#111a2c] px-4 py-3 text-sm text-[#dce6fb]">
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
                                            className="size-4 rounded border-white/15 bg-[#091426]"
                                        />
                                        <span className="min-w-0 flex-1 truncate">{monitor.name}</span>
                                        {focusMonitor?.id === monitor.id ? (
                                            <span className="rounded-full bg-[#3ee072]/12 px-2 py-0.5 text-[11px] text-[#3ee072]">
                                                selected
                                            </span>
                                        ) : null}
                                    </label>
                                );
                            })}
                        </div>
                        <div className="flex justify-end">
                            <button type="submit" className="inline-flex items-center gap-2 rounded-[14px] bg-[#352ef6] px-4 py-2.5 text-sm font-medium text-white">
                                <Save className="size-4" />
                                Create window
                            </button>
                        </div>
                    </form>
                </PageCard>

                <section className="space-y-4">
                    {active.length > 0 ? (
                        <div className="space-y-4">
                            <div className="text-[20px] font-semibold text-white">Active now</div>
                            {active.map((window) => (
                                <MaintenanceEditor key={window.id} window={window} monitorOptions={monitorOptions} />
                            ))}
                        </div>
                    ) : null}

                    <div className="space-y-4">
                        <div className="text-[20px] font-semibold text-white">Upcoming</div>
                        {upcoming.length === 0 ? (
                            <PageCard className="p-6 text-[15px] text-[#8fa0bf]">
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
                        {history.length === 0 ? (
                            <PageCard className="p-6 text-[15px] text-[#8fa0bf]">
                                Completed maintenance windows will appear here.
                            </PageCard>
                        ) : (
                            history.map((window) => (
                                <MaintenanceEditor key={window.id} window={window} monitorOptions={monitorOptions} />
                            ))
                        )}
                    </div>
                </section>
            </div>
        </MonitoringLayout>
    );
}

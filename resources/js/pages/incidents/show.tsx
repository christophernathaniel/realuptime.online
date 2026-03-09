import { Head, Link, router, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { PageCard } from '@/components/monitoring/page-card';
import MonitoringLayout from '@/layouts/monitoring-layout';
import type { IncidentCheckSummary, IncidentDetail } from '@/types/monitoring';

type IncidentShowProps = {
    incident: IncidentDetail;
};

function CheckSummaryCard({
    title,
    check,
}: {
    title: string;
    check: IncidentCheckSummary | null;
}) {
    return (
        <PageCard className="p-6">
            <div className="text-[17px] text-[#d5def3]">{title}</div>
            {check ? (
                <div className="mt-4 space-y-2 text-sm text-[#8fa0bf]">
                    <div className="text-[18px] font-semibold text-white">{check.status}</div>
                    <div>{check.checkedAt}</div>
                    <div>Response time: {check.responseTime}</div>
                    <div>HTTP status: {check.httpStatus ?? 'n/a'}</div>
                    <div>{check.error ?? 'No error recorded.'}</div>
                </div>
            ) : (
                <div className="mt-4 text-sm text-[#8fa0bf]">No check recorded.</div>
            )}
        </PageCard>
    );
}

export default function IncidentShow({ incident }: IncidentShowProps) {
    const form = useForm({
        operator_notes: incident.operatorNotes,
        root_cause_summary: incident.rootCauseSummary,
    });

    return (
        <MonitoringLayout>
            <Head title={`${incident.typeLabel} incident`} />
            <div className="space-y-5">
                <div className="flex flex-wrap items-center gap-4">
                    <Link
                        href="/incidents"
                        className="inline-flex items-center gap-3 rounded-[16px] border border-white/6 bg-[#1a2339]/95 px-4 py-2.5 text-base text-[#d5def3]"
                    >
                        Incidents
                    </Link>
                    <Link
                        href={incident.monitorUrl}
                        className="inline-flex items-center gap-3 rounded-[16px] border border-white/6 bg-[#1a2339]/95 px-4 py-2.5 text-base text-[#d5def3]"
                    >
                        {incident.monitorName ?? 'Monitor'}
                    </Link>
                </div>

                <div className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                    <div>
                        <h1 className="text-[40px] font-semibold tracking-[-0.06em] text-white lg:text-[46px]">
                            {incident.typeLabel}<span className="text-[#3ee072]">.</span>
                        </h1>
                        <div className="mt-3 flex flex-wrap items-center gap-3 text-sm text-[#8fa0bf]">
                            <span className="rounded-full bg-[#0d1628] px-3 py-1 text-[#9bb4ff]">{incident.severityLabel}</span>
                            <span className="rounded-full bg-[#261826] px-3 py-1 text-[#ffd4d7]">{incident.statusLabel}</span>
                            <span>{incident.startedAt} to {incident.endedAt}</span>
                            <span>{incident.duration}</span>
                        </div>
                        <div className="mt-4 max-w-[880px] text-[17px] text-[#dce6fb]">{incident.reason}</div>
                    </div>
                    <button
                        type="button"
                        className="inline-flex items-center gap-2 self-start rounded-[14px] border border-[#ff6269]/25 bg-[#231320] px-4 py-2.5 text-sm font-medium text-[#ffd4d7]"
                        onClick={() => {
                            if (window.confirm(`Delete this incident for ${incident.monitorName ?? 'this monitor'}?`)) {
                                router.delete(`/incidents/${incident.id}`);
                            }
                        }}
                    >
                        <Trash2 className="size-4" />
                        Delete incident
                    </button>
                </div>

                <div className="grid gap-4 xl:grid-cols-3">
                    <CheckSummaryCard title="First failed check" check={incident.firstFailedCheck} />
                    <CheckSummaryCard title="Last good check" check={incident.lastGoodCheck} />
                    <CheckSummaryCard title="Latest check" check={incident.latestCheck} />
                </div>

                <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
                    <section className="space-y-5">
                        <PageCard className="p-7">
                            <div className="text-[24px] font-semibold tracking-[-0.05em] text-white">
                                Retry timeline<span className="text-[#3ee072]">.</span>
                            </div>
                            <div className="mt-5 space-y-4">
                                {incident.timeline.length === 0 ? (
                                    <div className="rounded-[18px] bg-[#111a2c] px-5 py-5 text-sm text-[#8fa0bf]">
                                        No retry data recorded for this incident.
                                    </div>
                                ) : (
                                    incident.timeline.map((entry) => (
                                        <div key={`${entry.checkedAt}-${entry.attemptLabel}`} className="rounded-[18px] bg-[#111a2c] px-5 py-5">
                                            <div className="flex flex-wrap items-center justify-between gap-3">
                                                <div className="text-[16px] font-semibold text-white">{entry.attemptLabel}</div>
                                                <div className="text-sm text-[#8fa0bf]">{entry.checkedAt}</div>
                                            </div>
                                            <div className="mt-3 grid gap-2 text-sm text-[#8fa0bf] md:grid-cols-2">
                                                <div>Status: {entry.status}</div>
                                                <div>Response time: {entry.responseTime}</div>
                                                <div>HTTP status: {entry.httpStatus ?? 'n/a'}</div>
                                                <div>{entry.error ?? 'No error recorded.'}</div>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </PageCard>

                        <PageCard className="p-7">
                            <div className="text-[24px] font-semibold tracking-[-0.05em] text-white">
                                Notes &amp; root cause<span className="text-[#3ee072]">.</span>
                            </div>
                            <form
                                className="mt-5 space-y-4"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    form.put(`/incidents/${incident.id}`, { preserveScroll: true });
                                }}
                            >
                                <label className="block space-y-2">
                                    <span className="text-sm text-[#dce6fb]">Operator notes</span>
                                    <textarea
                                        value={form.data.operator_notes}
                                        onChange={(event) => form.setData('operator_notes', event.target.value)}
                                        className="min-h-[140px] w-full rounded-[16px] border border-white/10 bg-[#0b1425] px-4 py-3 text-sm text-white outline-none"
                                    />
                                </label>
                                <label className="block space-y-2">
                                    <span className="text-sm text-[#dce6fb]">Root cause summary</span>
                                    <textarea
                                        value={form.data.root_cause_summary}
                                        onChange={(event) => form.setData('root_cause_summary', event.target.value)}
                                        className="min-h-[140px] w-full rounded-[16px] border border-white/10 bg-[#0b1425] px-4 py-3 text-sm text-white outline-none"
                                    />
                                </label>
                                {Object.values(form.errors).length > 0 ? (
                                    <div className="rounded-[14px] border border-[#ff6269]/20 bg-[#2a1621] px-3 py-3 text-sm text-[#ffd4d7]">
                                        {Object.values(form.errors).join(' ')}
                                    </div>
                                ) : null}
                                <button
                                    type="submit"
                                    className="inline-flex items-center gap-2 rounded-[14px] bg-[#352ef6] px-4 py-2.5 text-sm font-medium text-white"
                                >
                                    <Save className="size-4" />
                                    Save notes
                                </button>
                            </form>
                        </PageCard>
                    </section>

                    <aside className="space-y-5">
                        <PageCard className="p-6">
                            <div className="text-[20px] font-semibold text-white">
                                Notification history<span className="text-[#3ee072]">.</span>
                            </div>
                            <div className="mt-5 space-y-4">
                                {incident.notificationHistory.length === 0 ? (
                                    <div className="text-sm text-[#8fa0bf]">No notifications were recorded for this incident.</div>
                                ) : (
                                    incident.notificationHistory.map((entry) => (
                                        <div key={`${entry.sentAt}-${entry.subject}`} className="rounded-[16px] bg-[#111a2c] px-4 py-4">
                                            <div className="flex items-center justify-between gap-3 text-sm text-white">
                                                <span>{entry.type}</span>
                                                <span className="text-[#3ee072]">{entry.status}</span>
                                            </div>
                                            <div className="mt-2 text-sm text-[#dce6fb]">{entry.subject}</div>
                                            <div className="mt-2 text-xs text-[#8fa0bf]">{entry.contact}</div>
                                            <div className="mt-1 text-xs text-[#667590]">{entry.sentAt}</div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </PageCard>
                    </aside>
                </div>
            </div>
        </MonitoringLayout>
    );
}

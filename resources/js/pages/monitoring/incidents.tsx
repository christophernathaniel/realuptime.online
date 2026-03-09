import { Head, Link, router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { PageCard } from '@/components/monitoring/page-card';
import MonitoringLayout from '@/layouts/monitoring-layout';

type IncidentsPageProps = {
    summary: {
        open: number;
        resolved: number;
        last7Days: number;
    };
    incidents: Array<{
        id: number;
        monitor: string;
        monitorUrl: string;
        showUrl: string;
        startedAt: string;
        endedAt: string;
        duration: string;
        reason: string;
        status: string;
        typeLabel: string;
        severityLabel: string;
        capabilities: string[];
    }>;
};

export default function IncidentsPage({ summary, incidents }: IncidentsPageProps) {
    return (
        <MonitoringLayout>
            <Head title="Event log" />
            <div className="space-y-6">
                <div>
                    <div className="text-[11px] font-semibold uppercase tracking-[0.26em] text-[#7f8b9b]">
                        Incident response
                    </div>
                    <h1 className="text-[56px] font-semibold tracking-[-0.06em] text-white">
                        Event log<span className="text-[#7c8cff]">.</span>
                    </h1>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <PageCard className="p-7">
                        <div className="text-lg text-[#9ca7b9]">Open</div>
                        <div className="mt-3 text-[52px] font-semibold tracking-[-0.05em] text-white">{summary.open}</div>
                    </PageCard>
                    <PageCard className="p-7">
                        <div className="text-lg text-[#9ca7b9]">Resolved</div>
                        <div className="mt-3 text-[52px] font-semibold tracking-[-0.05em] text-white">{summary.resolved}</div>
                    </PageCard>
                    <PageCard className="p-7">
                        <div className="text-lg text-[#9ca7b9]">Last 7 days</div>
                        <div className="mt-3 text-[52px] font-semibold tracking-[-0.05em] text-white">{summary.last7Days}</div>
                    </PageCard>
                </div>

                <PageCard className="p-8">
                    <div className="space-y-4">
                        {incidents.length === 0 ? (
                            <div className="text-[18px] text-[#9ca7b9]">No incidents have been recorded yet.</div>
                        ) : (
                            incidents.map((incident) => (
                                <div key={incident.id} className="rounded-[20px] bg-[#171d28] px-5 py-5 transition hover:bg-[#162139]">
                                    <div className="flex flex-wrap items-center justify-between gap-3 text-[20px] text-white">
                                        <span className="flex flex-wrap items-center gap-3">
                                            <Link href={incident.monitorUrl} className="hover:text-[#7c8cff]">
                                                {incident.monitor}
                                            </Link>
                                            <span className="rounded-full bg-[#121821] px-3 py-1 text-[12px] text-[#9bb4ff]">
                                                {incident.typeLabel}
                                            </span>
                                            <span className="rounded-full bg-[#261826] px-3 py-1 text-[12px] text-[#ffd4d7]">
                                                {incident.severityLabel}
                                            </span>
                                        </span>
                                        <div className="flex items-center gap-4">
                                            <div className="text-[#7c8cff]">{incident.status}</div>
                                            <button
                                                type="button"
                                                className="inline-flex items-center gap-1.5 text-[14px] text-[#ff9ca3] transition hover:text-white"
                                                onClick={() => {
                                                    if (window.confirm(`Delete incident "${incident.reason}"?`)) {
                                                        router.delete(`/incidents/${incident.id}`, { preserveScroll: true });
                                                    }
                                                }}
                                            >
                                                <Trash2 className="size-4" />
                                                Delete
                                            </button>
                                            <Link href={incident.showUrl} className="text-[14px] text-[#9bb4ff] hover:text-white">
                                                View details
                                            </Link>
                                        </div>
                                    </div>
                                    <div className="mt-2 text-[18px] text-[#d9e3f8]">{incident.reason}</div>
                                    {incident.capabilities.length > 0 ? (
                                        <div className="mt-3 flex flex-wrap gap-2">
                                            {incident.capabilities.map((capability) => (
                                                <span key={`${incident.id}-${capability}`} className="rounded-full bg-[#121821] px-3 py-1 text-[12px] text-[#cfd8ec]">
                                                    {capability}
                                                </span>
                                            ))}
                                        </div>
                                    ) : null}
                                    <div className="mt-2 text-[16px] text-[#9ca7b9]">
                                        {incident.startedAt} to {incident.endedAt} • {incident.duration}
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </PageCard>
            </div>
        </MonitoringLayout>
    );
}

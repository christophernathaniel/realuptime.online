import { Head } from '@inertiajs/react';
import { usePageAutoRefresh } from '@/hooks/use-page-auto-refresh';
import { cn } from '@/lib/utils';
import type { PublicStatusPageData } from '@/types/monitoring';

const tones = {
    up: {
        badge: 'bg-[#57c7c2]/15 text-[#57c7c2] border-[#57c7c2]/25',
        dot: 'bg-[#57c7c2]',
    },
    down: {
        badge: 'bg-[#ff7a72]/15 text-[#ffb2ad] border-[#ff7a72]/25',
        dot: 'bg-[#ff7a72]',
    },
    warning: {
        badge: 'bg-[#7c8cff]/15 text-[#d0d8ff] border-[#7c8cff]/25',
        dot: 'bg-[#7c8cff]',
    },
    maintenance: {
        badge: 'bg-[#7483a5]/15 text-[#bfc9da] border-[#7483a5]/25',
        dot: 'bg-[#7483a5]',
    },
};

type PublicStatusPageProps = {
    statusPage: PublicStatusPageData;
};

export default function PublicStatusPage({ statusPage }: PublicStatusPageProps) {
    const tone = tones[statusPage.overallTone];

    usePageAutoRefresh({ only: ['statusPage'], intervalMs: 30000 });

    return (
        <div className="min-h-screen bg-[#0d1117] px-4 py-8 text-[#f4f7ff] sm:px-6 lg:px-8">
            <Head title={statusPage.headline} />
            <div className="mx-auto max-w-[1180px] space-y-6">
                <div className="rounded-[28px] border border-[#2b3544] bg-[#171d28] px-7 py-8">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div className="text-sm uppercase tracking-[0.26em] text-[#7f8eab]">Public status page</div>
                            <h1 className="mt-3 text-[38px] font-semibold tracking-[-0.06em] text-white lg:text-[46px]">{statusPage.headline}</h1>
                            {statusPage.description ? (
                                <div className="mt-3 max-w-[720px] text-[16px] text-[#9eacc7]">{statusPage.description}</div>
                            ) : null}
                        </div>
                        <div className={cn('inline-flex items-center gap-3 rounded-full border px-4 py-2 text-sm font-medium', tone.badge)}>
                            <span className={cn('inline-flex size-2.5 rounded-full', tone.dot)} />
                            {statusPage.overallStatus}
                        </div>
                    </div>
                    <div className="mt-5 text-sm text-[#7f8eab]">{statusPage.updatedLabel}</div>
                </div>

                <section className="rounded-[24px] border border-[#2b3544] bg-[#171d28] p-6">
                    <div className="text-[22px] font-semibold text-white">Monitored services</div>
                    <div className="mt-5 grid gap-4 md:grid-cols-2">
                        {statusPage.monitors.map((monitor) => (
                            <div key={monitor.name} className="rounded-[18px] bg-[#121821] px-5 py-5">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <div className="text-[19px] font-semibold text-white">{monitor.name}</div>
                                        <div className="mt-1 text-[14px] text-[#7f8eab]">{monitor.type}</div>
                                    </div>
                                    <div
                                        className={cn(
                                            'inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium',
                                            monitor.statusTone === 'up' && 'bg-[#57c7c2]/15 text-[#57c7c2]',
                                            monitor.statusTone === 'down' && 'bg-[#ff7a72]/15 text-[#ffb2ad]',
                                            monitor.statusTone === 'warning' && 'bg-[#7c8cff]/15 text-[#d0d8ff]',
                                            monitor.statusTone === 'maintenance' && 'bg-[#7483a5]/15 text-[#bfc9da]',
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                'inline-flex size-2 rounded-full',
                                                monitor.statusTone === 'up' && 'bg-[#57c7c2]',
                                                monitor.statusTone === 'down' && 'bg-[#ff7a72]',
                                                monitor.statusTone === 'warning' && 'bg-[#7c8cff]',
                                                monitor.statusTone === 'maintenance' && 'bg-[#7483a5]',
                                            )}
                                        />
                                        {monitor.status}
                                    </div>
                                </div>
                                <div className="mt-4 grid grid-cols-3 gap-3 text-[13px] text-[#8fa0bf]">
                                    <div><div>24h uptime</div><div className="mt-1 text-[16px] font-semibold text-white">{monitor.uptimeLabel}</div></div>
                                    <div><div>Last checked</div><div className="mt-1 text-[16px] font-semibold text-white">{monitor.lastCheckedLabel}</div></div>
                                    <div><div>Response</div><div className="mt-1 text-[16px] font-semibold text-white">{monitor.responseTimeLabel}</div></div>
                                </div>
                                {monitor.statusDetail ? (
                                    <div className="mt-3 text-[13px] text-[#9badca]">{monitor.statusDetail}</div>
                                ) : null}
                                {monitor.activeMaintenance ? (
                                    <div className="mt-4 rounded-[14px] bg-[#7483a5]/12 px-4 py-3 text-[13px] text-[#c7d0df]">This monitor currently has an active maintenance window.</div>
                                ) : null}
                            </div>
                        ))}
                    </div>
                </section>

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
                    <section className="rounded-[24px] border border-[#2b3544] bg-[#171d28] p-6">
                        <div className="text-[22px] font-semibold text-white">Status updates</div>
                        <div className="mt-5 space-y-4">
                            {statusPage.incidents.length === 0 ? (
                                <div className="rounded-[18px] bg-[#121821] px-5 py-5 text-[15px] text-[#9ca7b9]">
                                    No public incident posts have been published.
                                </div>
                            ) : (
                                statusPage.incidents.map((incident) => (
                                    <div key={`${incident.title}-${incident.startedAt}`} className="rounded-[18px] bg-[#121821] px-5 py-5">
                                        <div className="flex flex-wrap items-center justify-between gap-3 text-[15px] text-white">
                                            <span>{incident.title}</span>
                                            <span className={incident.status === 'Resolved' ? 'text-[#57c7c2]' : 'text-[#7c8cff]'}>{incident.status}</span>
                                        </div>
                                        <div className="mt-2 flex flex-wrap gap-2 text-[12px] text-[#a7b6cb]">
                                            <span className="rounded-full bg-[#171d28] px-3 py-1">{incident.impact}</span>
                                            {incident.monitors.map((monitor) => (
                                                <span key={monitor} className="rounded-full bg-[#171d28] px-3 py-1">{monitor}</span>
                                            ))}
                                        </div>
                                        {incident.message ? <div className="mt-3 text-[15px] text-[#dce6fb]">{incident.message}</div> : null}
                                        <div className="mt-2 text-[13px] text-[#7f8eab]">{incident.startedAt} to {incident.endedAt}</div>
                                        <div className="mt-4 space-y-3">
                                            {incident.updates.map((update, index) => (
                                                <div key={`${update.createdAt}-${index}`} className="rounded-[14px] bg-[#171d28] px-4 py-4">
                                                    <div className="flex items-center justify-between gap-3 text-[14px] text-white">
                                                        <span>{update.status}</span>
                                                        <span className="text-[#7f8eab]">{update.createdAt}</span>
                                                    </div>
                                                    <div className="mt-2 text-[14px] text-[#dce6fb]">{update.message}</div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </section>

                    <aside className="space-y-6">
                        <div className="rounded-[24px] border border-[#2b3544] bg-[#171d28] p-6">
                            <div className="text-[22px] font-semibold text-white">Recent updates</div>
                            <div className="mt-5 space-y-4">
                                {statusPage.recentUpdates.length === 0 ? (
                                    <div className="rounded-[18px] bg-[#121821] px-5 py-5 text-[15px] text-[#9ca7b9]">
                                        No operator updates yet.
                                    </div>
                                ) : (
                                    statusPage.recentUpdates.map((update, index) => (
                                        <div key={`${update.incidentTitle}-${index}`} className="rounded-[18px] bg-[#121821] px-5 py-5">
                                            <div className="flex items-center justify-between gap-3 text-[15px] text-white">
                                                <span>{update.incidentTitle}</span>
                                                <span className="text-[#7f8eab]">{update.createdAt}</span>
                                            </div>
                                            <div className="mt-2 text-[13px] text-[#a7b6cb]">{update.status} • {update.impact}</div>
                                            <div className="mt-2 text-[14px] text-[#dce6fb]">{update.message}</div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </div>

                        <div className="rounded-[24px] border border-[#2b3544] bg-[#171d28] p-6">
                            <div className="text-[22px] font-semibold text-white">Recent monitor incidents</div>
                            <div className="mt-5 space-y-4">
                                {statusPage.monitorIncidents.length === 0 ? (
                                    <div className="rounded-[18px] bg-[#121821] px-5 py-5 text-[15px] text-[#9ca7b9]">
                                        No monitor-detected incidents reported.
                                    </div>
                                ) : (
                                    statusPage.monitorIncidents.map((incident) => (
                                        <div key={`${incident.monitor}-${incident.startedAt}`} className="rounded-[18px] bg-[#121821] px-5 py-5">
                                            <div className="flex items-center justify-between gap-3 text-[15px] text-white">
                                                <span>{incident.monitor}</span>
                                                <span className={incident.status === 'Resolved' ? 'text-[#57c7c2]' : 'text-[#7c8cff]'}>{incident.status}</span>
                                            </div>
                                            <div className="mt-2 text-[15px] text-[#dce6fb]">{incident.reason}</div>
                                            <div className="mt-2 text-[13px] text-[#7f8eab]">{incident.startedAt} to {incident.endedAt}</div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </div>

                        <div className="rounded-[24px] border border-white/6 bg-[#101a2f] p-6">
                            <div className="text-[22px] font-semibold text-white">Maintenance</div>
                            <div className="mt-5 space-y-4">
                                {statusPage.maintenance.length === 0 ? (
                                    <div className="rounded-[18px] bg-[#111a2c] px-5 py-5 text-[15px] text-[#8fa0bf]">
                                        No scheduled maintenance windows.
                                    </div>
                                ) : (
                                    statusPage.maintenance.map((window) => (
                                        <div key={window.id} className="rounded-[18px] bg-[#111a2c] px-5 py-5">
                                            <div className="flex items-center justify-between gap-3 text-[15px] text-white">
                                                <span>{window.title}</span>
                                                <span className="text-[#9bb4ff]">{window.status}</span>
                                            </div>
                                            {window.message ? <div className="mt-2 text-[14px] text-[#dce6fb]">{window.message}</div> : null}
                                            <div className="mt-2 text-[13px] text-[#7f8eab]">{window.startsAt} to {window.endsAt}</div>
                                            <div className="mt-1 text-[13px] text-[#7f8eab]">{window.monitorNames.join(', ')}</div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    );
}

import { Head, router } from '@inertiajs/react';
import { Laptop2, LogOut, ShieldCheck } from 'lucide-react';
import { PageCard } from '@/components/monitoring/page-card';
import { Button } from '@/components/ui/button';
import MonitoringLayout from '@/layouts/monitoring-layout';
import SettingsLayout from '@/layouts/settings/layout';
import {
    surfaceDangerButtonClass,
    surfaceMutedTextClass,
    surfaceSecondaryButtonClass,
} from '@/lib/realuptime-theme';

type SessionItem = {
    id: number;
    sessionId: string;
    deviceLabel: string;
    browser: string;
    platform: string;
    ipAddress: string;
    lastPath: string | null;
    lastActiveAt: string | null;
    lastActiveAgo: string | null;
    isCurrent: boolean;
};

export default function Sessions({ sessions }: { sessions: SessionItem[] }) {
    return (
        <MonitoringLayout>
            <Head title="Sessions" />

            <SettingsLayout
                title="Sessions"
                description="Review every browser and device currently signed in to your account, then end any session you do not trust."
            >
                <PageCard className="p-6 sm:p-7">
                    <div className="space-y-6">
                        <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                            <div>
                                <h2 className="text-[20px] font-semibold tracking-[-0.04em] text-white">
                                    Active sessions
                                </h2>
                                <p className="mt-2 text-[14px] leading-6 text-[#8fa0bf]">
                                    Keep only the devices you recognize. Ending a session removes access until the user signs in again.
                                </p>
                            </div>

                            <Button
                                type="button"
                                variant="outline"
                                className={surfaceSecondaryButtonClass}
                                onClick={() =>
                                    router.delete('/settings/sessions', {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                <LogOut className="size-4" />
                                Log out other sessions
                            </Button>
                        </div>

                        <div className="space-y-3">
                            {sessions.map((session) => (
                                <div
                                    key={session.id}
                                    className="rounded-[22px] border border-white/8 bg-[#101b2f]/85 p-4"
                                >
                                    <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                        <div className="space-y-2">
                                            <div className="flex flex-wrap items-center gap-2 text-[15px] font-semibold text-white">
                                                <Laptop2 className="size-4 text-[#8fa0bf]" />
                                                {session.deviceLabel}
                                                {session.isCurrent ? (
                                                    <span className="inline-flex items-center gap-1 rounded-full border border-[#3ee072]/25 bg-[#10273a] px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-[#dfffe9]">
                                                        <ShieldCheck className="size-3" />
                                                        Current session
                                                    </span>
                                                ) : null}
                                            </div>
                                            <div className={surfaceMutedTextClass}>
                                                {session.browser} on {session.platform} • {session.ipAddress}
                                            </div>
                                            <div className={surfaceMutedTextClass}>
                                                Last active {session.lastActiveAgo ?? session.lastActiveAt ?? 'Unknown'}
                                            </div>
                                            {session.lastPath ? (
                                                <div className="text-[12px] text-[#7081a2]">
                                                    Last path: /{session.lastPath}
                                                </div>
                                            ) : null}
                                        </div>

                                        <Button
                                            type="button"
                                            variant={session.isCurrent ? 'destructive' : 'outline'}
                                            className={
                                                session.isCurrent
                                                    ? surfaceDangerButtonClass
                                                    : surfaceSecondaryButtonClass
                                            }
                                            onClick={() =>
                                                router.delete(`/settings/sessions/${session.id}`, {
                                                    preserveScroll: true,
                                                })
                                            }
                                        >
                                            <LogOut className="size-4" />
                                            {session.isCurrent ? 'Log out here' : 'End session'}
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </PageCard>
            </SettingsLayout>
        </MonitoringLayout>
    );
}

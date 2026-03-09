import { Link } from '@inertiajs/react';
import { Activity, BellRing, ShieldCheck } from 'lucide-react';
import type { AuthLayoutProps } from '@/types';

const highlights = [
    {
        icon: Activity,
        label: 'Real-time monitoring',
        detail: 'Track HTTP, SSL, ping, heartbeat, and synthetic transactions from one dashboard.',
    },
    {
        icon: BellRing,
        label: 'Email alerting',
        detail: 'Deliver downtime, degraded performance, maintenance, and recovery alerts to your inbox.',
    },
    {
        icon: ShieldCheck,
        label: 'Incident visibility',
        detail: 'Inspect incidents, retries, response times, and public status communication in one place.',
    },
];

export default function AuthSimpleLayout({
    children,
    title,
    description,
    variant = 'full',
}: AuthLayoutProps) {
    if (variant === 'form-only') {
        return (
            <div className="relative min-h-svh overflow-hidden bg-[#071325] text-white">
                <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_left,#16345a_0%,transparent_35%),radial-gradient(circle_at_bottom_left,#0e5949_0%,transparent_28%),radial-gradient(circle_at_top_right,#14294d_0%,transparent_30%)] opacity-90" />
                <div className="pointer-events-none absolute inset-0 opacity-20 [background-image:linear-gradient(rgba(117,130,160,0.08)_1px,transparent_1px),linear-gradient(90deg,rgba(117,130,160,0.08)_1px,transparent_1px)] [background-size:90px_90px]" />

                <div className="relative mx-auto flex min-h-svh w-full max-w-[640px] items-start px-3 py-3 sm:px-5 sm:py-5 lg:items-center lg:py-8">
                    <section className="w-full rounded-[28px] border border-white/8 bg-[linear-gradient(180deg,rgba(22,32,51,0.96)_0%,rgba(12,22,40,0.98)_100%)] p-5 shadow-[0_30px_80px_rgba(0,0,0,0.32)] sm:p-6 lg:p-8">
                        <div className="space-y-5">
                            <Link href="/" className="inline-flex items-center gap-3 text-white">
                                <span className="inline-flex size-3 rounded-full bg-[#3ee072] shadow-[0_0_24px_rgba(62,224,114,0.55)]" />
                                <span className="text-[24px] font-semibold tracking-[-0.05em] sm:text-[28px]">RealUptime</span>
                            </Link>

                            <div className="space-y-2.5 border-b border-white/8 pb-4 lg:pb-5">
                                <p className="text-[11px] font-semibold uppercase tracking-[0.28em] text-[#6f82a3]">
                                    Account access
                                </p>
                                <div className="space-y-2">
                                    <h2 className="text-[28px] font-semibold tracking-[-0.05em] text-white sm:text-[30px]">
                                        {title}
                                    </h2>
                                    <p className="text-[14px] leading-6 text-[#8fa0bf] sm:text-[15px] sm:leading-7">
                                        {description}
                                    </p>
                                </div>
                            </div>

                            <div>{children}</div>
                        </div>
                    </section>
                </div>
            </div>
        );
    }

    return (
        <div className="relative min-h-svh overflow-hidden bg-[#071325] text-white">
            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_left,#16345a_0%,transparent_35%),radial-gradient(circle_at_bottom_left,#0e5949_0%,transparent_28%),radial-gradient(circle_at_top_right,#14294d_0%,transparent_30%)] opacity-90" />
            <div className="pointer-events-none absolute inset-0 opacity-20 [background-image:linear-gradient(rgba(117,130,160,0.08)_1px,transparent_1px),linear-gradient(90deg,rgba(117,130,160,0.08)_1px,transparent_1px)] [background-size:90px_90px]" />

            <div className="relative mx-auto flex min-h-svh w-full max-w-[1480px] flex-col gap-4 px-3 py-3 sm:px-5 sm:py-5 lg:flex-row lg:items-stretch lg:gap-7 lg:px-8 lg:py-8">
                <section className="order-2 relative flex flex-1 overflow-hidden rounded-[28px] border border-white/8 bg-[linear-gradient(180deg,rgba(17,30,53,0.94)_0%,rgba(9,21,40,0.96)_100%)] p-5 shadow-[0_30px_80px_rgba(0,0,0,0.32)] sm:p-6 lg:order-1 lg:min-h-[680px] lg:p-9">
                    <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_bottom_left,rgba(62,224,114,0.12)_0%,transparent_28%),radial-gradient(circle_at_top_right,rgba(47,67,255,0.16)_0%,transparent_32%)]" />
                    <div className="relative flex w-full flex-col gap-6 lg:justify-between lg:gap-9">
                        <div className="space-y-6">
                            <Link href="/" className="inline-flex items-center gap-3 text-white">
                                <span className="inline-flex size-3 rounded-full bg-[#3ee072] shadow-[0_0_24px_rgba(62,224,114,0.55)]" />
                                <span className="text-[24px] font-semibold tracking-[-0.05em] sm:text-[28px]">RealUptime</span>
                            </Link>

                            <div className="max-w-[640px] space-y-4">
                                <span className="inline-flex rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.28em] text-[#8fa0bf]">
                                    Monitoring workspace
                                </span>
                                <div className="space-y-3">
                                    <h1 className="max-w-[11ch] text-[34px] font-semibold leading-[0.98] tracking-[-0.06em] text-white sm:text-[44px] lg:text-[56px]">
                                        Reliable uptime, clear incident history.
                                    </h1>
                                    <p className="max-w-[560px] text-[14px] leading-6 text-[#9fb0cf] sm:text-[15px] sm:leading-7">
                                        Manage monitors, notifications, maintenance windows, and public status communication from the same RealUptime workspace.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-3 lg:gap-4">
                            {highlights.map((highlight) => {
                                const Icon = highlight.icon;

                                return (
                                    <div
                                        key={highlight.label}
                                        className="rounded-[20px] border border-white/8 bg-[#101b2f]/90 p-4 shadow-[inset_0_1px_0_rgba(255,255,255,0.02)]"
                                    >
                                        <span className="mb-3 inline-flex size-10 items-center justify-center rounded-2xl border border-white/10 bg-[#0a1324] text-[#3ee072]">
                                            <Icon className="size-[18px]" />
                                        </span>
                                        <div className="space-y-2">
                                            <h2 className="text-[15px] font-semibold tracking-[-0.03em] text-white">
                                                {highlight.label}
                                            </h2>
                                            <p className="text-[12px] leading-5 text-[#90a0be]">
                                                {highlight.detail}
                                            </p>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </section>

                <section className="order-1 flex w-full items-start lg:order-2 lg:max-w-[500px] lg:items-center">
                    <div className="w-full rounded-[28px] border border-white/8 bg-[linear-gradient(180deg,rgba(22,32,51,0.96)_0%,rgba(12,22,40,0.98)_100%)] p-5 shadow-[0_30px_80px_rgba(0,0,0,0.32)] sm:p-6 lg:p-8">
                        <div className="space-y-2.5 border-b border-white/8 pb-4 lg:pb-5">
                            <p className="text-[11px] font-semibold uppercase tracking-[0.28em] text-[#6f82a3]">
                                Account access
                            </p>
                            <div className="space-y-2">
                                <h2 className="text-[28px] font-semibold tracking-[-0.05em] text-white sm:text-[30px]">
                                    {title}
                                </h2>
                                <p className="text-[14px] leading-6 text-[#8fa0bf] sm:text-[15px] sm:leading-7">
                                    {description}
                                </p>
                            </div>
                        </div>

                        <div className="pt-4 lg:pt-5">{children}</div>
                    </div>
                </section>
            </div>
        </div>
    );
}

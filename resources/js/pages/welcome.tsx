import { Head, Link, usePage } from '@inertiajs/react';
import { Activity, BellRing, Globe2, ServerCog, ShieldCheck } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import type { Auth } from '@/types/auth';

export default function Welcome({ canRegister = true }: { canRegister?: boolean }) {
    const { auth } = usePage<{ auth: Auth }>().props;

    return (
        <>
            <Head title="RealUptime" />
            <div className="min-h-screen bg-[#0d1117] text-[#f4f7ff]">
                <div className="mx-auto flex min-h-screen max-w-[1280px] flex-col px-5 py-6 lg:px-7">
                    <header className="flex items-center justify-between gap-4">
                        <div className="flex items-center gap-3 text-[30px] font-semibold tracking-[-0.05em]">
                            <AppLogoIcon className="size-[28px]" />
                            RealUptime
                        </div>
                        <nav className="flex items-center gap-3">
                            {auth.user ? (
                                <Link href="/dashboard" className="rounded-[16px] bg-[#7c8cff] px-5 py-2.5 text-sm font-medium text-white">
                                    Open dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link href="/login" className="rounded-[16px] border border-white/10 px-5 py-2.5 text-sm text-[#dce6fb]">
                                        Log in
                                    </Link>
                                    {canRegister ? (
                                        <Link href="/register" className="rounded-[16px] bg-[#7c8cff] px-5 py-2.5 text-sm font-medium text-white">
                                            Create account
                                        </Link>
                                    ) : null}
                                </>
                            )}
                        </nav>
                    </header>

                    <main className="grid flex-1 items-center gap-10 py-10 lg:grid-cols-[1.1fr_0.9fr] lg:py-16">
                        <section>
                            <div className="inline-flex items-center gap-2 rounded-full border border-white/8 bg-white/4 px-4 py-2 text-sm text-[#9db0d2]">
                                <BellRing className="size-4 text-[#57c7c2]" />
                                Queued checks, email alerts, public status pages, maintenance windows
                            </div>
                            <h1 className="mt-6 max-w-[760px] text-[48px] font-semibold leading-[0.95] tracking-[-0.07em] text-white sm:text-[62px] lg:text-[76px]">
                                Uptime monitoring built for live deployment, not just screenshots.
                            </h1>
                            <p className="mt-6 max-w-[680px] text-[18px] leading-8 text-[#9db0d2]">
                                RealUptime monitors websites, HTTP endpoints, TCP ports, and ping targets. It records incidents, publishes status pages, schedules maintenance windows, and delivers alert emails through queued workers.
                            </p>
                            <div className="mt-8 flex flex-wrap gap-3">
                                <Link href={auth.user ? '/dashboard' : '/login'} className="rounded-[18px] bg-[#7c8cff] px-6 py-3.5 text-base font-medium text-white">
                                    {auth.user ? 'Go to monitoring' : 'Log in to dashboard'}
                                </Link>
                                {canRegister && !auth.user ? (
                                    <Link href="/register" className="rounded-[18px] border border-white/10 px-6 py-3.5 text-base text-[#dce6fb]">
                                        Create free account
                                    </Link>
                                ) : null}
                            </div>
                        </section>

                        <section className="grid gap-4">
                            <div className="rounded-[28px] border border-[#2b3544] bg-[#171d28] p-6">
                                <div className="text-[24px] font-semibold tracking-[-0.04em] text-white">What’s included</div>
                                <div className="mt-5 grid gap-3">
                                    {[
                                        ['HTTP, port, and ping checks with incidents, timelines, and alerting', Activity],
                                        ['Incident timelines, uptime analytics, and response-time charts', ShieldCheck],
                                        ['Published status pages, status updates, and scheduled maintenance', Globe2],
                                        ['Queued email notifications, tokens, and shared workspaces', BellRing],
                                    ].map(([label, Icon]) => {
                                        const EntryIcon = Icon as typeof Activity;
                                        return (
                                            <div key={label as string} className="flex items-center gap-3 rounded-[18px] bg-[#121821] px-4 py-4 text-[15px] text-[#dce6fb]">
                                                <EntryIcon className="size-4 text-[#57c7c2]" />
                                                <span>{label as string}</span>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                            <div className="rounded-[28px] border border-[#2b3544] bg-[linear-gradient(135deg,#1a202c_0%,#121821_100%)] p-6">
                                <div className="flex items-center gap-2 text-[18px] text-[#dce6fb]">
                                    <ServerCog className="size-5 text-[#7c8cff]" />
                                    Operational visibility
                                </div>
                                <div className="mt-4 space-y-3 text-[14px] text-[#9db0d2]">
                                    <div>1. Track websites, HTTP endpoints, TCP ports, and infrastructure from one shared workspace.</div>
                                    <div>2. Capture incident history with retries, root cause notes, and notification timelines.</div>
                                    <div>3. Publish public status pages and maintenance updates without a separate tool.</div>
                                    <div>4. Send alert emails and paid-plan downtime webhooks when monitors fail.</div>
                                </div>
                            </div>
                        </section>
                    </main>
                </div>
            </div>
        </>
    );
}

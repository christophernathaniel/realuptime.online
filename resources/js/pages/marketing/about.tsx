import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import MarketingLayout from '@/layouts/marketing-layout';

export default function AboutPage() {
    return (
        <MarketingLayout title="About" description="Learn what RealUptime is building and how the product is designed.">
            <section className="mx-auto max-w-[1380px] px-6 py-16 lg:px-10 lg:py-22">
                <div className="max-w-[980px]">
                    <div className="text-[14px] font-semibold uppercase tracking-[0.22em] text-[#7083a2]">About</div>
                    <h1 className="mt-5 text-[40px] font-semibold leading-[0.9] tracking-[-0.08em] text-white sm:text-[52px] lg:text-[66px]">
                        Built for teams that need the signal fast and the context immediately after<span className="text-[#7c8cff]">.</span>
                    </h1>
                    <p className="mt-6 max-w-[44ch] text-[21px] leading-9 text-[#98aac6]">
                        RealUptime is designed around one principle: when something breaks, detection, alerting, incident context, and public communication should already be connected.
                    </p>
                </div>

                <div className="mt-12 grid gap-5 lg:grid-cols-3">
                    {[
                        {
                            title: 'Operational clarity',
                            copy: 'Checks, alerts, incidents, and status updates should tell the same story instead of forcing teams to reconcile different tools.',
                        },
                        {
                            title: 'Useful defaults',
                            copy: 'The product focuses on the checks teams actually use in production: websites, HTTP endpoints, TCP ports, ping, incidents, and public communication.',
                        },
                        {
                            title: 'Room to scale',
                            copy: 'The queue, scheduler, and notification model are built so the product can move from a small workspace to serious production use without being rewritten.',
                        },
                    ].map((card) => (
                        <div key={card.title} className="rounded-[30px] border border-white/8 bg-[#0a1730] p-7">
                            <div className="text-[24px] font-semibold tracking-[-0.06em] text-white">{card.title}</div>
                            <div className="mt-4 text-[17px] leading-8 text-[#8ea0bf]">{card.copy}</div>
                        </div>
                    ))}
                </div>

                <div className="mt-12 rounded-[34px] border border-white/8 bg-[linear-gradient(120deg,#0a1730_0%,#07111f_100%)] p-8 lg:p-10">
                    <div className="grid gap-6 lg:grid-cols-3">
                        {[
                            ['Detect', 'Monitor websites, HTTP endpoints, TCP ports, and ping targets from one workspace.'],
                            ['Respond', 'Escalate failures with email alerts, critical downtime thresholds, and webhook delivery for paid plans.'],
                            ['Communicate', 'Keep incident records and publish public status updates without splitting the workflow across tools.'],
                        ].map(([title, copy]) => (
                            <div key={title}>
                                <div className="text-[14px] font-semibold uppercase tracking-[0.22em] text-[#6f82a3]">{title}</div>
                                <div className="mt-3 text-[24px] font-semibold tracking-[-0.05em] text-white">{title} with intent<span className="text-[#7c8cff]">.</span></div>
                                <div className="mt-3 text-[17px] leading-8 text-[#8ea0bf]">{copy}</div>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="mt-12 flex flex-wrap gap-4">
                    <Link href="/pricing" className="inline-flex h-14 items-center justify-center rounded-full bg-[#7c8cff] px-6 text-[17px] font-semibold text-[#10151d] transition hover:bg-[#95a3ff]">
                        Compare plans
                    </Link>
                    <Link href="/features" className="inline-flex h-14 items-center justify-center gap-2 rounded-full border border-white/12 px-6 text-[17px] font-medium text-white transition hover:bg-white/6">
                        Explore features
                        <ArrowRight className="size-4" />
                    </Link>
                </div>
            </section>
        </MarketingLayout>
    );
}

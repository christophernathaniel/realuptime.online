import { Link } from '@inertiajs/react';
import { ArrowRight, BellRing, Gauge, Globe2, ServerCog } from 'lucide-react';
import { FaqAccordion } from '@/components/marketing/faq-accordion';
import { FeatureIcon } from '@/components/marketing/feature-icon';
import { PricingTable } from '@/components/marketing/pricing-table';
import MarketingLayout from '@/layouts/marketing-layout';
import { marketingFaqs, marketingFeatures } from '@/lib/marketing-content';
import { cn } from '@/lib/utils';

export default function MarketingHome({ canRegister = true }: { canRegister?: boolean }) {
    return (
        <MarketingLayout
            title="RealUptime"
            description="Monitor websites, TCP ports, ping targets, incidents, and public status pages from one operational workspace."
        >
            <section className="mx-auto grid max-w-[1380px] gap-12 px-6 py-14 lg:grid-cols-[1.05fr_0.95fr] lg:px-10 lg:py-22">
                <div>
                    <div className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-[13px] font-semibold uppercase tracking-[0.22em] text-[#9db0d2]">
                        Real-time monitoring and alerting
                    </div>
                    <h1 className="mt-7 max-w-[11ch] text-[42px] font-semibold leading-[0.9] tracking-[-0.08em] text-white sm:text-[52px] lg:text-[66px]">
                        Catch downtime before your users do<span className="text-[#7c8cff]">.</span>
                    </h1>
                    <p className="mt-6 max-w-[34ch] text-[20px] leading-9 text-[#9eb0cb] sm:text-[22px]">
                        RealUptime brings website checks, port checks, ping monitoring, incident management, public status pages, and alerting into one live operational product.
                    </p>
                    <div className="mt-8 flex flex-wrap gap-4">
                        <Link href={canRegister ? '/register' : '/login'} className="inline-flex h-14 items-center justify-center rounded-full bg-[#7c8cff] px-6 text-[17px] font-semibold text-[#10151d] transition hover:bg-[#95a3ff]">
                            Get Started for free
                        </Link>
                        <Link href="/pricing" className="inline-flex h-14 items-center justify-center gap-2 rounded-full border border-white/12 px-6 text-[17px] font-medium text-white transition hover:bg-white/6">
                            See pricing
                            <ArrowRight className="size-4" />
                        </Link>
                    </div>
                    <div className="mt-10 grid gap-4 sm:grid-cols-3">
                        {[
                            ['10 free', 'Included monitors on the Free plan'],
                            ['30 sec', 'Fastest paid check interval'],
                            ['Email + webhooks', 'Alerting for operators and tools'],
                        ].map(([value, label]) => (
                            <div key={value} className="rounded-[24px] border border-white/8 bg-white/4 px-5 py-5">
                                <div className="text-[28px] font-semibold tracking-[-0.05em] text-white">{value}</div>
                                <div className="mt-2 text-[15px] leading-7 text-[#8ea0bf]">{label}</div>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="grid gap-4 lg:pt-10">
                    <div className="rounded-[34px] border border-white/8 bg-[#0a1730] p-6">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <div className="text-[14px] font-semibold uppercase tracking-[0.22em] text-[#7083a2]">Live overview</div>
                                <div className="mt-2 text-[28px] font-semibold tracking-[-0.05em] text-white">Production API<span className="text-[#7c8cff]">.</span></div>
                            </div>
                            <div className="rounded-full bg-[#1b2242] px-4 py-2 text-[13px] font-semibold uppercase tracking-[0.2em] text-[#7c8cff]">Operational</div>
                        </div>
                        <div className="mt-8 grid gap-4 sm:grid-cols-3">
                            <div className="rounded-[22px] bg-[#0f1d38] px-5 py-5">
                                <div className="text-[15px] text-[#8ea0bf]">Current status</div>
                                <div className="mt-3 text-[34px] font-semibold tracking-[-0.06em] text-[#7c8cff]">Up</div>
                                <div className="mt-2 text-[14px] text-[#8ea0bf]">Healthy for 16h 42m</div>
                            </div>
                            <div className="rounded-[22px] bg-[#0f1d38] px-5 py-5">
                                <div className="text-[15px] text-[#8ea0bf]">Last 24 hours</div>
                                <div className="mt-3 flex gap-1">
                                    {Array.from({ length: 24 }).map((_, index) => (
                                        <span key={index} className="h-9 w-2 rounded-full bg-[#7c8cff]" />
                                    ))}
                                </div>
                                <div className="mt-2 text-[14px] text-[#8ea0bf]">100% uptime</div>
                            </div>
                            <div className="rounded-[22px] bg-[#0f1d38] px-5 py-5">
                                <div className="text-[15px] text-[#8ea0bf]">Alerts</div>
                                <div className="mt-3 text-[34px] font-semibold tracking-[-0.06em] text-white">3</div>
                                <div className="mt-2 text-[14px] text-[#8ea0bf]">Email, critical escalation, webhook</div>
                            </div>
                        </div>
                    </div>
                    <div className="grid gap-4 md:grid-cols-3">
                        {[
                            {
                                icon: Globe2,
                                title: 'Website checks',
                                copy: 'Track landing pages, sign-in, and any HTTP(S) endpoint.',
                            },
                            {
                                icon: ServerCog,
                                title: 'Port checks',
                                copy: 'Validate raw TCP services like databases, SSH, or brokers.',
                            },
                            {
                                icon: BellRing,
                                title: 'Incident alerting',
                                copy: 'Escalate downtime, performance issues, and recoveries fast.',
                            },
                        ].map(({ icon: Icon, title, copy }) => (
                            <div key={title} className="rounded-[28px] border border-white/8 bg-[linear-gradient(180deg,#0b1831_0%,#091426_100%)] px-5 py-6">
                                <Icon className="size-6 text-[#7c8cff]" />
                                <div className="mt-5 text-[22px] font-semibold tracking-[-0.05em] text-white">{title}</div>
                                <div className="mt-2 text-[15px] leading-7 text-[#8ea0bf]">{copy}</div>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            <section className="mx-auto max-w-[1380px] px-6 py-8 lg:px-10 lg:py-12">
                <div className="grid gap-10 xl:grid-cols-[340px_minmax(0,1fr)] xl:items-start">
                    <div className="xl:sticky xl:top-28">
                        <div className="text-[14px] font-semibold uppercase tracking-[0.22em] text-[#7083a2]">Platform blocks</div>
                        <h2 className="mt-4 text-[28px] font-semibold leading-[0.92] tracking-[-0.07em] text-white sm:text-[36px] lg:text-[46px]">
                            One operating layer for checks, alerts, incident records, and public comms<span className="text-[#7c8cff]">.</span>
                        </h2>
                        <p className="mt-5 max-w-[32ch] text-[18px] leading-8 text-[#95a8c5] sm:text-[20px]">
                            This is the practical shape of the product: distinct workflows that share the same live state instead of a generic icon grid.
                        </p>
                        <div className="mt-6 rounded-[28px] border border-white/8 bg-[#0b1425] px-5 py-5">
                            <div className="text-[13px] font-semibold uppercase tracking-[0.22em] text-[#7083a2]">What teams use first</div>
                            <div className="mt-4 space-y-3 text-[16px] text-[#d8e1f4]">
                                <div>Detect failures and slowdowns</div>
                                <div>Escalate with context and timing</div>
                                <div>Publish one public source of truth</div>
                            </div>
                        </div>
                        <Link href="/features" className="mt-6 inline-flex h-12 items-center gap-2 rounded-full border border-white/12 px-5 text-[15px] font-medium text-white transition hover:bg-white/6">
                            Explore all features
                            <ArrowRight className="size-4" />
                        </Link>
                    </div>

                    <div className="grid gap-5 md:grid-cols-2">
                        {marketingFeatures.slice(0, 6).map((feature, index) => (
                            <Link
                                key={feature.slug}
                                href={`/features/${feature.slug}`}
                                className={cn(
                                    'group rounded-[30px] border border-white/8 bg-[linear-gradient(180deg,#0a1730_0%,#08111f_100%)] p-6 transition hover:border-[#7c8cff]/30 hover:bg-[#0c1934]',
                                    index === 1 && 'md:translate-y-8',
                                    index === 4 && 'md:-translate-y-4',
                                )}
                            >
                                <div className="flex items-start justify-between gap-4">
                                    <div className="text-[12px] font-semibold uppercase tracking-[0.24em] text-[#7f93b3]">
                                        {String(index + 1).padStart(2, '0')}
                                    </div>
                                    <div className="inline-flex size-12 items-center justify-center rounded-[18px] border border-[#17335d] bg-[#091223] text-[#7c8cff]">
                                        <FeatureIcon icon={feature.icon} className="size-5 shrink-0" />
                                    </div>
                                </div>
                                <div className="mt-10 text-[26px] font-semibold tracking-[-0.06em] text-white">{feature.shortLabel}</div>
                                <div className="mt-3 text-[16px] leading-7 text-[#8ea0bf]">{feature.menuDescription}</div>
                                <div className="mt-5 flex flex-wrap gap-2">
                                    {feature.useCases.slice(0, 2).map((useCase) => (
                                        <span key={useCase} className="rounded-full border border-white/10 bg-white/[0.04] px-3 py-1.5 text-[12px] text-[#cbd6ed]">
                                            {useCase}
                                        </span>
                                    ))}
                                </div>
                                <div className="mt-8 inline-flex items-center gap-2 text-[15px] font-medium text-[#7c8cff]">
                                    Open workflow
                                    <ArrowRight className="size-4 transition group-hover:translate-x-1" />
                                </div>
                            </Link>
                        ))}
                    </div>
                </div>
            </section>

            <section className="mx-auto max-w-[1380px] px-6 py-10 lg:px-10 lg:py-14">
                <div className="overflow-hidden rounded-[36px] border border-white/8 bg-[linear-gradient(120deg,#081121_0%,#08172e_50%,#0a1b31_100%)] p-8 lg:p-12">
                    <div className="grid gap-10 lg:grid-cols-[0.94fr_1.06fr] lg:items-center">
                        <div>
                            <div className="text-[14px] font-semibold uppercase tracking-[0.22em] text-[#7083a2]">Public status pages</div>
                            <h2 className="mt-4 text-[28px] font-semibold leading-[0.92] tracking-[-0.07em] text-white sm:text-[34px] lg:text-[42px]">
                                Publish uptime, incidents, and maintenance from the same workspace<span className="text-[#7c8cff]">.</span>
                            </h2>
                            <p className="mt-5 max-w-[34ch] text-[19px] leading-8 text-[#96a7c3]">
                                Create branded public status pages that stay aligned with live monitor state, incident posts, and maintenance windows without another service in the stack.
                            </p>
                            <div className="mt-8 space-y-4">
                                {[
                                    'Per-user public status pages with clean URLs',
                                    'Manual incident posts and lifecycle updates',
                                    'Maintenance windows included in the public timeline',
                                    'Clear uptime bars and current service health',
                                ].map((item) => (
                                    <div key={item} className="flex items-center gap-3 text-[18px] text-white">
                                        <span className="inline-flex size-8 items-center justify-center rounded-full bg-[#1a2140] text-[#7c8cff]">
                                            <Gauge className="size-4" />
                                        </span>
                                        {item}
                                    </div>
                                ))}
                            </div>
                            <div className="mt-9 flex flex-wrap gap-4">
                                <Link href="/features/public-status-pages" className="inline-flex h-14 items-center justify-center rounded-full border border-[#7c8cff] px-6 text-[17px] font-medium text-[#7c8cff] transition hover:bg-[#7c8cff] hover:text-[#10151d]">
                                    Learn more
                                </Link>
                                <Link href="/pricing" className="inline-flex h-14 items-center justify-center rounded-full border border-white/12 px-6 text-[17px] font-medium text-white transition hover:bg-white/6">
                                    Compare plans
                                </Link>
                            </div>
                        </div>
                        <div className="relative min-h-[360px] rounded-[32px] border border-white/8 bg-[radial-gradient(circle_at_center,rgba(124,140,255,0.08),transparent_35%),linear-gradient(180deg,#0b1730_0%,#081326_100%)] p-6 sm:min-h-[420px]">
                            <div className="absolute right-6 top-6 rotate-[-12deg] rounded-[24px] border border-white/8 bg-[#0e1a31] px-5 py-4">
                                <div className="text-[12px] font-semibold uppercase tracking-[0.22em] text-[#7083a2]">Public page</div>
                                <div className="mt-3 flex items-center gap-3 rounded-[18px] bg-[#132542] px-4 py-4 text-[18px] font-semibold text-white">
                                    <span className="inline-flex size-4 rounded-full bg-[#7c8cff]" />
                                    All systems operational
                                </div>
                            </div>
                            <div className="absolute bottom-8 left-6 right-10 rounded-[28px] border border-white/8 bg-[#0f1a30] p-6">
                                <div className="flex items-center justify-between gap-4">
                                    <div>
                                        <div className="text-[13px] font-semibold uppercase tracking-[0.22em] text-[#7083a2]">Uptime</div>
                                        <div className="mt-2 text-[24px] font-semibold tracking-[-0.05em] text-white">Last 24 hours</div>
                                    </div>
                                    <div className="text-[32px] font-semibold tracking-[-0.06em] text-[#7c8cff]">100%</div>
                                </div>
                                <div className="mt-5 flex gap-1">
                                    {Array.from({ length: 30 }).map((_, index) => (
                                        <span key={index} className="h-10 w-2 rounded-full bg-[#7c8cff]" />
                                    ))}
                                </div>
                                <div className="mt-6 grid gap-4 md:grid-cols-2">
                                    <div className="rounded-[18px] bg-[#111f39] px-4 py-4 text-[14px] text-[#92a4c1]">No incidents in the selected range.</div>
                                    <div className="rounded-[18px] bg-[#111f39] px-4 py-4 text-[14px] text-[#92a4c1]">Next maintenance: none scheduled.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section className="bg-[#f4f6fb] py-16 text-[#16233c] lg:py-22">
                <div className="mx-auto max-w-[1380px] px-6 lg:px-10">
                    <div className="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <div className="text-[14px] font-semibold uppercase tracking-[0.22em] text-[#62728f]">Pricing</div>
                            <h2 className="mt-4 max-w-[11ch] text-[30px] font-semibold leading-[0.92] tracking-[-0.07em] text-[#16233c] sm:text-[38px] lg:text-[46px]">
                                Honest pricing built around the product that ships<span className="text-[#7c8cff]">.</span>
                            </h2>
                        </div>
                        <p className="max-w-[42ch] text-[18px] leading-8 text-[#5d6d89]">
                            Start free with 10 monitors, unlock full check customization with Premium, and scale to larger estates with Ultra. No hidden feature bundles, no separate toolchain.
                        </p>
                    </div>
                    <div className="mt-10">
                        <PricingTable canRegister={canRegister} />
                    </div>
                </div>
            </section>

            <section id="faqs" className="mx-auto max-w-[1380px] px-6 py-14 lg:px-10 lg:py-18">
                <FaqAccordion items={marketingFaqs} />
            </section>
        </MarketingLayout>
    );
}

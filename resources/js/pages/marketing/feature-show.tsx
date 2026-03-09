import { Link } from '@inertiajs/react';
import { ArrowRight, Check } from 'lucide-react';
import { FaqAccordion } from '@/components/marketing/faq-accordion';
import { FeatureIcon } from '@/components/marketing/feature-icon';
import MarketingLayout from '@/layouts/marketing-layout';
import { marketingFeatureMap } from '@/lib/marketing-content';
import type { FeatureSlug } from '@/lib/marketing-content';

export default function FeatureShowPage({ slug, canRegister = true }: { slug: FeatureSlug; canRegister?: boolean }) {
    const feature = marketingFeatureMap[slug];

    return (
        <MarketingLayout title={feature.label} description={feature.summary}>
            <section className="mx-auto max-w-[1380px] px-6 py-16 lg:px-10 lg:py-22">
                <div className="grid gap-10 lg:grid-cols-[0.92fr_1.08fr] lg:items-end">
                    <div>
                        <div className="text-[14px] font-semibold uppercase tracking-[0.22em] text-[#7083a2]">{feature.eyebrow}</div>
                        <h1 className="mt-5 text-[40px] font-semibold leading-[0.9] tracking-[-0.08em] text-white sm:text-[50px] lg:text-[62px]">
                            {feature.title}
                        </h1>
                    </div>
                    <p className="max-w-[40ch] text-[20px] leading-9 text-[#98abc7]">{feature.summary}</p>
                </div>

                <div className="mt-10 grid gap-6 lg:grid-cols-[0.95fr_1.05fr]">
                    <div className="rounded-[34px] border border-white/8 bg-[linear-gradient(180deg,#091428_0%,#07111f_100%)] p-8">
                        <span className="inline-flex size-16 items-center justify-center rounded-[24px] border border-[#18325a] bg-[#091223] text-[#7c8cff]">
                            <FeatureIcon icon={feature.icon} className="size-7 shrink-0" />
                        </span>
                        <p className="mt-7 max-w-[36ch] text-[19px] leading-9 text-[#95a8c5]">{feature.intro}</p>
                        <div className="mt-8 space-y-4">
                            {feature.bullets.map((bullet) => (
                                <div key={bullet} className="flex items-start gap-3 text-[18px] leading-7 text-white">
                                    <span className="mt-1 inline-flex size-7 items-center justify-center rounded-full bg-[#1b2242] text-[#7c8cff]">
                                        <Check className="size-4" />
                                    </span>
                                    {bullet}
                                </div>
                            ))}
                        </div>
                    </div>
                    <div className="grid gap-5 sm:grid-cols-2">
                        {feature.benefitCards.map((card) => (
                            <div key={card.title} className="rounded-[30px] border border-white/8 bg-[#0a1730] p-7">
                                <div className="text-[24px] font-semibold tracking-[-0.06em] text-white">{card.title}</div>
                                <div className="mt-4 text-[17px] leading-8 text-[#8ea0bf]">{card.description}</div>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            <section className="mx-auto max-w-[1380px] px-6 py-8 lg:px-10 lg:py-12">
                <div className="grid gap-6 lg:grid-cols-[0.88fr_1.12fr]">
                    <div className="rounded-[34px] border border-white/8 bg-[#0a1427] p-8">
                        <div className="text-[14px] font-semibold uppercase tracking-[0.22em] text-[#7083a2]">Best fit</div>
                        <h2 className="mt-4 text-[27px] font-semibold leading-[0.92] tracking-[-0.06em] text-white sm:text-[34px]">
                            Common use cases<span className="text-[#7c8cff]">.</span>
                        </h2>
                        <div className="mt-7 space-y-4">
                            {feature.useCases.map((item) => (
                                <div key={item} className="rounded-[22px] bg-[#101c33] px-5 py-4 text-[18px] text-[#d7e0f2]">
                                    {item}
                                </div>
                            ))}
                        </div>
                    </div>
                    <div className="rounded-[34px] border border-white/8 bg-[linear-gradient(120deg,#0a1730_0%,#07111f_100%)] p-8">
                        <div className="text-[14px] font-semibold uppercase tracking-[0.22em] text-[#7083a2]">Next step</div>
                        <h2 className="mt-4 max-w-[14ch] text-[30px] font-semibold leading-[0.92] tracking-[-0.07em] text-white sm:text-[36px] lg:text-[42px]">
                            Use this feature in the same workspace as your alerts and incidents<span className="text-[#7c8cff]">.</span>
                        </h2>
                        <p className="mt-5 max-w-[34ch] text-[19px] leading-9 text-[#97aac6]">
                            RealUptime is designed so that monitor checks, incident records, alert delivery, and public communication stay tied together instead of spreading across separate tools.
                        </p>
                        <div className="mt-8 flex flex-wrap gap-4">
                            <Link href="/pricing" className="inline-flex h-14 items-center justify-center rounded-full bg-[#7c8cff] px-6 text-[17px] font-semibold text-[#10151d] transition hover:bg-[#95a3ff]">
                                Compare plans
                            </Link>
                            <Link
                                href={canRegister ? '/register' : '/login'}
                                className="inline-flex h-14 items-center justify-center gap-2 rounded-full border border-white/12 px-6 text-[17px] font-medium text-white transition hover:bg-white/6"
                            >
                                Get started
                                <ArrowRight className="size-4" />
                            </Link>
                        </div>
                    </div>
                </div>
            </section>

            <section className="mx-auto max-w-[1380px] px-6 py-12 lg:px-10 lg:py-16">
                <FaqAccordion items={feature.faqs} />
            </section>
        </MarketingLayout>
    );
}

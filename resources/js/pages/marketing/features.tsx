import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { FeatureIcon } from '@/components/marketing/feature-icon';
import MarketingLayout from '@/layouts/marketing-layout';
import { marketingFeatures } from '@/lib/marketing-content';

export default function FeaturesPage() {
    return (
        <MarketingLayout title="Features" description="Explore RealUptime monitoring, alerting, incidents, and public status page features.">
            <section className="mx-auto max-w-[1380px] px-6 py-16 lg:px-10 lg:py-22">
                <div className="max-w-[920px]">
                    <div className="text-[14px] font-semibold uppercase tracking-[0.22em] text-[#6f82a3]">Features</div>
                    <h1 className="mt-5 text-[40px] font-semibold leading-[0.9] tracking-[-0.08em] text-white sm:text-[50px] lg:text-[62px]">
                        Monitoring, alerting, incidents, and public communication from one product<span className="text-[#42df79]">.</span>
                    </h1>
                    <p className="mt-6 max-w-[46ch] text-[20px] leading-9 text-[#97aac6]">
                        RealUptime covers the operational loop end to end: detect failures, record incidents, notify responders, and publish status updates without switching tools.
                    </p>
                </div>
                <div className="mt-12 grid gap-5 lg:grid-cols-2 xl:grid-cols-3">
                    {marketingFeatures.map((feature) => (
                        <Link
                            key={feature.slug}
                            href={`/features/${feature.slug}`}
                            className="group rounded-[30px] border border-white/8 bg-[linear-gradient(180deg,#0a1730_0%,#071121_100%)] p-7 transition hover:-translate-y-1 hover:border-[#42df79]/35"
                        >
                            <span className="inline-flex size-14 items-center justify-center rounded-[20px] border border-[#18325a] bg-[#091223] text-[#42df79]">
                                <FeatureIcon icon={feature.icon} className="size-6 shrink-0" />
                            </span>
                            <div className="mt-8 text-[28px] font-semibold tracking-[-0.06em] text-white">{feature.label}</div>
                            <div className="mt-3 text-[17px] leading-8 text-[#8ea0bf]">{feature.summary}</div>
                            <div className="mt-6 inline-flex items-center gap-2 text-[16px] font-medium text-[#42df79]">
                                Explore feature
                                <ArrowRight className="size-4 transition group-hover:translate-x-1" />
                            </div>
                        </Link>
                    ))}
                </div>
            </section>
        </MarketingLayout>
    );
}

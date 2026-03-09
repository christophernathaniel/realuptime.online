import { FaqAccordion } from '@/components/marketing/faq-accordion';
import { PricingTable } from '@/components/marketing/pricing-table';
import MarketingLayout from '@/layouts/marketing-layout';
import { marketingFaqs } from '@/lib/marketing-content';

export default function PricingPage({ canRegister = true }: { canRegister?: boolean }) {
    return (
        <MarketingLayout title="Pricing" description="Compare Free, Premium, and Ultra plans for RealUptime.">
            <section className="mx-auto max-w-[1380px] px-6 py-16 lg:px-10 lg:py-22">
                <div className="grid gap-10 lg:grid-cols-[1fr_auto] lg:items-start">
                    <div>
                        <div className="text-[14px] font-semibold uppercase tracking-[0.22em] text-[#7083a2]">Pricing</div>
                        <h1 className="mt-5 max-w-[12ch] text-[40px] font-semibold leading-[0.9] tracking-[-0.08em] text-white sm:text-[52px] lg:text-[66px]">
                            Affordable plans for real monitoring work<span className="text-[#42df79]">.</span>
                        </h1>
                    </div>
                    <div className="rounded-[30px] border border-white/8 bg-[#0a1730] px-6 py-6 lg:min-w-[360px]">
                        <div className="text-[30px] font-semibold tracking-[-0.06em] text-white">
                            Simple monthly pricing<span className="text-[#42df79]">.</span>
                        </div>
                        <div className="mt-4 text-[16px] leading-7 text-[#8ea0bf]">
                            All public plans are billed monthly. Upgrade, downgrade, or cancel from the billing settings inside your account.
                        </div>
                    </div>
                </div>
            </section>

            <section className="bg-[#071121] py-4 pb-16 lg:pb-24">
                <div className="mx-auto max-w-[1380px] px-6 lg:px-10">
                    <PricingTable canRegister={canRegister} />
                </div>
            </section>

            <section id="faqs" className="bg-[#f4f6fb] py-16 lg:py-22">
                <div className="mx-auto max-w-[1380px] px-6 lg:px-10">
                    <FaqAccordion items={marketingFaqs} dark={false} />
                </div>
            </section>
        </MarketingLayout>
    );
}

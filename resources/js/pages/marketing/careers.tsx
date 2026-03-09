import MarketingLayout from '@/layouts/marketing-layout';

export default function CareersPage() {
    return (
        <MarketingLayout title="Careers" description="Careers at RealUptime.">
            <section className="mx-auto max-w-[1380px] px-6 py-16 lg:px-10 lg:py-22">
                <div className="max-w-[920px]">
                    <div className="text-[14px] font-semibold uppercase tracking-[0.22em] text-[#7083a2]">Careers</div>
                    <h1 className="mt-5 text-[40px] font-semibold leading-[0.9] tracking-[-0.08em] text-white sm:text-[52px] lg:text-[66px]">
                        No open roles right now<span className="text-[#42df79]">.</span>
                    </h1>
                    <p className="mt-6 max-w-[42ch] text-[21px] leading-9 text-[#97aac6]">
                        RealUptime is not actively hiring at the moment. When that changes, this page will list open roles, hiring context, and what the team is working on.
                    </p>
                </div>
                <div className="mt-12 grid gap-5 lg:grid-cols-3">
                    {[
                        'We care about operational clarity and simple systems that hold up under pressure.',
                        'We value product decisions that are defensible, useful, and grounded in real production needs.',
                        'We prefer tools that reduce confusion rather than adding another panel to watch during an incident.',
                    ].map((copy, index) => (
                        <div key={index} className="rounded-[30px] border border-white/8 bg-[#0a1730] p-7 text-[18px] leading-8 text-[#8ea0bf]">
                            {copy}
                        </div>
                    ))}
                </div>
                <div className="mt-12 rounded-[34px] border border-[#42df79]/22 bg-[#0a1425] px-8 py-8 text-[18px] leading-8 text-[#d9e3f5]">
                    There are currently no vacancies. Check back later for future opportunities across product, engineering, operations, and customer experience.
                </div>
            </section>
        </MarketingLayout>
    );
}

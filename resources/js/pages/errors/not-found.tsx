import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, LifeBuoy, Search, ShieldCheck } from 'lucide-react';
import MarketingLayout from '@/layouts/marketing-layout';
import type { Auth } from '@/types/auth';

type NotFoundPageProps = {
    auth: Auth;
    canRegister?: boolean;
};

const recoveryLinks = [
    {
        title: 'Monitoring dashboard',
        description: 'Return to monitors, incidents, and maintenance workflows.',
        href: '/dashboard',
        icon: Search,
    },
    {
        title: 'Pricing and plans',
        description: 'Review Free, Premium, and Ultra workspace limits.',
        href: '/pricing',
        icon: ShieldCheck,
    },
    {
        title: 'Support and policy pages',
        description: 'Open the public site sections that explain how RealUptime works.',
        href: '/about',
        icon: LifeBuoy,
    },
];

export default function NotFoundPage() {
    const { auth } = usePage<NotFoundPageProps>().props;
    const primaryHref = auth.user ? '/dashboard' : '/';
    const primaryLabel = auth.user ? 'Back to dashboard' : 'Back to homepage';

    return (
        <MarketingLayout
            title="404"
            description="The page you requested could not be found."
            pageClassName="min-h-[calc(100vh-80px)]"
        >
            <Head title="404" />
            <section className="mx-auto max-w-[1380px] px-6 py-16 lg:px-10 lg:py-22">
                <div className="grid gap-10 lg:grid-cols-[1.05fr_0.95fr] lg:items-end">
                    <div className="max-w-[780px]">
                        <div className="text-[14px] font-semibold uppercase tracking-[0.22em] text-[#7083a2]">
                            Error 404
                        </div>
                        <div className="mt-5 text-[82px] font-semibold leading-[0.84] tracking-[-0.09em] text-white sm:text-[110px] lg:text-[148px]">
                            404<span className="text-[#7c8cff]">.</span>
                        </div>
                        <h1 className="mt-5 max-w-[11ch] text-[38px] font-semibold leading-[0.9] tracking-[-0.08em] text-white sm:text-[50px] lg:text-[64px]">
                            This route does not exist in RealUptime<span className="text-[#7c8cff]">.</span>
                        </h1>
                        <p className="mt-6 max-w-[42ch] text-[18px] leading-8 text-[#97aac8] sm:text-[20px] sm:leading-9">
                            The address may be outdated, unpublished, or incorrect. If you followed a public status page link, confirm the page is still published and that the full URL was copied correctly.
                        </p>
                        <div className="mt-8 flex flex-wrap gap-4">
                            <Link
                                href={primaryHref}
                                className="inline-flex h-14 items-center justify-center gap-2 rounded-full bg-[#7c8cff] px-6 text-[16px] font-semibold text-[#10151d] transition hover:bg-[#95a3ff]"
                            >
                                <ArrowLeft className="size-4" />
                                {primaryLabel}
                            </Link>
                            <Link
                                href="/features"
                                className="inline-flex h-14 items-center justify-center gap-2 rounded-full border border-white/12 px-6 text-[16px] font-medium text-white transition hover:bg-white/6"
                            >
                                Explore features
                                <ArrowRight className="size-4" />
                            </Link>
                        </div>
                    </div>

                    <div className="rounded-[34px] border border-white/8 bg-[linear-gradient(145deg,#0b1832_0%,#08111f_100%)] p-8 lg:p-10">
                        <div className="text-[14px] font-semibold uppercase tracking-[0.22em] text-[#7083a2]">
                            Suggested next steps
                        </div>
                        <div className="mt-5 space-y-4">
                            {recoveryLinks.map(({ title, description, href, icon: Icon }) => (
                                <Link
                                    key={title}
                                    href={href}
                                    className="group flex items-start gap-4 rounded-[22px] border border-white/7 bg-white/[0.03] px-5 py-5 transition hover:border-[#7c8cff]/30 hover:bg-white/[0.05]"
                                >
                                    <span className="inline-flex size-12 shrink-0 items-center justify-center rounded-[16px] border border-[#1b345c] bg-[#091122] text-[#7c8cff]">
                                        <Icon className="size-5" />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block text-[20px] font-semibold tracking-[-0.05em] text-white">
                                            {title}
                                        </span>
                                        <span className="mt-2 block text-[15px] leading-7 text-[#8ea0bf]">
                                            {description}
                                        </span>
                                    </span>
                                    <ArrowRight className="mt-1 size-4 shrink-0 text-[#6e82a5] transition group-hover:text-[#7c8cff]" />
                                </Link>
                            ))}
                        </div>
                    </div>
                </div>
            </section>
        </MarketingLayout>
    );
}

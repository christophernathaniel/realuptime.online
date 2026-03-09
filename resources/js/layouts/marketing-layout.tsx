import { Head, Link, usePage } from '@inertiajs/react';
import { ChevronDown, Instagram, Linkedin, Menu, Twitter, X } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { useMemo, useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { FeatureIcon } from '@/components/marketing/feature-icon';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { footerGroups, marketingFeatures } from '@/lib/marketing-content';
import { cn } from '@/lib/utils';
import type { Auth } from '@/types/auth';

type MarketingLayoutProps = PropsWithChildren<{
    title: string;
    description?: string;
    pageClassName?: string;
}>;

type MarketingSharedProps = {
    auth: Auth;
    canRegister?: boolean;
};

const socialButtons = [
    { label: 'X', Icon: Twitter },
    { label: 'LinkedIn', Icon: Linkedin },
    { label: 'Instagram', Icon: Instagram },
];

export default function MarketingLayout({ title, description, children, pageClassName }: MarketingLayoutProps) {
    const { auth, canRegister = true } = usePage<MarketingSharedProps>().props;
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
    const [featuresOpen, setFeaturesOpen] = useState(false);
    const [mobileOpen, setMobileOpen] = useState(false);
    const [cookieChoice, setCookieChoice] = useState<string | null>(() =>
        typeof window === 'undefined' ? null : window.localStorage.getItem('realuptime-cookie-consent'),
    );

    const authLinks = useMemo(() => {
        if (auth.user) {
            return {
                loginHref: '/dashboard',
                loginLabel: 'Open dashboard',
                registerHref: '/dashboard',
                registerLabel: 'Open app',
            };
        }

        return {
            loginHref: '/login',
            loginLabel: 'Login',
            registerHref: '/register',
            registerLabel: 'Get Started for free',
        };
    }, [auth.user]);

    const acceptCookies = (value: 'accepted' | 'declined') => {
        window.localStorage.setItem('realuptime-cookie-consent', value);
        setCookieChoice(value);
    };

    return (
        <>
            <Head title={title}>
                {description ? <meta name="description" content={description} /> : null}
            </Head>
            <div className="min-h-screen bg-[#071326] text-[#f3f7ff]">
                <div className="fixed inset-x-0 top-0 z-50 border-b border-white/8 bg-[#071326]/78 backdrop-blur-xl">
                    <div className="mx-auto flex h-[74px] max-w-[1380px] items-center justify-between gap-5 px-5 lg:px-8">
                        <Link href="/" className="flex items-center gap-3 text-[25px] font-semibold tracking-[-0.05em] text-white">
                            <AppLogoIcon className="size-[26px]" />
                            RealUptime
                        </Link>

                        <nav className="hidden items-center gap-2 lg:flex">
                            <div
                                className="relative"
                                onMouseEnter={() => setFeaturesOpen(true)}
                                onMouseLeave={() => setFeaturesOpen(false)}
                            >
                                <button
                                    type="button"
                                    className={cn(
                                        'inline-flex items-center gap-2 rounded-full px-4 py-2 text-[15px] font-medium transition',
                                        isCurrentOrParentUrl('/features') ? 'bg-white/10 text-white' : 'text-[#a8b5cf] hover:bg-white/8 hover:text-white',
                                    )}
                                >
                                    Features
                                    <ChevronDown className={cn('size-4 transition', featuresOpen && 'rotate-180')} />
                                </button>
                                {featuresOpen ? (
                                    <div className="absolute left-0 top-full w-[720px] pt-2">
                                        <div className="grid gap-3 rounded-[28px] border border-white/8 bg-[#0a1730] p-4">
                                            <div className="grid grid-cols-2 gap-3">
                                                {marketingFeatures.map((feature) => (
                                                    <Link
                                                        key={feature.slug}
                                                        href={`/features/${feature.slug}`}
                                                        className="flex items-start gap-4 rounded-[20px] px-4 py-4 transition hover:bg-white/5"
                                                    >
                                                        <span className="inline-flex size-11 shrink-0 items-center justify-center rounded-[16px] border border-[#1d345e] bg-[#091122] text-[#7c8cff]">
                                                            <FeatureIcon icon={feature.icon} className="size-5 shrink-0" />
                                                        </span>
                                                        <span>
                                                            <span className="block text-[16px] font-semibold tracking-[-0.04em] text-white">{feature.label}</span>
                                                            <span className="mt-1 block text-[13px] leading-6 text-[#8ea0bf]">{feature.menuDescription}</span>
                                                        </span>
                                                    </Link>
                                                ))}
                                            </div>
                                        </div>
                                    </div>
                                ) : null}
                            </div>
                            <Link
                                href="/pricing"
                                className={cn(
                                    'rounded-full px-4 py-2.5 text-[15px] font-medium transition',
                                    isCurrentUrl('/pricing') || isCurrentUrl('/plans') ? 'bg-white/10 text-white' : 'text-[#a8b5cf] hover:bg-white/8 hover:text-white',
                                )}
                            >
                                Pricing
                            </Link>
                        </nav>

                        <div className="hidden items-center gap-3 lg:flex">
                            <Link href={authLinks.loginHref} className="rounded-full px-4 py-2.5 text-[15px] font-medium text-[#d8e2f4] transition hover:bg-white/8">
                                {authLinks.loginLabel}
                            </Link>
                            {canRegister || auth.user ? (
                                <Link
                                    href={authLinks.registerHref}
                                    className="rounded-full bg-[#7c8cff] px-5 py-2.5 text-[15px] font-semibold text-[#10151d] transition hover:bg-[#95a3ff]"
                                >
                                    {authLinks.registerLabel}
                                </Link>
                            ) : null}
                        </div>

                        <button
                            type="button"
                            className="inline-flex size-10 items-center justify-center rounded-full border border-white/10 text-white lg:hidden"
                            onClick={() => setMobileOpen((value) => !value)}
                            aria-label="Toggle navigation"
                        >
                            {mobileOpen ? <X className="size-5" /> : <Menu className="size-5" />}
                        </button>
                    </div>
                    {mobileOpen ? (
                        <div className="border-t border-white/8 bg-[#09152a] px-6 py-5 lg:hidden">
                            <div className="space-y-2">
                                <div className="pb-2 text-[12px] font-semibold uppercase tracking-[0.24em] text-[#6f82a3]">Features</div>
                                {marketingFeatures.map((feature) => (
                                    <Link
                                        key={feature.slug}
                                        href={`/features/${feature.slug}`}
                                        className="block rounded-[18px] px-4 py-3 text-[16px] text-[#d8e2f4] transition hover:bg-white/6"
                                        onClick={() => setMobileOpen(false)}
                                    >
                                        {feature.label}
                                    </Link>
                                ))}
                                <Link href="/pricing" className="block rounded-[18px] px-4 py-3 text-[16px] text-[#d8e2f4] transition hover:bg-white/6" onClick={() => setMobileOpen(false)}>
                                    Pricing
                                </Link>
                                <Link href="/about" className="block rounded-[18px] px-4 py-3 text-[16px] text-[#d8e2f4] transition hover:bg-white/6" onClick={() => setMobileOpen(false)}>
                                    About
                                </Link>
                                <Link href="/careers" className="block rounded-[18px] px-4 py-3 text-[16px] text-[#d8e2f4] transition hover:bg-white/6" onClick={() => setMobileOpen(false)}>
                                    Careers
                                </Link>
                            </div>
                            <div className="mt-5 grid gap-3">
                                <Link href={authLinks.loginHref} className="rounded-full border border-white/10 px-5 py-3 text-center text-[15px] font-medium text-white" onClick={() => setMobileOpen(false)}>
                                    {authLinks.loginLabel}
                                </Link>
                                {canRegister || auth.user ? (
                                    <Link href={authLinks.registerHref} className="rounded-full bg-[#7c8cff] px-5 py-3 text-center text-[15px] font-semibold text-[#10151d]" onClick={() => setMobileOpen(false)}>
                                        {authLinks.registerLabel}
                                    </Link>
                                ) : null}
                            </div>
                        </div>
                    ) : null}
                </div>

                <main className={cn('relative overflow-x-clip pt-[88px]', pageClassName)}>
                    <div className="pointer-events-none absolute inset-x-0 top-0 h-[520px] bg-[radial-gradient(circle_at_top_left,rgba(124,140,255,0.18),transparent_34%),radial-gradient(circle_at_top_right,rgba(49,94,190,0.22),transparent_34%)]" />
                    <div className="pointer-events-none absolute inset-x-0 top-24 h-[760px] bg-[linear-gradient(180deg,rgba(9,21,42,0)_0%,rgba(9,21,42,0.26)_40%,rgba(9,21,42,0)_100%)]" />
                    <div className="relative">{children}</div>
                </main>

                <footer className="border-t border-white/8 bg-[#07101d]">
                    <div className="mx-auto max-w-[1380px] px-6 py-12 lg:px-8 lg:py-14">
                        <div className="grid gap-12 xl:grid-cols-[1.2fr_1fr_1fr_1fr_1fr]">
                            <div>
                                <div className="flex items-center gap-3 text-[25px] font-semibold tracking-[-0.05em] text-white">
                                    <AppLogoIcon className="size-[26px]" />
                                    RealUptime
                                </div>
                                <p className="mt-5 max-w-[26ch] text-[18px] leading-8 text-[#8ea0bf]">
                                    Monitor websites, TCP ports, and ping targets, then handle incidents, alerts, and public status communication from one operational workspace.
                                </p>
                                <div className="mt-6 flex items-center gap-3">
                                    {socialButtons.map(({ label, Icon }) => (
                                        <button
                                            key={label}
                                            type="button"
                                            className="inline-flex size-11 items-center justify-center rounded-full border border-white/10 bg-white/6 text-[#c9d5ea] transition hover:border-[#7c8cff]/50 hover:text-white"
                                            aria-label={label}
                                        >
                                            <Icon className="size-5" />
                                        </button>
                                    ))}
                                </div>
                            </div>
                            {footerGroups.map((group) => (
                                <div key={group.title}>
                                    <div className="text-[24px] font-semibold tracking-[-0.05em] text-white">
                                        {group.title}
                                        <span className="text-[#7c8cff]">.</span>
                                    </div>
                                    <div className="mt-5 space-y-3">
                                        {group.links.map((link) => (
                                            <Link key={link.href} href={link.href} className="block text-[17px] leading-8 text-[#8ea0bf] transition hover:text-white">
                                                {link.label}
                                            </Link>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                        <div className="mt-12 flex flex-col gap-3 border-t border-white/8 pt-6 text-[14px] text-[#6f82a3] md:flex-row md:items-center md:justify-between">
                            <div>© {new Date().getFullYear()} RealUptime. Uptime, incidents, and public status for modern teams.</div>
                            <div>Cookies are limited to essential product functionality until you opt in.</div>
                        </div>
                    </div>
                </footer>

                {cookieChoice === null ? (
                    <div className="fixed bottom-5 left-5 z-50 max-w-[360px] rounded-[22px] border border-white/10 bg-[#0c1629]/96 p-5 backdrop-blur-xl">
                        <div className="text-[18px] font-semibold tracking-[-0.04em] text-white">Cookies for a better RealUptime experience.</div>
                        <p className="mt-3 text-[15px] leading-7 text-[#9aacca]">
                            We use essential cookies for sign-in and security, plus optional cookies to improve the public website experience.
                        </p>
                        <div className="mt-5 flex flex-wrap gap-3">
                            <button
                                type="button"
                                className="inline-flex h-11 items-center justify-center rounded-full border border-white/10 px-4 text-sm font-medium text-white"
                                onClick={() => acceptCookies('declined')}
                            >
                                Decline optional cookies
                            </button>
                            <button
                                type="button"
                                className="inline-flex h-11 items-center justify-center rounded-full bg-[#7c8cff] px-4 text-sm font-semibold text-[#10151d]"
                                onClick={() => acceptCookies('accepted')}
                            >
                                Accept cookies
                            </button>
                        </div>
                    </div>
                ) : null}
            </div>
        </>
    );
}

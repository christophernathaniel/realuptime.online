import { Link } from '@inertiajs/react';
import { CreditCard, KeyRound, Laptop2, Palette, ShieldCheck, UserRound } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { PageCard } from '@/components/monitoring/page-card';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { show } from '@/routes/two-factor';
import { edit as editPassword } from '@/routes/user-password';
import type { NavItem } from '@/types';

const sidebarNavItems: Array<NavItem & { description: string; icon: typeof UserRound }> = [
    {
        title: 'Profile',
        href: edit(),
        icon: UserRound,
        description: 'Identity and sign-in providers',
    },
    {
        title: 'Password',
        href: editPassword(),
        icon: KeyRound,
        description: 'Password updates and protection',
    },
    {
        title: 'Two-factor auth',
        href: show(),
        icon: ShieldCheck,
        description: 'Authenticator setup and recovery',
    },
    {
        title: 'Sessions',
        href: '/settings/sessions',
        icon: Laptop2,
        description: 'Manage signed-in devices',
    },
    {
        title: 'Membership',
        href: '/settings/membership',
        icon: CreditCard,
        description: 'Plans, limits, and Stripe billing',
    },
    {
        title: 'Appearance',
        href: editAppearance(),
        icon: Palette,
        description: 'Theme preferences',
    },
];

type SettingsLayoutProps = PropsWithChildren<{
    title: string;
    description: string;
}>;

export default function SettingsLayout({
    children,
    title,
    description,
}: SettingsLayoutProps) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    if (typeof window === 'undefined') {
        return null;
    }

    return (
        <div className="space-y-6">
            <div className="space-y-2">
                <p className="text-[11px] font-semibold uppercase tracking-[0.28em] text-[#667998]">
                    Settings
                </p>
                <h1 className="text-[32px] font-semibold tracking-[-0.05em] text-white sm:text-[38px]">
                    {title}
                    <span className="text-[#7c8cff]">.</span>
                </h1>
                <p className="max-w-2xl text-[15px] leading-7 text-[#8fa0bf]">
                    {description}
                </p>
            </div>

            <div className="grid items-start gap-6 lg:grid-cols-[250px,minmax(0,1fr)] xl:grid-cols-[260px,minmax(0,1fr)]">
                <aside className="w-full lg:sticky lg:top-6">
                    <PageCard className="h-fit w-full max-w-[280px] p-3 lg:max-w-none">
                        <div className="mb-3 px-2 pt-2">
                            <p className="text-[11px] font-semibold uppercase tracking-[0.26em] text-[#61718f]">
                                Account center
                            </p>
                            <p className="mt-2 text-sm leading-6 text-[#9badca]">
                                Manage identity, security, devices, and appearance from the same workspace shell.
                            </p>
                        </div>

                        <nav className="space-y-1.5" aria-label="Settings">
                            {sidebarNavItems.map((item, index) => {
                                const Icon = item.icon;
                                const active = isCurrentOrParentUrl(item.href);

                                return (
                                    <Link
                                        key={`${toUrl(item.href)}-${index}`}
                                        href={item.href}
                                        className={cn(
                                            'flex items-start gap-3 rounded-[18px] px-3 py-3 transition',
                                            active
                                                ? 'bg-[#0d172a] text-white'
                                                : 'text-[#c9d5ec] hover:bg-white/5 hover:text-white',
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                'mt-0.5 inline-flex size-9 shrink-0 items-center justify-center rounded-[14px] border',
                                                active
                                                    ? 'border-[#7c8cff]/35 bg-[#171d28] text-[#7c8cff]'
                                                    : 'border-[#2b3544] bg-[#171d28] text-[#7183a5]',
                                            )}
                                        >
                                            <Icon className="size-4" />
                                        </span>
                                        <span className="min-w-0">
                                            <span className="block text-[14px] font-semibold">
                                                {item.title}
                                            </span>
                                            <span
                                                className={cn(
                                                    'mt-1 block text-[12px] leading-5',
                                                    active ? 'text-[#8fa0bf]' : 'text-[#7081a2]',
                                                )}
                                            >
                                                {item.description}
                                            </span>
                                        </span>
                                    </Link>
                                );
                            })}
                        </nav>
                    </PageCard>
                </aside>

                <div className="space-y-6">{children}</div>
            </div>
        </div>
    );
}

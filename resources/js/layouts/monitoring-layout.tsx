import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    BriefcaseBusiness,
    BellRing,
    Cable,
    ChevronDown,
    CreditCard,
    KeyRound,
    Lock,
    LogOut,
    RadioTower,
    Settings,
    ShieldAlert,
    ShieldCheck,
    Users,
    Wrench,
} from 'lucide-react';
import type { PropsWithChildren } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import type { Auth } from '@/types/auth';

const navigation = [
    { label: 'Monitoring', href: '/monitors', icon: Activity, requiresPaid: false },
    { label: 'Incidents', href: '/incidents', icon: ShieldAlert, requiresPaid: true },
    { label: 'Status pages', href: '/status-pages', icon: RadioTower, requiresPaid: true },
    { label: 'Maintenance', href: '/maintenance', icon: Wrench, requiresPaid: true },
    { label: 'Team members', href: '/team-members', icon: Users, requiresPaid: true },
    { label: 'Integrations & API', href: '/integrations', icon: Cable, requiresPaid: true },
];

const accountLinks = [
    { label: 'Membership', href: '/settings/membership', icon: CreditCard },
    { label: 'Profile settings', href: '/settings/profile', icon: Settings },
    { label: 'Password', href: '/settings/password', icon: KeyRound },
    {
        label: 'Two-factor auth',
        href: '/settings/two-factor',
        icon: ShieldCheck,
    },
    { label: 'Sessions', href: '/settings/sessions', icon: Users },
];

export default function MonitoringLayout({ children }: PropsWithChildren) {
    const page = usePage<{
        auth: Auth;
        workspace?: {
            current: {
                id: number;
                name: string;
                email: string;
                isPersonal: boolean;
                membership?: {
                    plan: string;
                    planLabel: string;
                    priceLabel: string;
                    monitorLimit: number;
                    monitorLimitLabel: string;
                    monitorCount: number;
                    minimumIntervalLabel: string;
                    advancedFeaturesUnlocked: boolean;
                    manageUrl: string | null;
                };
            };
            available?: Array<{
                id: number;
                name: string;
                email: string;
                isPersonal: boolean;
            }>;
        } | null;
        flash?: {
            success?: string | null;
            error?: string | null;
        };
        url: string;
    }>();
    const user = page.props.auth.user;
    const workspace = page.props.workspace;
    const availableWorkspaces = workspace?.available ?? [];
    const pathname = page.url;
    const flash = page.props.flash;
    const initials = user.name
        .split(' ')
        .map((part) => part[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();
    const platformNavigation = user.is_admin
        ? [{ label: 'Admin users', href: '/admin/users', icon: ShieldCheck, requiresPaid: false }]
        : [];
    const dropdownLinks = user.is_admin
        ? [{ label: 'Platform admin', href: '/admin/users', icon: ShieldCheck }, ...accountLinks]
        : accountLinks;
    const workspaceMembership = workspace?.current?.membership;
    const advancedSectionsLocked = Boolean(workspaceMembership && !workspaceMembership.advancedFeaturesUnlocked);

    return (
        <div className="monitoring-shell min-h-screen bg-[#081428] text-[#f4f7ff]">
            <div className="flex min-h-screen flex-col lg:h-screen lg:flex-row">
                <aside className="monitoring-sidebar relative overflow-hidden border-b border-white/5 bg-[linear-gradient(180deg,#0f1d33_0%,#0a182c_56%,#072235_100%)] px-4 py-5 lg:sticky lg:top-0 lg:h-screen lg:w-[302px] lg:shrink-0 lg:border-b-0 lg:border-r">
                    <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_bottom,#0f4f45_0%,transparent_35%),radial-gradient(circle_at_top,#183353_0%,transparent_50%)] opacity-40" />
                    <div className="monitoring-sidebar-inner relative flex h-full flex-col lg:overflow-y-auto">
                        <div className="flex items-center gap-2.5 px-2 pb-6 pt-1.5">
                            <span className="inline-flex size-2.5 rounded-full bg-[#3ee072]" />
                            <span className="text-[24px] font-semibold tracking-[-0.04em]">RealUptime</span>
                        </div>

                        {workspace?.current ? (
                            <div className="mb-4 rounded-[16px] border border-white/6 bg-[#101b2f]/85 px-4 py-3">
                                <div className="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.2em] text-[#6f82a3]">
                                    <BriefcaseBusiness className="size-3.5 text-[#3ee072]" />
                                    Current workspace
                                </div>
                                <div className="mt-2 text-[15px] font-semibold text-white">
                                    {workspace.current.name}
                                </div>
                                <div className="mt-1 text-[12px] text-[#8fa0bf]">
                                    {workspace.current.isPersonal ? 'Personal workspace' : workspace.current.email}
                                </div>
                            </div>
                        ) : null}

                        <nav className="space-y-2">
                            {[...navigation, ...platformNavigation].map((item) => {
                                const Icon = item.icon;
                                const active = pathname.startsWith(item.href);
                                const locked = Boolean(item.requiresPaid && advancedSectionsLocked);
                                const upgradeHref = workspace?.current?.isPersonal && workspaceMembership?.manageUrl
                                    ? workspaceMembership.manageUrl
                                    : '/monitors';

                                return locked ? (
                                    <Link
                                        key={item.href}
                                        href={upgradeHref}
                                        className="flex items-center gap-3 rounded-[16px] px-4 py-3 text-[15px] font-medium text-[#8fa0bf] transition hover:bg-white/6 hover:text-white"
                                    >
                                        <span className="inline-flex size-[30px] items-center justify-center rounded-full border border-white/8 text-[#6e7c97]">
                                            <Icon className="size-[15px]" strokeWidth={2.1} />
                                        </span>
                                        <span className="flex min-w-0 flex-1 items-center justify-between gap-3">
                                            <span>{item.label}</span>
                                            <Lock className="size-4 shrink-0 text-[#ffb454]" />
                                        </span>
                                    </Link>
                                ) : (
                                    <Link
                                        key={item.href}
                                        href={item.href}
                                        className={cn(
                                            'flex items-center gap-3 rounded-[16px] px-4 py-3 text-[15px] font-medium text-[#d3dcf0] transition hover:bg-white/6 hover:text-white',
                                            active && 'bg-[#0c1628] text-white shadow-[inset_0_0_0_1px_rgba(255,255,255,0.02)]',
                                        )}
                                    >
                                        <span className={cn('inline-flex size-[30px] items-center justify-center rounded-full border', active ? 'border-[#3ee072]/40 text-[#3ee072]' : 'border-white/8 text-[#6e7c97]')}>
                                            <Icon className="size-[15px]" strokeWidth={2.1} />
                                        </span>
                                        <span>{item.label}</span>
                                    </Link>
                                );
                            })}
                        </nav>

                        <div className="mt-auto space-y-3 pt-5">
                            {workspaceMembership ? (
                                <div className="rounded-[16px] border border-white/5 bg-[#162033]/80 p-3 text-[11px] text-[#9fadca]">
                                    <div className="mb-2 flex items-center gap-2 text-[#dce6fb]">
                                        <CreditCard className="size-[13px] text-[#3ee072]" />
                                        {workspaceMembership.planLabel}
                                    </div>
                                    <div className="text-[12px] text-[#dce6fb]">
                                        {workspaceMembership.monitorCount} / {workspaceMembership.monitorLimitLabel} monitors
                                    </div>
                                    <div className="mt-1 text-[11px] text-[#8fa0bf]">
                                        Fastest interval {workspaceMembership.minimumIntervalLabel}
                                    </div>
                                    {!workspaceMembership.advancedFeaturesUnlocked ? (
                                        <div className="mt-2 text-[11px] text-[#ffd88c]">
                                            Advanced sections are locked on Free.
                                        </div>
                                    ) : null}
                                    {workspaceMembership.manageUrl ? (
                                        <Link
                                            href={workspaceMembership.manageUrl}
                                            className="mt-3 inline-flex rounded-[12px] bg-[#352ef6] px-3 py-2 text-[12px] font-medium text-white"
                                        >
                                            {workspaceMembership.plan === 'free' ? 'Upgrade plan' : 'Manage plan'}
                                        </Link>
                                    ) : null}
                                </div>
                            ) : null}

                            <div className="rounded-[16px] border border-white/5 bg-[#162033]/80 p-3 text-[11px] text-[#9fadca]">
                                <div className="mb-2 flex items-center gap-2 text-[#dce6fb]">
                                    <BellRing className="size-[13px] text-[#3ee072]" />
                                    Email alerts enabled
                                </div>
                                Notifications are delivered to your configured contacts through the app&apos;s email delivery pipeline.
                            </div>

                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <button
                                        type="button"
                                        className="flex w-full items-center gap-3 rounded-[16px] bg-[#0c1628]/80 px-3 py-3 text-left transition hover:bg-[#111d33]"
                                    >
                                        <div className="flex size-10 items-center justify-center rounded-full bg-[#091223] text-[16px] font-semibold tracking-[-0.04em] text-white">
                                            {initials}
                                            <span className="ml-1 text-[#3ee072]">.</span>
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <div className="truncate text-[14px] font-semibold text-white">
                                                {user.name}
                                            </div>
                                            <div className="truncate text-[11px] text-[#8fa0bf]">
                                                {user.email}
                                            </div>
                                        </div>
                                        <ChevronDown className="size-4 text-[#7d8aa7]" />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="start"
                                    side="top"
                                    className="w-[260px] rounded-[20px] border border-white/8 bg-[#101b2f] p-2 text-[#dce6fb] shadow-[0_26px_70px_rgba(0,0,0,0.34)]"
                                >
                                    <DropdownMenuLabel className="px-3 py-2 font-normal">
                                        <div className="space-y-1">
                                            <div className="text-[14px] font-semibold text-white">
                                                {user.name}
                                            </div>
                                            <div className="text-[12px] text-[#8fa0bf]">
                                                {user.email}
                                            </div>
                                        </div>
                                    </DropdownMenuLabel>
                                    <DropdownMenuSeparator className="bg-white/8" />
                                    {workspace && availableWorkspaces.length > 1 ? (
                                        <>
                                            <DropdownMenuLabel className="px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.2em] text-[#6f82a3]">
                                                Switch workspace
                                            </DropdownMenuLabel>
                                            <DropdownMenuGroup>
                                                {availableWorkspaces.map((item) => (
                                                    <DropdownMenuItem
                                                        key={item.id}
                                                        asChild
                                                        className="rounded-[14px] px-3 py-2.5 text-[14px] text-[#dce6fb] focus:bg-[#16253f] focus:text-white"
                                                    >
                                                        <Link
                                                            href="/workspaces/switch"
                                                            method="post"
                                                            as="button"
                                                            data={{ owner_id: item.id }}
                                                            className="flex w-full items-center gap-2.5"
                                                        >
                                                            <BriefcaseBusiness className="size-4 text-[#7e8fad]" />
                                                            {item.name}
                                                        </Link>
                                                    </DropdownMenuItem>
                                                ))}
                                            </DropdownMenuGroup>
                                            <DropdownMenuSeparator className="bg-white/8" />
                                        </>
                                    ) : null}
                                    <DropdownMenuGroup>
                                        {dropdownLinks.map((item) => {
                                            const Icon = item.icon;

                                            return (
                                                <DropdownMenuItem
                                                    key={item.href}
                                                    asChild
                                                    className="rounded-[14px] px-3 py-2.5 text-[14px] text-[#dce6fb] focus:bg-[#16253f] focus:text-white"
                                                >
                                                    <Link href={item.href} className="flex w-full items-center gap-2.5">
                                                        <Icon className="size-4 text-[#7e8fad]" />
                                                        {item.label}
                                                    </Link>
                                                </DropdownMenuItem>
                                            );
                                        })}
                                    </DropdownMenuGroup>
                                    <DropdownMenuSeparator className="bg-white/8" />
                                    <DropdownMenuItem
                                        asChild
                                        className="rounded-[14px] px-3 py-2.5 text-[14px] text-[#ffd9dd] focus:bg-[#2a1621] focus:text-white"
                                    >
                                        <Link
                                            href="/logout"
                                            as="button"
                                            method="post"
                                            className="flex w-full items-center gap-2.5"
                                            data-test="logout-button"
                                        >
                                            <LogOut className="size-4 text-[#ff8d95]" />
                                            Log out
                                        </Link>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>
                </aside>

                <main className="relative flex-1 overflow-hidden bg-[radial-gradient(circle_at_top,#0a2040_0%,transparent_40%),linear-gradient(180deg,#071325_0%,#081428_100%)] px-4 py-5 sm:px-5 lg:h-screen lg:overflow-y-auto lg:px-6 lg:py-5">
                    <div className="pointer-events-none absolute inset-0 opacity-30 [background-image:linear-gradient(rgba(117,130,160,0.05)_1px,transparent_1px),linear-gradient(90deg,rgba(117,130,160,0.05)_1px,transparent_1px)] [background-size:72px_72px]" />
                    <div className="monitoring-page relative mx-auto w-full max-w-[1600px] space-y-3.5">
                        {flash?.success ? (
                            <div className="rounded-[15px] border border-[#3ee072]/25 bg-[#10273a] px-3.5 py-2.5 text-[13px] text-[#dfffe9] shadow-[0_12px_30px_rgba(0,0,0,0.18)]">
                                {flash.success}
                            </div>
                        ) : null}
                        {flash?.error ? (
                            <div className="rounded-[15px] border border-[#ff6269]/25 bg-[#2a1621] px-3.5 py-2.5 text-[13px] text-[#ffe1e4] shadow-[0_12px_30px_rgba(0,0,0,0.18)]">
                                {flash.error}
                            </div>
                        ) : null}
                        {children}
                    </div>
                </main>
            </div>
        </div>
    );
}

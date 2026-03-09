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
    Menu,
    RadioTower,
    Settings,
    ShieldAlert,
    ShieldCheck,
    Users,
    Wrench,
    X,
} from 'lucide-react';
import { useState } from 'react';
import type { PropsWithChildren } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
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
    { label: 'Checks', href: '/monitors', icon: Activity, requiresPaid: false },
    { label: 'Event log', href: '/incidents', icon: ShieldAlert, requiresPaid: true },
    { label: 'Status hub', href: '/status-pages', icon: RadioTower, requiresPaid: true },
    { label: 'Change windows', href: '/maintenance', icon: Wrench, requiresPaid: true },
    { label: 'Workspace access', href: '/team-members', icon: Users, requiresPaid: true },
    { label: 'Automation & API', href: '/integrations', icon: Cable, requiresPaid: true },
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

type WorkspaceMembership = {
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

type WorkspaceSummary = {
    id: number;
    name: string;
    email: string;
    isPersonal: boolean;
};

type WorkspaceState = {
    current: WorkspaceSummary & {
        membership?: WorkspaceMembership;
    };
    available?: WorkspaceSummary[];
} | null;

type SidebarContentProps = {
    user: Auth['user'];
    pathname: string;
    workspace?: WorkspaceState;
    workspaceMembership?: WorkspaceMembership;
    advancedSectionsLocked: boolean;
    availableWorkspaces: WorkspaceSummary[];
    initials: string;
    onNavigate?: () => void;
    mobile?: boolean;
    onCloseMobile?: () => void;
};

function SidebarContent({
    user,
    pathname,
    workspace,
    workspaceMembership,
    advancedSectionsLocked,
    availableWorkspaces,
    initials,
    onNavigate,
    mobile = false,
    onCloseMobile,
}: SidebarContentProps) {
    const platformNavigation = user.is_admin
        ? [{ label: 'Admin users', href: '/admin/users', icon: ShieldCheck, requiresPaid: false }]
        : [];
    const dropdownLinks = user.is_admin
        ? [{ label: 'Platform admin', href: '/admin/users', icon: ShieldCheck }, ...accountLinks]
        : accountLinks;

    return (
        <div className="monitoring-sidebar-inner relative flex h-full flex-col lg:overflow-y-auto">
            <div className="flex items-center justify-between gap-3 px-1.5 pb-5 pt-1">
                <div className="flex items-center gap-3">
                    <AppLogoIcon className="size-[28px]" />
                    <div>
                        <div className="text-[21px] font-semibold tracking-[-0.05em] text-[#edf2fa]">RealUptime</div>
                        <div className="text-[11px] uppercase tracking-[0.24em] text-[#7f8b9b]">Operations cloud</div>
                    </div>
                </div>
                {mobile ? (
                    <button
                        type="button"
                        onClick={onCloseMobile}
                        className="inline-flex size-10 items-center justify-center rounded-[14px] border border-[#2b3645] bg-[#171d28] text-[#dce6fb] transition hover:bg-[#1d2431]"
                        aria-label="Close navigation"
                    >
                        <X className="size-4.5" />
                    </button>
                ) : null}
            </div>

            {workspace?.current ? (
                <div className="mb-4 rounded-[18px] border border-[#2a3443] bg-[linear-gradient(180deg,rgba(22,29,40,0.96)_0%,rgba(18,23,32,0.96)_100%)] px-3.5 py-3.5">
                    <div className="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.2em] text-[#7f8b9b]">
                        <BriefcaseBusiness className="size-3.5 text-[#57c7c2]" />
                        Active workspace
                    </div>
                    <div className="mt-2 text-[15px] font-semibold text-white">
                        {workspace.current.name}
                    </div>
                    <div className="mt-1 text-[12px] text-[#9ba7ba]">
                        {workspace.current.isPersonal ? 'Personal workspace' : workspace.current.email}
                    </div>
                </div>
            ) : null}

            <nav className="space-y-1.5">
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
                            onClick={onNavigate}
                            className="flex items-center gap-3 rounded-[16px] border border-transparent px-3.5 py-2.5 text-[14px] font-medium text-[#97a4b8] transition hover:border-[#263241] hover:bg-[#151c28] hover:text-white"
                        >
                            <span className="inline-flex size-[32px] items-center justify-center rounded-[11px] border border-[#2b3544] bg-[#161d28] text-[#7c879b]">
                                <Icon className="size-[15px]" strokeWidth={2.1} />
                            </span>
                            <span className="flex min-w-0 flex-1 items-center justify-between gap-3">
                                <span>{item.label}</span>
                                <Lock className="size-4 shrink-0 text-[#7c8cff]" />
                            </span>
                        </Link>
                    ) : (
                        <Link
                            key={item.href}
                            href={item.href}
                            onClick={onNavigate}
                            className={cn(
                                'flex items-center gap-3 rounded-[16px] border border-transparent px-3.5 py-2.5 text-[14px] font-medium text-[#d9e1f1] transition hover:border-[#263241] hover:bg-[#151c28] hover:text-white',
                                active && 'border-[#394455] bg-[#171d28] text-white',
                            )}
                        >
                            <span className={cn('inline-flex size-[32px] items-center justify-center rounded-[11px] border bg-[#161d28]', active ? 'border-[#7c8cff]/35 text-[#7c8cff]' : 'border-[#2b3544] text-[#7c879b]')}>
                                <Icon className="size-[15px]" strokeWidth={2.1} />
                            </span>
                            <span>{item.label}</span>
                        </Link>
                    );
                })}
            </nav>

            <div className="mt-auto space-y-2.5 pt-4">
                {workspaceMembership ? (
                    <div className="rounded-[18px] border border-[#2a3443] bg-[linear-gradient(180deg,rgba(20,26,37,0.96)_0%,rgba(18,22,31,0.96)_100%)] p-3.5 text-[11px] text-[#9ca7b9]">
                        <div className="mb-2 flex items-center gap-2 text-[#dce6fb]">
                            <CreditCard className="size-[13px] text-[#7c8cff]" />
                            {workspaceMembership.planLabel}
                        </div>
                        <div className="text-[12px] text-[#dce6fb]">
                            {workspaceMembership.monitorCount} / {workspaceMembership.monitorLimitLabel} monitors
                        </div>
                        <div className="mt-1 text-[11px] text-[#9ca7b9]">
                            Fastest interval {workspaceMembership.minimumIntervalLabel}
                        </div>
                        {!workspaceMembership.advancedFeaturesUnlocked ? (
                            <div className="mt-2 text-[11px] text-[#d3daff]">
                                Expanded workspace tools unlock on paid plans.
                            </div>
                        ) : null}
                        {workspaceMembership.manageUrl ? (
                            <Link
                                href={workspaceMembership.manageUrl}
                                onClick={onNavigate}
                                className="mt-3 inline-flex rounded-[12px] bg-[#7c8cff] px-3 py-2 text-[12px] font-medium text-white"
                            >
                                {workspaceMembership.plan === 'free' ? 'Upgrade plan' : 'Manage plan'}
                            </Link>
                        ) : null}
                    </div>
                ) : null}

                <div className="rounded-[18px] border border-[#2a3443] bg-[linear-gradient(180deg,rgba(20,26,37,0.96)_0%,rgba(18,22,31,0.96)_100%)] p-3.5 text-[11px] text-[#9ca7b9]">
                    <div className="mb-2 flex items-center gap-2 text-[#dce6fb]">
                        <BellRing className="size-[13px] text-[#57c7c2]" />
                        Alert delivery
                    </div>
                    Email notifications and webhook deliveries are dispatched from the active workspace policy.
                </div>

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <button
                            type="button"
                            className="flex w-full items-center gap-3 rounded-[16px] border border-[#2a3443] bg-[#131924]/90 px-3 py-2.5 text-left transition hover:bg-[#171f2b]"
                        >
                            <div className="flex size-10 items-center justify-center rounded-[14px] bg-[#0e131c] text-[15px] font-semibold tracking-[-0.04em] text-white">
                                {initials}
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
                        side={mobile ? 'bottom' : 'top'}
                        className="w-[260px] rounded-[20px] border border-[#2a3443] bg-[#141b26] p-2 text-[#dce6fb]"
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
                        <DropdownMenuSeparator className="bg-[#273140]" />
                        {workspace && availableWorkspaces.length > 1 ? (
                            <>
                                <DropdownMenuLabel className="px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.2em] text-[#7f8b9b]">
                                    Switch workspace
                                </DropdownMenuLabel>
                                <DropdownMenuGroup>
                                    {availableWorkspaces.map((item) => (
                                        <DropdownMenuItem
                                            key={item.id}
                                            asChild
                                            className="rounded-[14px] px-3 py-2.5 text-[14px] text-[#dce6fb] focus:bg-[#1b2330] focus:text-white"
                                        >
                                            <Link
                                                href="/workspaces/switch"
                                                method="post"
                                                as="button"
                                                data={{ owner_id: item.id }}
                                                className="flex w-full items-center gap-2.5"
                                                onClick={onNavigate}
                                            >
                                                <BriefcaseBusiness className="size-4 text-[#7e8fad]" />
                                                {item.name}
                                            </Link>
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuGroup>
                                <DropdownMenuSeparator className="bg-[#273140]" />
                            </>
                        ) : null}
                        <DropdownMenuGroup>
                            {dropdownLinks.map((item) => {
                                const Icon = item.icon;

                                return (
                                    <DropdownMenuItem
                                        key={item.href}
                                        asChild
                                        className="rounded-[14px] px-3 py-2.5 text-[14px] text-[#dce6fb] focus:bg-[#1b2330] focus:text-white"
                                    >
                                        <Link href={item.href} className="flex w-full items-center gap-2.5" onClick={onNavigate}>
                                            <Icon className="size-4 text-[#7e8fad]" />
                                            {item.label}
                                        </Link>
                                    </DropdownMenuItem>
                                );
                            })}
                        </DropdownMenuGroup>
                        <DropdownMenuSeparator className="bg-[#273140]" />
                        <DropdownMenuItem
                            asChild
                            className="rounded-[14px] px-3 py-2.5 text-[14px] text-[#ffd9dd] focus:bg-[#2a1818] focus:text-white"
                        >
                            <Link
                                href="/logout"
                                as="button"
                                method="post"
                                className="flex w-full items-center gap-2.5"
                                data-test="logout-button"
                                onClick={onNavigate}
                            >
                                <LogOut className="size-4 text-[#ff8d95]" />
                                Log out
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
    );
}

export default function MonitoringLayout({ children }: PropsWithChildren) {
    const page = usePage<{
        auth: Auth;
        workspace?: WorkspaceState;
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
    const [mobileNavOpen, setMobileNavOpen] = useState(false);
    const initials = user.name
        .split(' ')
        .map((part) => part[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();
    const workspaceMembership = workspace?.current?.membership;
    const advancedSectionsLocked = Boolean(workspaceMembership && !workspaceMembership.advancedFeaturesUnlocked);

    return (
        <div className="monitoring-shell min-h-screen bg-[#0d1117] text-[#f4f7ff]">
            <div className="flex min-h-screen flex-col lg:h-screen lg:flex-row">
                <aside className="monitoring-sidebar relative hidden overflow-hidden border-b border-[#212a38] bg-[linear-gradient(180deg,#161b25_0%,#151922_52%,#10141b_100%)] px-3.5 py-4 lg:sticky lg:top-0 lg:block lg:h-screen lg:w-[296px] lg:shrink-0 lg:border-b-0 lg:border-r">
                    <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_bottom,rgba(124,140,255,0.12)_0%,transparent_38%),radial-gradient(circle_at_top,rgba(87,199,194,0.12)_0%,transparent_48%)]" />
                    <SidebarContent
                        user={user}
                        pathname={pathname}
                        workspace={workspace}
                        workspaceMembership={workspaceMembership}
                        advancedSectionsLocked={advancedSectionsLocked}
                        availableWorkspaces={availableWorkspaces}
                        initials={initials}
                    />
                </aside>

                {mobileNavOpen ? (
                    <div className="fixed inset-0 z-50 lg:hidden">
                        <button
                            type="button"
                            aria-label="Close navigation"
                            className="absolute inset-0 bg-[#05070c]/78 backdrop-blur-sm"
                            onClick={() => setMobileNavOpen(false)}
                        />
                        <aside className="monitoring-sidebar absolute inset-y-0 left-0 w-[min(85vw,296px)] overflow-hidden border-r border-[#212a38] bg-[linear-gradient(180deg,#161b25_0%,#151922_52%,#10141b_100%)] px-3.5 py-4">
                            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_bottom,rgba(124,140,255,0.12)_0%,transparent_38%),radial-gradient(circle_at_top,rgba(87,199,194,0.12)_0%,transparent_48%)]" />
                            <SidebarContent
                                user={user}
                                pathname={pathname}
                                workspace={workspace}
                                workspaceMembership={workspaceMembership}
                                advancedSectionsLocked={advancedSectionsLocked}
                                availableWorkspaces={availableWorkspaces}
                                initials={initials}
                                mobile
                                onNavigate={() => setMobileNavOpen(false)}
                                onCloseMobile={() => setMobileNavOpen(false)}
                            />
                        </aside>
                    </div>
                ) : null}

                <main className="relative flex-1 overflow-hidden bg-[radial-gradient(circle_at_top_left,rgba(124,140,255,0.12)_0%,transparent_32%),radial-gradient(circle_at_right,rgba(87,199,194,0.08)_0%,transparent_38%),linear-gradient(180deg,#0b0f15_0%,#10141b_100%)] px-3.5 py-4 sm:px-[18px] lg:h-screen lg:overflow-y-auto lg:px-5 lg:py-4">
                    <div className="pointer-events-none absolute inset-0 opacity-30 [background-image:linear-gradient(rgba(145,157,179,0.04)_1px,transparent_1px),linear-gradient(90deg,rgba(145,157,179,0.04)_1px,transparent_1px)] [background-size:76px_76px]" />
                    <div className="monitoring-page relative mx-auto w-full max-w-[1600px] space-y-3">
                        <div className="flex items-center justify-between rounded-[18px] border border-[#273140] bg-[#111722]/88 px-3.5 py-2.5 lg:hidden">
                            <div className="flex items-center gap-3">
                                <AppLogoIcon className="size-[26px]" />
                                <div>
                                    <span className="block text-[19px] font-semibold tracking-[-0.04em] text-white">
                                        RealUptime
                                    </span>
                                    <span className="block text-[10px] uppercase tracking-[0.22em] text-[#7f8b9b]">
                                        Operations cloud
                                    </span>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => setMobileNavOpen(true)}
                                className="inline-flex size-10 items-center justify-center rounded-[14px] border border-[#2b3645] bg-[#171d28] text-[#dce6fb] transition hover:bg-[#1d2431]"
                                aria-label="Open navigation"
                            >
                                <Menu className="size-4.5" />
                            </button>
                        </div>
                        {flash?.success ? (
                            <div className="rounded-[15px] border border-[#57c7c2]/25 bg-[#15222a] px-3 py-2 text-[13px] text-[#def8f4]">
                                {flash.success}
                            </div>
                        ) : null}
                        {flash?.error ? (
                            <div className="rounded-[15px] border border-[#ff7a72]/25 bg-[#2a1818] px-3 py-2 text-[13px] text-[#ffe1e4]">
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

import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowRight, MailPlus, Search, ShieldCheck, ShieldOff, Trash2, Users } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { PageCard } from '@/components/monitoring/page-card';
import { PaginationStrip } from '@/components/monitoring/pagination-strip';
import MonitoringLayout from '@/layouts/monitoring-layout';
import type { PaginatedData } from '@/types/monitoring';

type AdminUserItem = {
    id: number;
    name: string;
    email: string;
    isAdmin: boolean;
    emailVerified: boolean;
    membershipPlan: string;
    membershipPlanLabel: string;
    membershipSource: string;
    adminPlanOverride: string | null;
    hasSubscription: boolean;
    createdAt: string | null;
    lastActiveAt: string | null;
    lastActiveLabel: string;
    monitorsCount: number;
    statusPagesCount: number;
    contactsCount: number;
    acceptedMembersCount: number;
    pendingInvitationsCount: number;
    activeSessionsCount: number;
    openIncidentsCount: number;
    supportExtension: {
        plan: string;
        planLabel: string;
        expiresAt: string | null;
    } | null;
    showUrl: string;
};

type AdminUsersPageProps = {
    summary: {
        users: number;
        admins: number;
        monitors: number;
        supportExtensions: number;
        openIncidents: number;
    };
    filters: {
        search: string;
    };
    users: PaginatedData<AdminUserItem>;
    formDefaults: {
        name: string;
        email: string;
        password: string;
        password_confirmation: string;
    };
};

function SummaryCard({ label, value }: { label: string; value: string | number }) {
    return (
        <PageCard className="px-5 py-4">
            <div className="text-sm text-[#9ca7b9]">{label}</div>
            <div className="mt-1 text-[28px] font-semibold text-white">{value}</div>
        </PageCard>
    );
}

export default function AdminUsersPage({ summary, filters, users, formDefaults }: AdminUsersPageProps) {
    const form = useForm(formDefaults);
    const formErrors = Object.values(form.errors);
    const auth = usePage<{ auth: { user: { id: number } } }>().props.auth;
    const [search, setSearch] = useState(filters.search);

    return (
        <MonitoringLayout>
            <Head title="Platform admin" />
            <div className="space-y-5">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <div className="text-[11px] font-semibold uppercase tracking-[0.26em] text-[#7f8b9b]">
                            Platform support
                        </div>
                        <h1 className="mt-2 text-[40px] font-semibold tracking-[-0.06em] text-white lg:text-[46px]">
                            Platform admin<span className="text-[#7c8cff]">.</span>
                        </h1>
                        <div className="mt-2 max-w-[860px] text-[16px] text-[#9ca7b9]">
                            Review every signed-up account, inspect plan state and live usage, and open a full support view for billing, monitors, sessions, contacts, and recent activity.
                        </div>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        <SummaryCard label="Accounts" value={summary.users} />
                        <SummaryCard label="Admins" value={summary.admins} />
                        <SummaryCard label="Monitors" value={summary.monitors} />
                        <SummaryCard label="Courtesy extensions" value={summary.supportExtensions} />
                        <SummaryCard label="Open incidents" value={summary.openIncidents} />
                    </div>
                </div>

                <div className="grid gap-5 xl:grid-cols-[minmax(0,1.18fr)_400px]">
                    <section className="space-y-4">
                        <PageCard className="space-y-5 p-6">
                            <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                                <div>
                                    <div className="flex items-center gap-3 text-[24px] font-semibold text-white">
                                        <Users className="size-5 text-[#7c8cff]" />
                                        Account queue
                                    </div>
                                    <div className="mt-2 text-[15px] text-[#9ca7b9]">
                                        Search by name or email, then open the account for the full support view.
                                    </div>
                                </div>

                                <form
                                    className="w-full max-w-[360px]"
                                    onSubmit={(event: FormEvent) => {
                                        event.preventDefault();
                                        router.get('/admin/users', { search }, { preserveState: true, preserveScroll: true, replace: true });
                                    }}
                                >
                                    <label className="relative block">
                                        <Search className="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-[#7f8b9b]" />
                                        <input
                                            value={search}
                                            onChange={(event) => setSearch(event.target.value)}
                                            placeholder="Search accounts"
                                            className="h-12 w-full rounded-[16px] border border-white/10 bg-[#0b1425] pl-11 pr-4 text-sm text-white outline-none placeholder:text-[#7f8b9b]"
                                        />
                                    </label>
                                </form>
                            </div>

                            <div className="space-y-4">
                                {users.data.length === 0 ? (
                                    <div className="rounded-[18px] border border-white/8 bg-[#171d28] px-5 py-5 text-[15px] text-[#9ca7b9]">
                                        No accounts matched the current search.
                                    </div>
                                ) : (
                                    users.data.map((user) => (
                                        <div key={user.id} className="rounded-[20px] border border-white/8 bg-[#171d28] px-5 py-5">
                                            <div className="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                                                <div className="min-w-0">
                                                    <div className="flex flex-wrap items-center gap-2 text-[18px] font-semibold text-white">
                                                        <span>{user.name}</span>
                                                        <span className="rounded-full border border-white/8 px-2 py-0.5 text-[11px] uppercase tracking-[0.18em] text-[#9ca7b9]">
                                                            {user.emailVerified ? 'Verified' : 'Unverified'}
                                                        </span>
                                                        <span className="rounded-full border border-[#4d7cff]/20 bg-[#102240] px-2 py-0.5 text-[11px] uppercase tracking-[0.18em] text-[#dce6fb]">
                                                            {user.membershipPlanLabel}
                                                        </span>
                                                        {user.isAdmin ? (
                                                            <span className="rounded-full border border-[#7c8cff]/20 bg-[#171c33] px-2 py-0.5 text-[11px] uppercase tracking-[0.18em] text-[#dbe1ff]">
                                                                Admin
                                                            </span>
                                                        ) : null}
                                                        {user.supportExtension ? (
                                                            <span className="rounded-full border border-[#57c7c2]/20 bg-[#0f2527] px-2 py-0.5 text-[11px] uppercase tracking-[0.18em] text-[#9de5e0]">
                                                                Courtesy until {user.supportExtension.expiresAt ?? 'set'}
                                                            </span>
                                                        ) : null}
                                                        {auth.user.id === user.id ? (
                                                            <span className="rounded-full border border-white/8 px-2 py-0.5 text-[11px] uppercase tracking-[0.18em] text-[#dce6fb]">
                                                                You
                                                            </span>
                                                        ) : null}
                                                    </div>
                                                    <div className="mt-1 break-all text-sm text-[#9ca7b9]">{user.email}</div>
                                                    <div className="mt-2 flex flex-wrap gap-x-4 gap-y-2 text-[12px] text-[#7081a2]">
                                                        <span>Created {user.createdAt ?? 'Unknown'}</span>
                                                        <span>Last active {user.lastActiveLabel}</span>
                                                        <span>Plan source {user.membershipSource === 'admin' ? 'Admin override' : user.membershipSource === 'stripe' ? 'Stripe subscription' : user.membershipSource === 'support' ? 'Courtesy extension' : 'Free default'}</span>
                                                        {user.hasSubscription ? <span>Stripe record present</span> : null}
                                                    </div>
                                                </div>

                                                <div className="flex flex-wrap gap-3">
                                                    <Link
                                                        href={user.showUrl}
                                                        className="inline-flex items-center gap-2 rounded-[14px] bg-[#7c8cff] px-4 py-2.5 text-sm font-medium text-white"
                                                    >
                                                        Open account
                                                        <ArrowRight className="size-4" />
                                                    </Link>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            router.patch(`/admin/users/${user.id}`, { is_admin: !user.isAdmin }, { preserveScroll: true })
                                                        }
                                                        disabled={auth.user.id === user.id && user.isAdmin}
                                                        className={`inline-flex items-center gap-2 rounded-[14px] px-4 py-2.5 text-sm ${
                                                            user.isAdmin
                                                                ? 'border border-white/10 bg-[#101b2f] text-[#dce6fb]'
                                                                : 'border border-[#7c8cff]/25 bg-[#171c33] text-[#dce6fb]'
                                                        } ${auth.user.id === user.id && user.isAdmin ? 'cursor-not-allowed opacity-60' : ''}`}
                                                    >
                                                        {user.isAdmin ? <ShieldOff className="size-4" /> : <ShieldCheck className="size-4" />}
                                                        {auth.user.id === user.id && user.isAdmin ? 'Current admin' : user.isAdmin ? 'Remove admin' : 'Make admin'}
                                                    </button>
                                                    {auth.user.id !== user.id ? (
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                if (window.confirm(`Delete user "${user.email}"? This also deletes their workspace data.`)) {
                                                                    router.delete(`/admin/users/${user.id}`, { preserveScroll: true });
                                                                }
                                                            }}
                                                            className="inline-flex items-center gap-2 rounded-[14px] border border-[#ff6269]/25 bg-[#231320] px-4 py-2.5 text-sm text-[#ffd4d7]"
                                                        >
                                                            <Trash2 className="size-4" />
                                                            Delete
                                                        </button>
                                                    ) : null}
                                                </div>
                                            </div>

                                            <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                                <div className="rounded-[14px] bg-[#121821] px-4 py-3">
                                                    <div className="text-[12px] text-[#7f8eab]">Monitors</div>
                                                    <div className="mt-1 text-[20px] font-semibold text-white">{user.monitorsCount}</div>
                                                </div>
                                                <div className="rounded-[14px] bg-[#121821] px-4 py-3">
                                                    <div className="text-[12px] text-[#7f8eab]">Open incidents</div>
                                                    <div className="mt-1 text-[20px] font-semibold text-white">{user.openIncidentsCount}</div>
                                                </div>
                                                <div className="rounded-[14px] bg-[#121821] px-4 py-3">
                                                    <div className="text-[12px] text-[#7f8eab]">Status pages</div>
                                                    <div className="mt-1 text-[20px] font-semibold text-white">{user.statusPagesCount}</div>
                                                </div>
                                                <div className="rounded-[14px] bg-[#121821] px-4 py-3">
                                                    <div className="text-[12px] text-[#7f8eab]">Active sessions</div>
                                                    <div className="mt-1 text-[20px] font-semibold text-white">{user.activeSessionsCount}</div>
                                                </div>
                                            </div>

                                            <div className="mt-3 flex flex-wrap gap-3 text-[13px] text-[#9badca]">
                                                <span className="rounded-full bg-[#121821] px-3 py-2">Contacts {user.contactsCount}</span>
                                                <span className="rounded-full bg-[#121821] px-3 py-2">Team members {user.acceptedMembersCount}</span>
                                                <span className="rounded-full bg-[#121821] px-3 py-2">Pending invites {user.pendingInvitationsCount}</span>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>

                            <PaginationStrip
                                currentPage={users.currentPage}
                                lastPage={users.lastPage}
                                from={users.from}
                                to={users.to}
                                total={users.total}
                                previousPageUrl={users.previousPageUrl}
                                nextPageUrl={users.nextPageUrl}
                            />
                        </PageCard>
                    </section>

                    <aside className="space-y-4">
                        <PageCard className="space-y-5 p-6">
                            <div className="flex items-center gap-3 text-[24px] font-semibold text-white">
                                <MailPlus className="size-5 text-[#7c8cff]" />
                                Create account
                            </div>
                            <div className="text-[15px] text-[#9ca7b9]">
                                Accounts created here are standard users by default. Use the account view to handle plan support, billing context, and deeper operational troubleshooting.
                            </div>

                            <form
                                className="space-y-4"
                                onSubmit={(event: FormEvent) => {
                                    event.preventDefault();
                                    form.post('/admin/users', {
                                        preserveScroll: true,
                                        onSuccess: () => form.reset(),
                                    });
                                }}
                            >
                                {formErrors.length > 0 ? (
                                    <div className="rounded-[16px] border border-[#ff6269]/20 bg-[#2a1621] px-4 py-3 text-sm text-[#ffd4d7]">
                                        {formErrors.join(' ')}
                                    </div>
                                ) : null}

                                <label className="space-y-2">
                                    <span className="text-[15px] text-[#dce6fb]">Name</span>
                                    <input
                                        value={form.data.name}
                                        onChange={(event) => form.setData('name', event.target.value)}
                                        className="h-11 w-full rounded-[14px] border border-white/10 bg-[#0b1425] px-4 text-sm text-white outline-none"
                                        placeholder="Alex Carter"
                                    />
                                </label>

                                <label className="space-y-2">
                                    <span className="text-[15px] text-[#dce6fb]">Email</span>
                                    <input
                                        value={form.data.email}
                                        onChange={(event) => form.setData('email', event.target.value)}
                                        className="h-11 w-full rounded-[14px] border border-white/10 bg-[#0b1425] px-4 text-sm text-white outline-none"
                                        placeholder="alex@example.com"
                                    />
                                </label>

                                <label className="space-y-2">
                                    <span className="text-[15px] text-[#dce6fb]">Password</span>
                                    <input
                                        type="password"
                                        value={form.data.password}
                                        onChange={(event) => form.setData('password', event.target.value)}
                                        className="h-11 w-full rounded-[14px] border border-white/10 bg-[#0b1425] px-4 text-sm text-white outline-none"
                                        placeholder="Choose a password"
                                    />
                                </label>

                                <label className="space-y-2">
                                    <span className="text-[15px] text-[#dce6fb]">Confirm password</span>
                                    <input
                                        type="password"
                                        value={form.data.password_confirmation}
                                        onChange={(event) => form.setData('password_confirmation', event.target.value)}
                                        className="h-11 w-full rounded-[14px] border border-white/10 bg-[#0b1425] px-4 text-sm text-white outline-none"
                                        placeholder="Repeat the password"
                                    />
                                </label>

                                <button
                                    type="submit"
                                    disabled={form.processing}
                                    className="inline-flex h-11 items-center gap-2 rounded-[14px] bg-[#7c8cff] px-4 text-sm font-medium text-white"
                                >
                                    <MailPlus className="size-4" />
                                    Create standard user
                                </button>
                            </form>
                        </PageCard>
                    </aside>
                </div>
            </div>
        </MonitoringLayout>
    );
}

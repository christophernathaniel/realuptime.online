import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Activity, MailPlus, ShieldCheck, ShieldOff, Trash2, Users } from 'lucide-react';
import type { FormEvent } from 'react';
import { PageCard } from '@/components/monitoring/page-card';
import MonitoringLayout from '@/layouts/monitoring-layout';

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
};

type AdminUsersPageProps = {
    summary: {
        users: number;
        admins: number;
        monitors: number;
        statusPages: number;
        openIncidents: number;
    };
    users: AdminUserItem[];
    formDefaults: {
        name: string;
        email: string;
        password: string;
        password_confirmation: string;
    };
};

function SummaryCard({
    label,
    value,
    tone = 'default',
}: {
    label: string;
    value: string | number;
    tone?: 'default' | 'good' | 'warning';
}) {
    const toneClass = tone === 'good' ? 'text-[#3ee072]' : tone === 'warning' ? 'text-[#ffb454]' : 'text-white';

    return (
        <PageCard className="px-5 py-4">
            <div className="text-sm text-[#8fa0bf]">{label}</div>
            <div className={`mt-1 text-[28px] font-semibold ${toneClass}`}>{value}</div>
        </PageCard>
    );
}

export default function AdminUsersPage({ summary, users, formDefaults }: AdminUsersPageProps) {
    const form = useForm(formDefaults);
    const formErrors = Object.values(form.errors);
    const auth = usePage<{ auth: { user: { id: number } } }>().props.auth;

    return (
        <MonitoringLayout>
            <Head title="Admin users" />
            <div className="space-y-5">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h1 className="text-[40px] font-semibold tracking-[-0.06em] text-white lg:text-[46px]">
                            Admin users<span className="text-[#3ee072]">.</span>
                        </h1>
                        <div className="mt-2 max-w-[840px] text-[16px] text-[#8fa0bf]">
                            Monitor platform usage across all accounts, create standard users, and explicitly promote or demote admin access. Self-registration and workspace invitations remain non-admin by default.
                        </div>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        <SummaryCard label="Users" value={summary.users} />
                        <SummaryCard label="Admins" value={summary.admins} tone="good" />
                        <SummaryCard label="Monitors" value={summary.monitors} />
                        <SummaryCard label="Status pages" value={summary.statusPages} />
                        <SummaryCard label="Open incidents" value={summary.openIncidents} tone={summary.openIncidents > 0 ? 'warning' : 'default'} />
                    </div>
                </div>

                <div className="grid gap-5 xl:grid-cols-[minmax(0,1.2fr)_400px]">
                    <section className="space-y-4">
                        <PageCard className="space-y-5 p-6">
                            <div className="flex items-center gap-3 text-[24px] font-semibold text-white">
                                <Users className="size-5 text-[#3ee072]" />
                                Platform accounts
                            </div>
                            <div className="grid gap-4">
                                {users.map((user) => (
                                    <div key={user.id} className="rounded-[18px] border border-white/8 bg-[#111a2c] px-5 py-5">
                                        <div className="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                                            <div>
                                                <div className="flex flex-wrap items-center gap-2 text-[18px] font-semibold text-white">
                                                <span>{user.name}</span>
                                                <span className="rounded-full border border-white/8 px-2 py-0.5 text-[11px] uppercase tracking-[0.18em] text-[#8fa0bf]">
                                                    {user.emailVerified ? 'Verified' : 'Unverified'}
                                                </span>
                                                <span className="rounded-full border border-[#4d7cff]/20 bg-[#102240] px-2 py-0.5 text-[11px] uppercase tracking-[0.18em] text-[#dce6fb]">
                                                    {user.membershipPlanLabel}
                                                </span>
                                                {user.isAdmin ? (
                                                    <span className="rounded-full border border-[#3ee072]/20 bg-[#10273a] px-2 py-0.5 text-[11px] uppercase tracking-[0.18em] text-[#dfffe9]">
                                                        Admin
                                                        </span>
                                                    ) : null}
                                                    {auth.user.id === user.id ? (
                                                        <span className="rounded-full border border-white/8 px-2 py-0.5 text-[11px] uppercase tracking-[0.18em] text-[#dce6fb]">
                                                            You
                                                        </span>
                                                    ) : null}
                                                </div>
                                            <div className="mt-1 text-sm text-[#8fa0bf]">{user.email}</div>
                                            <div className="mt-2 text-[12px] text-[#7081a2]">
                                                Created {user.createdAt ?? 'Unknown'} • Last active {user.lastActiveLabel}
                                            </div>
                                            <div className="mt-2 text-[12px] text-[#7081a2]">
                                                Plan source: {user.membershipSource === 'admin' ? 'Admin override' : user.membershipSource === 'stripe' ? 'Stripe subscription' : 'Free default'}
                                            </div>
                                            {user.hasSubscription ? (
                                                <div className="mt-1 text-[12px] text-[#9bb4ff]">
                                                    Stripe billing record present
                                                </div>
                                            ) : null}
                                        </div>

                                            <div className="flex flex-wrap gap-3">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        router.patch(`/admin/users/${user.id}`, { is_admin: !user.isAdmin }, { preserveScroll: true })
                                                    }
                                                    className={`inline-flex items-center gap-2 rounded-[14px] px-4 py-2.5 text-sm ${
                                                        user.isAdmin
                                                            ? 'border border-white/10 bg-[#101b2f] text-[#dce6fb]'
                                                            : 'bg-[#352ef6] text-white'
                                                    }`}
                                                >
                                                    {user.isAdmin ? (
                                                        <>
                                                            <ShieldOff className="size-4" />
                                                            Remove admin
                                                        </>
                                                    ) : (
                                                        <>
                                                            <ShieldCheck className="size-4" />
                                                            Make admin
                                                        </>
                                                    )}
                                                </button>
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
                                            </div>
                                        </div>

                                        <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                            <div className="rounded-[14px] bg-[#0d1628] px-4 py-3">
                                                <div className="text-[12px] text-[#7f8eab]">Monitors</div>
                                                <div className="mt-1 text-[20px] font-semibold text-white">{user.monitorsCount}</div>
                                            </div>
                                            <div className="rounded-[14px] bg-[#0d1628] px-4 py-3">
                                                <div className="text-[12px] text-[#7f8eab]">Open incidents</div>
                                                <div className={`mt-1 text-[20px] font-semibold ${user.openIncidentsCount > 0 ? 'text-[#ffb454]' : 'text-white'}`}>{user.openIncidentsCount}</div>
                                            </div>
                                            <div className="rounded-[14px] bg-[#0d1628] px-4 py-3">
                                                <div className="text-[12px] text-[#7f8eab]">Status pages</div>
                                                <div className="mt-1 text-[20px] font-semibold text-white">{user.statusPagesCount}</div>
                                            </div>
                                            <div className="rounded-[14px] bg-[#0d1628] px-4 py-3">
                                                <div className="text-[12px] text-[#7f8eab]">Active sessions</div>
                                                <div className="mt-1 text-[20px] font-semibold text-white">{user.activeSessionsCount}</div>
                                            </div>
                                        </div>

                                        <div className="mt-3 grid gap-3 sm:grid-cols-3">
                                            <div className="rounded-[14px] bg-[#0d1628] px-4 py-3 text-sm text-[#9badca]">
                                                Contacts <span className="ml-2 font-semibold text-white">{user.contactsCount}</span>
                                            </div>
                                            <div className="rounded-[14px] bg-[#0d1628] px-4 py-3 text-sm text-[#9badca]">
                                                Team members <span className="ml-2 font-semibold text-white">{user.acceptedMembersCount}</span>
                                            </div>
                                            <div className="rounded-[14px] bg-[#0d1628] px-4 py-3 text-sm text-[#9badca]">
                                                Pending invites <span className="ml-2 font-semibold text-white">{user.pendingInvitationsCount}</span>
                                            </div>
                                        </div>

                                        <div className="mt-4 flex flex-col gap-3 rounded-[14px] bg-[#0d1628] px-4 py-4 lg:flex-row lg:items-center lg:justify-between">
                                            <div className="text-sm text-[#9badca]">
                                                Override membership plan for this user. Choose <span className="font-semibold text-white">Subscription / default</span> to let Stripe or free access determine the plan.
                                            </div>
                                            <div className="flex flex-wrap gap-2">
                                                {[
                                                    { value: '', label: 'Subscription / default' },
                                                    { value: 'free', label: 'Free' },
                                                    { value: 'premium', label: 'Premium' },
                                                    { value: 'ultra', label: 'Ultra' },
                                                ].map((option) => {
                                                    const active = (user.adminPlanOverride ?? '') === option.value;

                                                    return (
                                                        <button
                                                            key={`${user.id}-${option.value || 'default'}`}
                                                            type="button"
                                                            onClick={() =>
                                                                router.patch(`/admin/users/${user.id}/membership`, {
                                                                    admin_plan_override: option.value || null,
                                                                }, { preserveScroll: true })
                                                            }
                                                            className={`rounded-[12px] px-3 py-2 text-xs font-medium ${
                                                                active
                                                                    ? 'bg-[#352ef6] text-white'
                                                                    : 'border border-white/10 bg-[#111a2c] text-[#dce6fb]'
                                                            }`}
                                                        >
                                                            {option.label}
                                                        </button>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </PageCard>
                    </section>

                    <aside className="space-y-4">
                        <PageCard className="space-y-5 p-6">
                            <div className="flex items-center gap-3 text-[24px] font-semibold text-white">
                                <MailPlus className="size-5 text-[#3ee072]" />
                                Create user
                            </div>
                            <div className="text-[15px] text-[#8fa0bf]">
                                New accounts created here are standard users, not admins. Promote them explicitly only when platform access is required.
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
                                    className="inline-flex h-11 items-center gap-2 rounded-[14px] bg-[#352ef6] px-4 text-sm font-medium text-white"
                                >
                                    <Activity className="size-4" />
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

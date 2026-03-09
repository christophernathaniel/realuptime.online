import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    BadgeCheck,
    KeyRound,
    MailPlus,
    Shield,
    UserRound,
    Users,
} from 'lucide-react';
import InputError from '@/components/input-error';
import { PageCard } from '@/components/monitoring/page-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import MonitoringLayout from '@/layouts/monitoring-layout';
import {
    surfaceInputClass,
    surfaceLabelClass,
    surfacePrimaryButtonClass,
} from '@/lib/realuptime-theme';

type TeamPageProps = {
    teamWorkspace: {
        name: string;
        email: string;
        isOwner: boolean;
        isPersonal: boolean;
    };
    owner: {
        name: string;
        email: string;
        emailVerified: boolean;
        twoFactorEnabled: boolean;
        memberSince: string;
    };
    summary: {
        monitors: number;
        contacts: number;
        statusPages: number;
        members: number;
    };
    links: Array<{
        label: string;
        href: string;
    }>;
    canInvite: boolean;
    formDefaults: {
        email: string;
    };
    acceptedMembers: Array<{
        id: string | number;
        name: string;
        email: string;
        acceptedAt: string | null;
        statusLabel: string;
        isCurrentUser: boolean;
        removable: boolean;
        invitationId: number | null;
    }>;
    pendingInvitations: Array<{
        id: number;
        email: string;
        invitedAt: string;
        removable: boolean;
    }>;
};

export default function TeamPage({
    teamWorkspace,
    owner,
    summary,
    links,
    canInvite,
    formDefaults,
    acceptedMembers,
    pendingInvitations,
}: TeamPageProps) {
    const invitationForm = useForm(formDefaults);

    return (
        <MonitoringLayout>
            <Head title="Team members" />
            <div className="space-y-5">
                <div>
                    <h1 className="text-[40px] font-semibold tracking-[-0.06em] text-white lg:text-[46px]">
                        Team members<span className="text-[#3ee072]">.</span>
                    </h1>
                    <div className="mt-2 max-w-[780px] text-[16px] text-[#8fa0bf]">
                        Invite teammates into this workspace, track accepted access, and remove members when they no longer need access.
                    </div>
                    {!teamWorkspace.isOwner ? (
                        <div className="mt-4 inline-flex rounded-full border border-[#3ee072]/20 bg-[#10273a] px-4 py-2 text-sm text-[#dfffe9]">
                            You are currently viewing {owner.name}&apos;s shared workspace.
                        </div>
                    ) : null}
                </div>

                <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
                    <section className="space-y-5">
                        {canInvite ? (
                            <PageCard className="space-y-5 p-6">
                                <div className="flex items-center gap-3 text-[24px] font-semibold text-white">
                                    <MailPlus className="size-5 text-[#3ee072]" />
                                    Invite teammate
                                </div>
                                <div className="text-[15px] text-[#8fa0bf]">
                                    Invitations are sent by email. The recipient signs in with that email address, accepts the invitation, and gets access to this workspace.
                                </div>

                                <form
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        invitationForm.post('/team-members/invitations', {
                                            preserveScroll: true,
                                        });
                                    }}
                                    className="space-y-4"
                                >
                                    <div className="grid gap-2.5">
                                        <Label htmlFor="email" className={surfaceLabelClass}>
                                            Teammate email
                                        </Label>
                                        <Input
                                            id="email"
                                            name="email"
                                            value={invitationForm.data.email}
                                            onChange={(event) =>
                                                invitationForm.setData('email', event.currentTarget.value)
                                            }
                                            placeholder="teammate@example.com"
                                            className={surfaceInputClass}
                                        />
                                        <InputError message={invitationForm.errors.email} />
                                    </div>

                                    <Button
                                        type="submit"
                                        disabled={invitationForm.processing}
                                        className={surfacePrimaryButtonClass}
                                    >
                                        Send invitation
                                    </Button>
                                </form>
                            </PageCard>
                        ) : null}

                        <PageCard className="space-y-5 p-6">
                            <div className="flex items-center gap-3 text-[24px] font-semibold text-white">
                                <Users className="size-5 text-[#3ee072]" />
                                Accepted members
                            </div>
                            <div className="grid gap-3">
                                {acceptedMembers.map((member) => (
                                    <div
                                        key={member.id}
                                        className="flex flex-col gap-4 rounded-[18px] bg-[#111a2c] px-5 py-5 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div>
                                            <div className="flex items-center gap-2 text-[16px] font-semibold text-white">
                                                {member.name}
                                                <span className="rounded-full border border-white/8 px-2 py-0.5 text-[11px] uppercase tracking-[0.18em] text-[#8fa0bf]">
                                                    {member.statusLabel}
                                                </span>
                                                {member.isCurrentUser ? (
                                                    <span className="rounded-full border border-[#3ee072]/20 bg-[#10273a] px-2 py-0.5 text-[11px] uppercase tracking-[0.18em] text-[#dfffe9]">
                                                        You
                                                    </span>
                                                ) : null}
                                            </div>
                                            <div className="mt-1 text-sm text-[#8fa0bf]">{member.email}</div>
                                            <div className="mt-2 text-[12px] text-[#7081a2]">
                                                {member.acceptedAt ? `Accepted ${member.acceptedAt}` : 'Accepted'}
                                            </div>
                                        </div>

                                        {member.removable && member.invitationId ? (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    router.delete(`/team-members/invitations/${member.invitationId}`, {
                                                        preserveScroll: true,
                                                    })
                                                }
                                                className="inline-flex h-11 items-center justify-center rounded-[16px] border border-white/10 bg-[#101b2f] px-4 text-sm font-medium text-[#dce6fb] transition hover:bg-[#16253f] hover:text-white"
                                            >
                                                {teamWorkspace.isOwner ? 'Remove access' : 'Leave workspace'}
                                            </button>
                                        ) : null}
                                    </div>
                                ))}
                            </div>
                        </PageCard>

                        <PageCard className="space-y-4 p-6">
                            <div className="text-[22px] font-semibold text-white">Pending invitations</div>
                            <div className="grid gap-3">
                                {pendingInvitations.length === 0 ? (
                                    <div className="rounded-[18px] bg-[#111a2c] px-5 py-5 text-[15px] text-[#8fa0bf]">
                                        No pending invitations.
                                    </div>
                                ) : (
                                    pendingInvitations.map((invitation) => (
                                        <div
                                            key={invitation.id}
                                            className="flex flex-col gap-4 rounded-[18px] bg-[#111a2c] px-5 py-5 sm:flex-row sm:items-center sm:justify-between"
                                        >
                                            <div>
                                                <div className="text-[16px] font-semibold text-white">{invitation.email}</div>
                                                <div className="mt-1 text-sm text-[#8fa0bf]">
                                                    Invited {invitation.invitedAt}
                                                </div>
                                            </div>

                                            {invitation.removable ? (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        router.delete(`/team-members/invitations/${invitation.id}`, {
                                                            preserveScroll: true,
                                                        })
                                                    }
                                                    className="inline-flex h-11 items-center justify-center rounded-[16px] border border-white/10 bg-[#101b2f] px-4 text-sm font-medium text-[#dce6fb] transition hover:bg-[#16253f] hover:text-white"
                                                >
                                                    Cancel invitation
                                                </button>
                                            ) : null}
                                        </div>
                                    ))
                                )}
                            </div>
                        </PageCard>

                        <PageCard className="space-y-4 p-6">
                            <div className="text-[22px] font-semibold text-white">Account shortcuts</div>
                            <div className="grid gap-3">
                                {links.map((link) => (
                                    <Link
                                        key={link.href}
                                        href={link.href}
                                        className="rounded-[16px] border border-white/8 bg-[#111a2c] px-5 py-4 text-[15px] text-[#dce6fb] transition hover:border-white/14 hover:bg-[#162139]"
                                    >
                                        {link.label}
                                    </Link>
                                ))}
                            </div>
                        </PageCard>
                    </section>

                    <aside className="space-y-4">
                        <PageCard className="space-y-5 p-6">
                            <div className="flex items-center gap-3 text-[24px] font-semibold text-white">
                                <UserRound className="size-5 text-[#3ee072]" />
                                Current workspace
                            </div>
                            <div className="grid gap-4">
                                <div className="rounded-[18px] bg-[#111a2c] px-5 py-5">
                                    <div className="text-sm text-[#8fa0bf]">Workspace name</div>
                                    <div className="mt-1 text-[22px] font-semibold text-white">{teamWorkspace.name}</div>
                                </div>
                                <div className="rounded-[18px] bg-[#111a2c] px-5 py-5">
                                    <div className="text-sm text-[#8fa0bf]">Workspace type</div>
                                    <div className="mt-1 text-[22px] font-semibold text-white">
                                        {teamWorkspace.isPersonal ? 'Personal workspace' : 'Shared workspace'}
                                    </div>
                                </div>
                                {!teamWorkspace.isPersonal ? (
                                    <div className="rounded-[18px] bg-[#111a2c] px-5 py-5">
                                        <div className="text-sm text-[#8fa0bf]">Workspace email</div>
                                        <div className="mt-1 text-[22px] font-semibold text-white">{teamWorkspace.email}</div>
                                    </div>
                                ) : null}
                            </div>
                        </PageCard>

                        <PageCard className="space-y-5 p-6">
                            <div className="flex items-center gap-3 text-[24px] font-semibold text-white">
                                <UserRound className="size-5 text-[#3ee072]" />
                                Workspace owner
                            </div>
                            <div className="grid gap-4">
                                <div className="rounded-[18px] bg-[#111a2c] px-5 py-5">
                                    <div className="text-sm text-[#8fa0bf]">Name</div>
                                    <div className="mt-1 text-[22px] font-semibold text-white">{owner.name}</div>
                                </div>
                                <div className="rounded-[18px] bg-[#111a2c] px-5 py-5">
                                    <div className="text-sm text-[#8fa0bf]">Email</div>
                                    <div className="mt-1 text-[22px] font-semibold text-white">{owner.email}</div>
                                </div>
                                <div className="rounded-[18px] bg-[#111a2c] px-5 py-5">
                                    <div className="text-sm text-[#8fa0bf]">Email verification</div>
                                    <div className="mt-1 text-[22px] font-semibold text-white">
                                        {owner.emailVerified ? 'Verified' : 'Pending'}
                                    </div>
                                </div>
                                <div className="rounded-[18px] bg-[#111a2c] px-5 py-5">
                                    <div className="text-sm text-[#8fa0bf]">Two-factor authentication</div>
                                    <div className="mt-1 text-[22px] font-semibold text-white">
                                        {owner.twoFactorEnabled ? 'Enabled' : 'Disabled'}
                                    </div>
                                </div>
                            </div>
                            <div className="text-[14px] text-[#7f8eab]">Member since {owner.memberSince}</div>
                        </PageCard>

                        <PageCard className="p-6">
                            <div className="flex items-center gap-3 text-[20px] font-semibold text-white">
                                <BadgeCheck className="size-5 text-[#3ee072]" />
                                Workspace summary
                            </div>
                            <div className="mt-5 grid gap-4">
                                <div className="rounded-[18px] bg-[#111a2c] px-5 py-5">
                                    <div className="text-sm text-[#8fa0bf]">Members</div>
                                    <div className="mt-1 text-[30px] font-semibold text-white">{summary.members}</div>
                                </div>
                                <div className="rounded-[18px] bg-[#111a2c] px-5 py-5">
                                    <div className="text-sm text-[#8fa0bf]">Monitors</div>
                                    <div className="mt-1 text-[30px] font-semibold text-white">{summary.monitors}</div>
                                </div>
                                <div className="rounded-[18px] bg-[#111a2c] px-5 py-5">
                                    <div className="text-sm text-[#8fa0bf]">Email contacts</div>
                                    <div className="mt-1 text-[30px] font-semibold text-white">{summary.contacts}</div>
                                </div>
                                <div className="rounded-[18px] bg-[#111a2c] px-5 py-5">
                                    <div className="text-sm text-[#8fa0bf]">Status pages</div>
                                    <div className="mt-1 text-[30px] font-semibold text-white">{summary.statusPages}</div>
                                </div>
                            </div>
                        </PageCard>

                        <PageCard className="space-y-4 p-6">
                            <div className="flex items-center gap-3 text-[20px] font-semibold text-white">
                                <Shield className="size-5 text-[#3ee072]" />
                                Security posture
                            </div>
                            <div className="text-[15px] text-[#8fa0bf]">
                                Shared workspaces still use personal account security. Encourage every member to verify email, enable two-factor auth, and keep sessions clean.
                            </div>
                            <div className="flex items-center gap-3 rounded-[16px] bg-[#111a2c] px-4 py-4 text-[15px] text-[#dce6fb]">
                                <KeyRound className="size-4 text-[#3ee072]" />
                                Use the settings area to rotate credentials and review active sessions.
                            </div>
                        </PageCard>
                    </aside>
                </div>
            </div>
        </MonitoringLayout>
    );
}

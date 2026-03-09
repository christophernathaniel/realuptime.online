import { Head, Link, router, usePage } from '@inertiajs/react';
import { CreditCard, Lock, ShieldCheck, Sparkles } from 'lucide-react';
import { PageCard } from '@/components/monitoring/page-card';
import MonitoringLayout from '@/layouts/monitoring-layout';
import SettingsLayout from '@/layouts/settings/layout';

type MembershipPageProps = {
    membership: {
        currentPlan: {
            value: string;
            label: string;
            priceLabel: string;
            monitorLimit: number;
            monitorLimitLabel: string;
            minimumIntervalLabel: string;
            source: string;
            sourceLabel: string;
            advancedFeaturesUnlocked: boolean;
            isAdmin: boolean;
        };
        plans: Array<{
            value: string;
            label: string;
            priceLabel: string;
            monitorLimit: number;
            minimumIntervalLabel: string;
            advancedFeaturesUnlocked: boolean;
            stripeEnabled: boolean;
            isCurrent: boolean;
        }>;
        canCheckout: boolean;
        canManageBilling: boolean;
        subscriptionActive: boolean;
        subscriptionStatus: string | null;
        adminOverride: string | null;
        checkoutSuccess: boolean;
        checkoutCancelled: boolean;
    };
};

export default function MembershipPage({ membership }: MembershipPageProps) {
    const flash = usePage<{ flash?: { success?: string | null; error?: string | null } }>().props.flash;

    return (
        <MonitoringLayout>
            <Head title="Membership" />

            <SettingsLayout
                title="Membership"
                description="Upgrade the workspace subscription, review monitor limits, and manage billing through Stripe when applicable."
            >
                <PageCard className="space-y-6 p-6 sm:p-7">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div className="text-[11px] font-semibold uppercase tracking-[0.26em] text-[#61718f]">
                                Current plan
                            </div>
                            <h2 className="mt-2 text-[28px] font-semibold tracking-[-0.05em] text-white">
                                {membership.currentPlan.label}
                                <span className="text-[#3ee072]">.</span>
                            </h2>
                            <div className="mt-2 text-[15px] text-[#8fa0bf]">
                                {membership.currentPlan.priceLabel} • {membership.currentPlan.sourceLabel}
                            </div>
                        </div>
                        {membership.currentPlan.isAdmin ? (
                            <div className="rounded-[16px] border border-[#3ee072]/18 bg-[#10273a] px-4 py-3 text-sm text-[#dfffe9]">
                                Platform admin access is enabled on this account. Workspace subscriptions and limits still follow the selected membership plan.
                            </div>
                        ) : null}
                    </div>

                    <div className="grid gap-4 md:grid-cols-3">
                        <div className="rounded-[18px] bg-[#111a2c] px-5 py-5">
                            <div className="text-sm text-[#8fa0bf]">Monitor limit</div>
                            <div className="mt-1 text-[26px] font-semibold text-white">{membership.currentPlan.monitorLimitLabel}</div>
                        </div>
                        <div className="rounded-[18px] bg-[#111a2c] px-5 py-5">
                            <div className="text-sm text-[#8fa0bf]">Fastest interval</div>
                            <div className="mt-1 text-[26px] font-semibold text-white">{membership.currentPlan.minimumIntervalLabel}</div>
                        </div>
                        <div className="rounded-[18px] bg-[#111a2c] px-5 py-5">
                            <div className="text-sm text-[#8fa0bf]">Advanced sections</div>
                            <div className={`mt-1 text-[26px] font-semibold ${membership.currentPlan.advancedFeaturesUnlocked ? 'text-[#3ee072]' : 'text-[#ffb454]'}`}>
                                {membership.currentPlan.advancedFeaturesUnlocked ? 'Unlocked' : 'Locked'}
                            </div>
                        </div>
                    </div>

                    {flash?.error ? (
                        <div className="rounded-[18px] border border-[#ff6269]/20 bg-[#2a1621] px-4 py-3 text-sm text-[#ffd4d7]">
                            {flash.error}
                        </div>
                    ) : null}

                    {flash?.success ? (
                        <div className="rounded-[18px] border border-[#3ee072]/18 bg-[#10273a] px-4 py-3 text-sm text-[#dfffe9]">
                            {flash.success}
                        </div>
                    ) : null}

                    {membership.checkoutSuccess ? (
                        <div className="rounded-[18px] border border-[#3ee072]/18 bg-[#10273a] px-4 py-3 text-sm text-[#dfffe9]">
                            Stripe checkout completed. Subscription access will reflect as soon as Stripe syncs the subscription back into the app.
                        </div>
                    ) : null}

                    {membership.checkoutCancelled ? (
                        <div className="rounded-[18px] border border-[#ffb454]/18 bg-[#2b2110] px-4 py-3 text-sm text-[#ffd88c]">
                            Stripe checkout was cancelled before completion.
                        </div>
                    ) : null}
                </PageCard>

                <div className="grid gap-5 xl:grid-cols-3">
                    {membership.plans.map((plan) => (
                        <PageCard key={plan.value} className="flex flex-col p-6">
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <div className="text-[22px] font-semibold text-white">{plan.label}</div>
                                    <div className="mt-1 text-[15px] text-[#8fa0bf]">{plan.priceLabel}</div>
                                </div>
                                {plan.isCurrent ? (
                                    <div className="rounded-full border border-[#3ee072]/20 bg-[#10273a] px-3 py-1 text-xs font-medium text-[#dfffe9]">
                                        Current
                                    </div>
                                ) : null}
                            </div>

                            <div className="mt-5 space-y-3 text-sm text-[#dce6fb]">
                                <div className="flex items-center gap-2">
                                    <Sparkles className="size-4 text-[#3ee072]" />
                                    {plan.monitorLimit} monitors
                                </div>
                                <div className="flex items-center gap-2">
                                    <CreditCard className="size-4 text-[#9bb4ff]" />
                                    Fastest interval: {plan.minimumIntervalLabel}
                                </div>
                                <div className="flex items-center gap-2">
                                    {plan.advancedFeaturesUnlocked ? (
                                        <ShieldCheck className="size-4 text-[#3ee072]" />
                                    ) : (
                                        <Lock className="size-4 text-[#ffb454]" />
                                    )}
                                    {plan.advancedFeaturesUnlocked ? 'Status pages, incidents, maintenance, team, integrations' : 'Monitoring pages only'}
                                </div>
                            </div>

                            <div className="mt-6">
                                {plan.value === 'free' ? (
                                    <div className="rounded-[14px] bg-[#111a2c] px-4 py-3 text-sm text-[#8fa0bf]">
                                        Free workspaces are limited to 3 monitors and 5-minute checks.
                                    </div>
                                ) : membership.canCheckout && !plan.isCurrent ? (
                                    <Link
                                        href={`/settings/membership/checkout/${plan.value}`}
                                        method="post"
                                        as="button"
                                        className={`inline-flex h-11 w-full items-center justify-center rounded-[14px] bg-[#352ef6] px-4 text-sm font-medium text-white ${plan.stripeEnabled ? '' : 'pointer-events-none opacity-50'}`}
                                    >
                                        {plan.stripeEnabled ? `Subscribe to ${plan.label}` : 'Stripe not configured'}
                                    </Link>
                                ) : membership.canManageBilling && membership.subscriptionActive ? (
                                    <button
                                        type="button"
                                        onClick={() => router.post('/settings/membership/portal')}
                                        className="inline-flex h-11 w-full items-center justify-center rounded-[14px] border border-white/10 bg-[#111a2c] px-4 text-sm font-medium text-[#dce6fb]"
                                    >
                                        Manage in Stripe
                                    </button>
                                ) : (
                                    <div className="rounded-[14px] bg-[#111a2c] px-4 py-3 text-sm text-[#8fa0bf]">
                                        {membership.adminOverride
                                            ? 'Plan access is currently controlled by an admin override.'
                                            : 'This plan is already active or managed through Stripe.'}
                                    </div>
                                )}
                            </div>
                        </PageCard>
                    ))}
                </div>
            </SettingsLayout>
        </MonitoringLayout>
    );
}

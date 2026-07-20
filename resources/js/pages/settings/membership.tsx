import { Head, router, usePage } from '@inertiajs/react';
import { Check, CreditCard, Gauge, ShieldCheck, XCircle } from 'lucide-react';
import { PageCard } from '@/components/monitoring/page-card';
import MonitoringLayout from '@/layouts/monitoring-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { cn } from '@/lib/utils';

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
        stripeReady: boolean;
        stripeMissing: string[];
        canCheckout: boolean;
        canManageBilling: boolean;
        subscriptionActive: boolean;
        subscriptionStatus: string | null;
        subscriptionOnGracePeriod: boolean;
        subscriptionEndsAt: string | null;
        adminOverride: string | null;
        supportExtension?: {
            plan: string;
            planLabel: string;
            expiresAt: string | null;
        } | null;
        checkoutSuccess: boolean;
        checkoutCancelled: boolean;
    };
};

export default function MembershipPage({ membership }: MembershipPageProps) {
    const flash = usePage<{
        flash?: { success?: string | null; error?: string | null };
    }>().props.flash;

    return (
        <MonitoringLayout>
            <Head title="Membership" />

            <SettingsLayout
                title="Membership"
                description="Plan limits, subscription status, and Stripe billing for this workspace."
            >
                {flash?.error ||
                flash?.success ||
                membership.checkoutSuccess ||
                membership.checkoutCancelled ? (
                    <div
                        className={cn(
                            'rounded-md border px-4 py-3 text-sm',
                            flash?.error
                                ? 'border-[#ff6269]/25 bg-[#2a1621] text-[#ffd4d7]'
                                : 'border-[#57c7c2]/20 bg-[#0f2527] text-[#b8ece8]',
                        )}
                    >
                        {flash?.error ??
                            flash?.success ??
                            (membership.checkoutSuccess
                                ? 'Checkout completed. Stripe will confirm the subscription through the signed webhook.'
                                : 'Checkout was cancelled before completion.')}
                    </div>
                ) : null}

                <PageCard className="p-0">
                    <div className="flex flex-col gap-5 border-b border-white/8 p-5 sm:p-6 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div className="text-sm text-[#9ca7b9]">
                                Current plan
                            </div>
                            <div className="mt-1 text-2xl font-semibold text-white">
                                {membership.currentPlan.label}
                            </div>
                            <div className="mt-1 text-sm text-[#9ca7b9]">
                                {membership.currentPlan.priceLabel} ·{' '}
                                {membership.currentPlan.sourceLabel}
                            </div>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            {membership.currentPlan.isAdmin ? (
                                <span className="inline-flex items-center gap-2 rounded-md border border-[#7c8cff]/20 px-3 py-2 text-xs text-[#dbe1ff]">
                                    <ShieldCheck className="size-4" />
                                    Main admin
                                </span>
                            ) : null}
                            {membership.canManageBilling ? (
                                <button
                                    type="button"
                                    onClick={() =>
                                        router.post(
                                            '/settings/membership/portal',
                                        )
                                    }
                                    className="inline-flex h-10 items-center gap-2 rounded-md bg-[#7c8cff] px-4 text-sm font-medium text-white"
                                >
                                    <CreditCard className="size-4" />
                                    Manage billing
                                </button>
                            ) : null}
                        </div>
                    </div>

                    <div className="grid divide-y divide-white/8 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                        <div className="p-5">
                            <div className="text-xs text-[#7f8eab] uppercase">
                                Monitor limit
                            </div>
                            <div className="mt-2 text-xl font-semibold text-white">
                                {membership.currentPlan.monitorLimitLabel}
                            </div>
                        </div>
                        <div className="p-5">
                            <div className="text-xs text-[#7f8eab] uppercase">
                                Fastest check
                            </div>
                            <div className="mt-2 text-xl font-semibold text-white">
                                {membership.currentPlan.minimumIntervalLabel}
                            </div>
                        </div>
                        <div className="p-5">
                            <div className="text-xs text-[#7f8eab] uppercase">
                                Subscription
                            </div>
                            <div className="mt-2 text-xl font-semibold text-white">
                                {membership.subscriptionOnGracePeriod
                                    ? 'Cancelling'
                                    : (membership.subscriptionStatus ??
                                      'No Stripe plan')}
                            </div>
                            {membership.subscriptionEndsAt ? (
                                <div className="mt-1 text-xs text-[#9ca7b9]">
                                    Access ends {membership.subscriptionEndsAt}
                                </div>
                            ) : null}
                        </div>
                    </div>
                </PageCard>

                {!membership.stripeReady && membership.currentPlan.isAdmin ? (
                    <div className="flex items-start gap-3 rounded-md border border-[#ff6269]/20 bg-[#2a1621] px-4 py-3 text-sm text-[#ffd4d7]">
                        <XCircle className="mt-0.5 size-4 shrink-0" />
                        Stripe setup is incomplete:{' '}
                        {membership.stripeMissing.join(', ')}.
                    </div>
                ) : null}

                <PageCard className="overflow-hidden p-0">
                    <div className="border-b border-white/8 px-5 py-4 sm:px-6">
                        <div className="text-lg font-semibold text-white">
                            Plan comparison
                        </div>
                    </div>
                    <div className="hidden grid-cols-[1.1fr_0.8fr_0.8fr_1.4fr_180px] gap-4 border-b border-white/8 px-6 py-3 text-xs text-[#7f8eab] uppercase lg:grid">
                        <span>Plan</span>
                        <span>Monitors</span>
                        <span>Interval</span>
                        <span>Workspace</span>
                        <span />
                    </div>
                    <div className="divide-y divide-white/8">
                        {membership.plans.map((plan) => (
                            <div
                                key={plan.value}
                                className={cn(
                                    'grid gap-4 px-5 py-5 sm:px-6 lg:grid-cols-[1.1fr_0.8fr_0.8fr_1.4fr_180px] lg:items-center',
                                    plan.isCurrent && 'bg-[#151b2a]',
                                )}
                            >
                                <div>
                                    <div className="flex items-center gap-2 font-medium text-white">
                                        {plan.label}
                                        {plan.isCurrent ? (
                                            <span className="rounded-md bg-[#263052] px-2 py-0.5 text-[11px] text-[#dbe1ff]">
                                                Current
                                            </span>
                                        ) : null}
                                    </div>
                                    <div className="mt-1 text-sm text-[#9ca7b9]">
                                        {plan.priceLabel}
                                    </div>
                                </div>
                                <div className="flex items-center gap-2 text-sm text-[#dce6fb]">
                                    <Gauge className="size-4 text-[#7c8cff]" />
                                    {plan.monitorLimit}
                                </div>
                                <div className="text-sm text-[#dce6fb]">
                                    {plan.minimumIntervalLabel}
                                </div>
                                <div className="flex items-start gap-2 text-sm text-[#9ca7b9]">
                                    <Check className="mt-0.5 size-4 shrink-0 text-[#57c7c2]" />
                                    {plan.advancedFeaturesUnlocked
                                        ? 'Status pages, incidents, maintenance, team, integrations, and custom checks'
                                        : 'Core uptime monitoring with the standard check profile'}
                                </div>
                                <div>
                                    {plan.value === 'free' || plan.isCurrent ? (
                                        <span className="text-sm text-[#7f8eab]">
                                            {plan.isCurrent
                                                ? 'Active plan'
                                                : 'Included'}
                                        </span>
                                    ) : membership.canCheckout ? (
                                        <button
                                            type="button"
                                            disabled={!plan.stripeEnabled}
                                            onClick={() =>
                                                router.post(
                                                    `/settings/membership/checkout/${plan.value}`,
                                                )
                                            }
                                            className="inline-flex h-10 w-full items-center justify-center rounded-md bg-[#7c8cff] px-3 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            {plan.stripeEnabled
                                                ? `Choose ${plan.label}`
                                                : 'Unavailable'}
                                        </button>
                                    ) : membership.canManageBilling ? (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                router.post(
                                                    '/settings/membership/portal',
                                                )
                                            }
                                            className="inline-flex h-10 w-full items-center justify-center rounded-md border border-white/10 px-3 text-sm text-[#dce6fb]"
                                        >
                                            Manage in Stripe
                                        </button>
                                    ) : (
                                        <span className="text-sm text-[#7f8eab]">
                                            {membership.adminOverride
                                                ? 'Managed by admin'
                                                : 'Not available'}
                                        </span>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                </PageCard>
            </SettingsLayout>
        </MonitoringLayout>
    );
}

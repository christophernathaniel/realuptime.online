import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Cable,
    CheckCircle2,
    CircleDollarSign,
    CreditCard,
    ExternalLink,
    KeyRound,
    LoaderCircle,
    Mail,
    ReceiptText,
    RefreshCcw,
    Save,
    ShieldCheck,
    TimerReset,
    Trash2,
    Users,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { PageCard } from '@/components/monitoring/page-card';
import MonitoringLayout from '@/layouts/monitoring-layout';
import { cn } from '@/lib/utils';

type PlanOption = {
    value: string;
    label: string;
};

type AdminUserShowProps = {
    account: {
        id: number;
        name: string;
        email: string;
        isAdmin: boolean;
        passwordLoginEnabled: boolean;
        emailVerified: boolean;
        createdAt: string | null;
        lastActiveAt: string | null;
        lastActiveLabel: string;
        membershipPlan: string;
        membershipPlanLabel: string;
        membershipSource: string;
        membershipSourceLabel: string;
        publicStatusKey: string | null;
    };
    usage: {
        monitors: number;
        statusPages: number;
        contacts: number;
        integrations: number;
        apiTokens: number;
        activeSessions: number;
        acceptedMembers: number;
        pendingInvitations: number;
        openIncidents: number;
    };
    support: {
        adminOverride: {
            plan: string;
            planLabel: string;
            assignedAt: string | null;
            assignedBy: string | null;
        } | null;
        courtesyExtension: {
            plan: string;
            planLabel: string;
            grantedAt: string | null;
            grantedBy: string | null;
            expiresAt: string | null;
            expiresLabel: string | null;
        } | null;
        supportPlanOptions: PlanOption[];
    };
    billing: {
        customerId: string | null;
        paymentMethodLabel: string;
        currentSubscription: {
            stripeId: string;
            status: string;
            plan: string | null;
            planLabel: string | null;
            priceIds: string[];
            quantity: number | null;
            trialEndsAt: string | null;
            endsAt: string | null;
            createdAt: string | null;
            valid: boolean;
            onGracePeriod: boolean;
            ended: boolean;
        } | null;
        hasSavedPaymentMethod: boolean;
        configuration: {
            ready: boolean;
            webhookUrl: string;
            missing: string[];
        };
        planOptions: Array<{
            value: string;
            label: string;
            priceLabel: string;
            configured: boolean;
        }>;
        canChangePlan: boolean;
        canCancel: boolean;
        canReactivate: boolean;
        invoiceHistoryAvailable: boolean;
    };
    stripeInvoices?: {
        status: 'none' | 'loaded' | 'error';
        error: string | null;
        items: Array<{
            id: string;
            number: string | null;
            status: string;
            tone: 'paid' | 'failed' | 'pending';
            date: string;
            dueDate: string | null;
            paidAt: string | null;
            total: string;
            amountPaid: string;
            currency: string;
            attemptCount: number;
            hostedInvoiceUrl: string | null;
            invoicePdf: string | null;
        }>;
    };
    collectionMeta: Record<
        string,
        {
            shown: number;
            total: number;
            truncated: boolean;
        }
    >;
    monitors: Array<{
        id: number;
        name: string;
        status: string;
        statusValue: string;
        typeLabel: string;
        target: string | null;
        region: string;
        intervalLabel: string;
        timeoutLabel: string;
        retries: number;
        lastCheckedAt: string | null;
        lastCheckedLabel: string;
        lastResponseTimeLabel: string;
        lastHttpStatus: number | null;
        lastError: string | null;
        openIncidentsCount: number;
        notificationLogsCount: number;
        statusPages: string[];
        contacts: string[];
        capabilities: string[];
    }>;
    statusPages: Array<{
        id: number;
        name: string;
        headline: string | null;
        published: boolean;
        slug: string;
        monitorCount: number;
        incidentsCount: number;
        monitorNames: string[];
        publicUrl: string;
    }>;
    contacts: Array<{
        id: number;
        name: string;
        email: string;
        enabled: boolean;
        isPrimary: boolean;
        logsCount: number;
        monitorNames: string[];
    }>;
    integrations: Array<{
        id: number;
        name: string;
        provider: string;
        status: string;
        events: string[];
        notificationLogsCount: number;
        lastTestedAt: string | null;
        lastError: string | null;
    }>;
    apiTokens: Array<{
        id: number;
        name: string;
        createdAt: string | null;
        lastUsedAt: string | null;
        lastUsedLabel: string;
    }>;
    team: Array<{
        id: number;
        email: string;
        memberName: string | null;
        memberEmail: string | null;
        status: string;
        invitedAt: string | null;
        acceptedAt: string | null;
        invitedBy: string | null;
    }>;
    sessions: Array<{
        id: number;
        active: boolean;
        lastActiveAt: string | null;
        lastActiveLabel: string;
        lastPath: string | null;
        ipAddress: string | null;
        userAgent: string | null;
    }>;
    recentIncidents: Array<{
        id: number;
        monitor: string | null;
        status: string;
        startedAt: string | null;
        resolvedAt: string | null;
        duration: string | null;
        reason: string;
    }>;
    recentNotifications: Array<{
        id: number;
        type: string;
        channel: string;
        status: string;
        subject: string;
        destination: string;
        monitor: string | null;
        sentAt: string | null;
        failureMessage: string | null;
    }>;
};

function SummaryCard({
    label,
    value,
}: {
    label: string;
    value: string | number;
}) {
    return (
        <PageCard className="px-5 py-4">
            <div className="text-sm text-[#9ca7b9]">{label}</div>
            <div className="mt-1 text-[28px] font-semibold text-white">
                {value}
            </div>
        </PageCard>
    );
}

function SectionHeading({
    title,
    description,
}: {
    title: string;
    description?: string;
}) {
    return (
        <div>
            <div className="text-[22px] font-semibold text-white">{title}</div>
            {description ? (
                <div className="mt-2 text-[14px] text-[#9ca7b9]">
                    {description}
                </div>
            ) : null}
        </div>
    );
}

export default function AdminUserShowPage({
    account,
    usage,
    support,
    billing,
    stripeInvoices,
    collectionMeta,
    monitors,
    statusPages,
    contacts,
    integrations,
    apiTokens,
    team,
    sessions,
    recentIncidents,
    recentNotifications,
}: AdminUserShowProps) {
    const auth = usePage<{ auth: { user: { id: number } } }>().props.auth;
    const isCurrentAdmin = auth.user.id === account.id;
    const [selectedStripePlan, setSelectedStripePlan] = useState(
        billing.currentSubscription?.plan ??
            billing.planOptions[0]?.value ??
            'premium',
    );
    const [loadingInvoices, setLoadingInvoices] = useState(false);
    const accountForm = useForm({
        name: account.name,
        email: account.email,
        email_verified: account.emailVerified,
        password_login_enabled: account.passwordLoginEnabled,
        password: '',
        password_confirmation: '',
    });

    return (
        <MonitoringLayout>
            <Head title={`Platform admin · ${account.email}`} />
            <div className="space-y-5">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <Link
                            href="/admin/users"
                            className="inline-flex items-center gap-2 text-sm text-[#9ca7b9]"
                        >
                            <ArrowLeft className="size-4" />
                            Back to accounts
                        </Link>
                        <div className="mt-4 flex flex-wrap items-center gap-2 text-[11px] tracking-[0.2em] text-[#7f8b9b] uppercase">
                            <span>
                                {account.emailVerified
                                    ? 'Verified account'
                                    : 'Unverified account'}
                            </span>
                            <span className="rounded-full border border-[#4d7cff]/20 bg-[#102240] px-2 py-1 text-[#dce6fb]">
                                {account.membershipPlanLabel}
                            </span>
                            <span className="rounded-full border border-white/8 px-2 py-1 text-[#dce6fb]">
                                {account.membershipSourceLabel}
                            </span>
                            {account.isAdmin ? (
                                <span className="rounded-full border border-[#7c8cff]/20 bg-[#171c33] px-2 py-1 text-[#dbe1ff]">
                                    Main admin
                                </span>
                            ) : null}
                            {auth.user.id === account.id ? (
                                <span className="rounded-full border border-white/8 px-2 py-1 text-[#dce6fb]">
                                    You
                                </span>
                            ) : null}
                        </div>
                        <h1 className="mt-3 text-[38px] font-semibold tracking-[-0.06em] text-white lg:text-[46px]">
                            {account.name}
                            <span className="text-[#7c8cff]">.</span>
                        </h1>
                        <div className="mt-2 text-[16px] break-all text-[#9ca7b9]">
                            {account.email}
                        </div>
                        <div className="mt-2 flex flex-wrap gap-x-4 gap-y-2 text-[13px] text-[#7081a2]">
                            <span>
                                Created {account.createdAt ?? 'Unknown'}
                            </span>
                            <span>Last active {account.lastActiveLabel}</span>
                            {account.publicStatusKey ? (
                                <span>
                                    Status key {account.publicStatusKey}
                                </span>
                            ) : null}
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-3 xl:grid-cols-4">
                        <SummaryCard label="Monitors" value={usage.monitors} />
                        <SummaryCard
                            label="Open incidents"
                            value={usage.openIncidents}
                        />
                        <SummaryCard
                            label="Sessions"
                            value={usage.activeSessions}
                        />
                        <SummaryCard
                            label="Status pages"
                            value={usage.statusPages}
                        />
                    </div>
                </div>

                <div className="grid gap-5 xl:grid-cols-[minmax(0,1.1fr)_420px]">
                    <section className="space-y-5">
                        <PageCard className="space-y-5 p-6">
                            <SectionHeading
                                title="Billing and membership"
                                description="Subscription controls use local Cashier state. Live invoice history is fetched from Stripe only when requested."
                            />

                            <div className="grid gap-4 lg:grid-cols-2">
                                <div className="rounded-[18px] bg-[#121821] px-5 py-5">
                                    <div className="flex items-center gap-3 text-[16px] font-semibold text-white">
                                        <CreditCard className="size-4 text-[#7c8cff]" />
                                        Current membership
                                    </div>
                                    <div className="mt-4 space-y-3 text-[14px] text-[#9ca7b9]">
                                        <div className="flex items-center justify-between gap-4">
                                            <span>Effective plan</span>
                                            <span className="font-medium text-white">
                                                {account.membershipPlanLabel}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between gap-4">
                                            <span>Source</span>
                                            <span className="font-medium text-white">
                                                {account.membershipSourceLabel}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between gap-4">
                                            <span>Payment method</span>
                                            <span className="font-medium text-white">
                                                {billing.paymentMethodLabel}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between gap-4">
                                            <span>Stripe customer</span>
                                            <span className="font-mono text-[12px] text-white">
                                                {billing.customerId ?? 'None'}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div className="rounded-[18px] bg-[#121821] px-5 py-5">
                                    <div className="flex items-center gap-3 text-[16px] font-semibold text-white">
                                        <ReceiptText className="size-4 text-[#7c8cff]" />
                                        Subscription record
                                    </div>
                                    {billing.currentSubscription ? (
                                        <div className="mt-4 space-y-3 text-[14px] text-[#9ca7b9]">
                                            <div className="flex items-center justify-between gap-4">
                                                <span>Status</span>
                                                <span className="font-medium text-white">
                                                    {
                                                        billing
                                                            .currentSubscription
                                                            .status
                                                    }
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between gap-4">
                                                <span>Plan</span>
                                                <span className="font-medium text-white">
                                                    {billing.currentSubscription
                                                        .planLabel ??
                                                        'Unknown from Stripe price'}
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between gap-4">
                                                <span>Valid</span>
                                                <span className="font-medium text-white">
                                                    {billing.currentSubscription
                                                        .valid
                                                        ? 'Yes'
                                                        : 'No'}
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between gap-4">
                                                <span>Stripe sub</span>
                                                <span className="font-mono text-[12px] text-white">
                                                    {
                                                        billing
                                                            .currentSubscription
                                                            .stripeId
                                                    }
                                                </span>
                                            </div>
                                            {billing.currentSubscription
                                                .endsAt ? (
                                                <div className="flex items-center justify-between gap-4">
                                                    <span>Ends</span>
                                                    <span className="font-medium text-white">
                                                        {
                                                            billing
                                                                .currentSubscription
                                                                .endsAt
                                                        }
                                                    </span>
                                                </div>
                                            ) : null}
                                            {billing.currentSubscription
                                                .trialEndsAt ? (
                                                <div className="flex items-center justify-between gap-4">
                                                    <span>Trial ends</span>
                                                    <span className="font-medium text-white">
                                                        {
                                                            billing
                                                                .currentSubscription
                                                                .trialEndsAt
                                                        }
                                                    </span>
                                                </div>
                                            ) : null}
                                        </div>
                                    ) : (
                                        <div className="mt-4 text-[14px] text-[#9ca7b9]">
                                            No local subscription record is
                                            attached to this account.
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div className="border-t border-white/8 pt-5">
                                <div className="flex items-center gap-2 text-[16px] font-semibold text-white">
                                    <CircleDollarSign className="size-4 text-[#57c7c2]" />
                                    Stripe subscription controls
                                </div>
                                {!billing.configuration.ready ? (
                                    <div className="mt-3 flex items-start gap-2 text-sm text-[#ffd4d7]">
                                        <XCircle className="mt-0.5 size-4 shrink-0" />
                                        Missing{' '}
                                        {billing.configuration.missing.join(
                                            ', ',
                                        )}
                                        . Billing mutations are disabled.
                                    </div>
                                ) : (
                                    <div className="mt-4 flex flex-col gap-3 lg:flex-row lg:items-end">
                                        <label className="min-w-0 flex-1 space-y-2">
                                            <span className="text-sm text-[#9ca7b9]">
                                                Stripe plan
                                            </span>
                                            <select
                                                value={selectedStripePlan}
                                                onChange={(event) =>
                                                    setSelectedStripePlan(
                                                        event.target.value,
                                                    )
                                                }
                                                className="h-11 w-full rounded-md border border-white/10 bg-[#0b1425] px-3 text-sm text-white outline-none"
                                            >
                                                {billing.planOptions.map(
                                                    (plan) => (
                                                        <option
                                                            key={plan.value}
                                                            value={plan.value}
                                                            disabled={
                                                                !plan.configured
                                                            }
                                                        >
                                                            {plan.label} -{' '}
                                                            {plan.priceLabel}
                                                            {plan.configured
                                                                ? ''
                                                                : ' (not configured)'}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                        </label>
                                        <div className="flex flex-wrap gap-2">
                                            {billing.canChangePlan ? (
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        if (
                                                            window.confirm(
                                                                'Change this Stripe subscription? Stripe may apply a prorated charge or credit.',
                                                            )
                                                        ) {
                                                            router.patch(
                                                                `/admin/users/${account.id}/subscription`,
                                                                {
                                                                    plan: selectedStripePlan,
                                                                },
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            );
                                                        }
                                                    }}
                                                    className="inline-flex h-11 items-center gap-2 rounded-md bg-[#7c8cff] px-4 text-sm font-medium text-white"
                                                >
                                                    <RefreshCcw className="size-4" />
                                                    Change plan
                                                </button>
                                            ) : null}
                                            {billing.canReactivate ? (
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        if (
                                                            window.confirm(
                                                                'Reactivate this subscription? An ended subscription may charge the saved payment method.',
                                                            )
                                                        ) {
                                                            router.post(
                                                                `/admin/users/${account.id}/subscription/reactivate`,
                                                                {
                                                                    plan: selectedStripePlan,
                                                                },
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            );
                                                        }
                                                    }}
                                                    className="inline-flex h-11 items-center gap-2 rounded-md bg-[#1f765f] px-4 text-sm font-medium text-white"
                                                >
                                                    <RefreshCcw className="size-4" />
                                                    Reactivate
                                                </button>
                                            ) : null}
                                            {billing.canCancel ? (
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        if (
                                                            window.confirm(
                                                                'Cancel this subscription at the end of its current billing period?',
                                                            )
                                                        ) {
                                                            router.delete(
                                                                `/admin/users/${account.id}/subscription`,
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            );
                                                        }
                                                    }}
                                                    className="inline-flex h-11 items-center gap-2 rounded-md border border-[#ff6269]/30 px-4 text-sm text-[#ffd4d7]"
                                                >
                                                    <XCircle className="size-4" />
                                                    Cancel
                                                </button>
                                            ) : null}
                                        </div>
                                    </div>
                                )}
                            </div>

                            <div className="space-y-4 border-t border-white/8 pt-5">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div className="text-[16px] font-semibold text-white">
                                        Recent Stripe invoices
                                    </div>
                                    {billing.invoiceHistoryAvailable ? (
                                        <button
                                            type="button"
                                            disabled={loadingInvoices}
                                            onClick={() =>
                                                router.reload({
                                                    only: ['stripeInvoices'],
                                                    onStart: () =>
                                                        setLoadingInvoices(
                                                            true,
                                                        ),
                                                    onFinish: () =>
                                                        setLoadingInvoices(
                                                            false,
                                                        ),
                                                })
                                            }
                                            className="inline-flex h-9 items-center gap-2 rounded-md border border-white/10 px-3 text-xs text-white disabled:opacity-60"
                                        >
                                            {loadingInvoices ? (
                                                <LoaderCircle className="size-3.5 animate-spin" />
                                            ) : (
                                                <ReceiptText className="size-3.5" />
                                            )}
                                            {stripeInvoices
                                                ? 'Refresh invoices'
                                                : 'Load invoices'}
                                        </button>
                                    ) : null}
                                </div>
                                {!billing.invoiceHistoryAvailable ? (
                                    <div className="text-sm text-[#9ca7b9]">
                                        No Stripe customer is available for
                                        invoice lookup.
                                    </div>
                                ) : !stripeInvoices ? (
                                    <div className="text-sm text-[#9ca7b9]">
                                        Invoice history is not requested during
                                        the initial page load.
                                    </div>
                                ) : stripeInvoices.status === 'error' ? (
                                    <div className="text-sm text-[#ffd4d7]">
                                        {stripeInvoices.error}
                                    </div>
                                ) : stripeInvoices.items.length === 0 ? (
                                    <div className="text-sm text-[#9ca7b9]">
                                        No recent invoices were returned for
                                        this customer.
                                    </div>
                                ) : (
                                    <div className="divide-y divide-white/8 border-y border-white/8">
                                        {stripeInvoices.items.map((invoice) => (
                                            <div
                                                key={invoice.id}
                                                className="flex flex-col gap-3 py-4 lg:flex-row lg:items-center lg:justify-between"
                                            >
                                                <div>
                                                    <div className="flex flex-wrap items-center gap-2 text-sm font-medium text-white">
                                                        <span>
                                                            {invoice.number ??
                                                                invoice.id}
                                                        </span>
                                                        <span
                                                            className={cn(
                                                                'rounded-full px-2 py-0.5 text-[11px] uppercase',
                                                                invoice.tone ===
                                                                    'paid' &&
                                                                    'bg-[#0f2527] text-[#9de5e0]',
                                                                invoice.tone ===
                                                                    'failed' &&
                                                                    'bg-[#2a1621] text-[#ffd4d7]',
                                                                invoice.tone ===
                                                                    'pending' &&
                                                                    'bg-[#171c33] text-[#dbe1ff]',
                                                            )}
                                                        >
                                                            {invoice.status}
                                                        </span>
                                                    </div>
                                                    <div className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-[#9ca7b9]">
                                                        <span>
                                                            {invoice.date}
                                                        </span>
                                                        <span>
                                                            Total{' '}
                                                            {invoice.total}
                                                        </span>
                                                        <span>
                                                            Paid{' '}
                                                            {invoice.amountPaid}
                                                        </span>
                                                    </div>
                                                </div>
                                                <div className="flex gap-2">
                                                    {invoice.hostedInvoiceUrl ? (
                                                        <a
                                                            href={
                                                                invoice.hostedInvoiceUrl
                                                            }
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="inline-flex items-center gap-1 text-xs text-[#dce6fb]"
                                                        >
                                                            Invoice{' '}
                                                            <ExternalLink className="size-3.5" />
                                                        </a>
                                                    ) : null}
                                                    {invoice.invoicePdf ? (
                                                        <a
                                                            href={
                                                                invoice.invoicePdf
                                                            }
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="inline-flex items-center gap-1 text-xs text-[#dce6fb]"
                                                        >
                                                            PDF{' '}
                                                            <ExternalLink className="size-3.5" />
                                                        </a>
                                                    ) : null}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </PageCard>

                        <PageCard className="space-y-5 p-6">
                            <SectionHeading
                                title="Monitors and sites"
                                description={
                                    collectionMeta.monitors?.truncated
                                        ? `Showing ${collectionMeta.monitors.shown} of ${collectionMeta.monitors.total} monitors, prioritising down and active sites.`
                                        : 'Targets, cadence, timeout, recent response timing, and the latest operational signal for each monitor.'
                                }
                            />
                            <div className="space-y-4">
                                {monitors.length === 0 ? (
                                    <div className="rounded-[18px] bg-[#121821] px-5 py-5 text-[14px] text-[#9ca7b9]">
                                        This account has not created any
                                        monitors yet.
                                    </div>
                                ) : (
                                    monitors.map((monitor) => (
                                        <div
                                            key={monitor.id}
                                            className="rounded-[18px] bg-[#121821] px-5 py-5"
                                        >
                                            <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                                <div className="min-w-0">
                                                    <div className="flex flex-wrap items-center gap-2 text-[16px] font-semibold text-white">
                                                        <span>
                                                            {monitor.name}
                                                        </span>
                                                        <span
                                                            className={cn(
                                                                'rounded-full px-2.5 py-1 text-[11px] tracking-[0.16em] uppercase',
                                                                monitor.statusValue ===
                                                                    'up' &&
                                                                    'bg-[#0f2527] text-[#9de5e0]',
                                                                monitor.statusValue ===
                                                                    'down' &&
                                                                    'bg-[#2a1621] text-[#ffd4d7]',
                                                                monitor.statusValue ===
                                                                    'paused' &&
                                                                    'bg-[#171c33] text-[#dbe1ff]',
                                                            )}
                                                        >
                                                            {monitor.status}
                                                        </span>
                                                        <span className="rounded-full border border-white/8 px-2.5 py-1 text-[11px] tracking-[0.16em] text-[#dce6fb] uppercase">
                                                            {monitor.typeLabel}
                                                        </span>
                                                    </div>
                                                    <div className="mt-2 text-[14px] break-all text-[#9ca7b9]">
                                                        {monitor.target ??
                                                            'No target stored'}
                                                    </div>
                                                    <div className="mt-2 flex flex-wrap gap-x-4 gap-y-2 text-[13px] text-[#7081a2]">
                                                        <span>
                                                            Region{' '}
                                                            {monitor.region}
                                                        </span>
                                                        <span>
                                                            Interval{' '}
                                                            {
                                                                monitor.intervalLabel
                                                            }
                                                        </span>
                                                        <span>
                                                            Timeout{' '}
                                                            {
                                                                monitor.timeoutLabel
                                                            }
                                                        </span>
                                                        <span>
                                                            Retries{' '}
                                                            {monitor.retries}
                                                        </span>
                                                        <span>
                                                            Last checked{' '}
                                                            {
                                                                monitor.lastCheckedLabel
                                                            }
                                                        </span>
                                                    </div>
                                                </div>
                                                <div className="grid gap-3 sm:grid-cols-2">
                                                    <div className="rounded-[14px] bg-[#171d28] px-4 py-3">
                                                        <div className="text-[12px] text-[#7f8eab]">
                                                            Response
                                                        </div>
                                                        <div className="mt-1 text-[16px] font-semibold text-white">
                                                            {
                                                                monitor.lastResponseTimeLabel
                                                            }
                                                        </div>
                                                    </div>
                                                    <div className="rounded-[14px] bg-[#171d28] px-4 py-3">
                                                        <div className="text-[12px] text-[#7f8eab]">
                                                            Open incidents
                                                        </div>
                                                        <div className="mt-1 text-[16px] font-semibold text-white">
                                                            {
                                                                monitor.openIncidentsCount
                                                            }
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            {monitor.lastHttpStatus ? (
                                                <div className="mt-3 text-[13px] text-[#9ca7b9]">
                                                    Last HTTP status{' '}
                                                    {monitor.lastHttpStatus}
                                                </div>
                                            ) : null}
                                            {monitor.lastError ? (
                                                <div className="mt-3 rounded-[14px] border border-[#ff6269]/20 bg-[#2a1621] px-4 py-3 text-[13px] text-[#ffd4d7]">
                                                    {monitor.lastError}
                                                </div>
                                            ) : null}
                                            {monitor.capabilities.length > 0 ? (
                                                <div className="mt-3 flex flex-wrap gap-2 text-[12px] text-[#dce6fb]">
                                                    {monitor.capabilities.map(
                                                        (capability) => (
                                                            <span
                                                                key={`${monitor.id}-${capability}`}
                                                                className="rounded-full bg-[#171d28] px-3 py-1"
                                                            >
                                                                {capability}
                                                            </span>
                                                        ),
                                                    )}
                                                </div>
                                            ) : null}
                                            {monitor.statusPages.length > 0 ||
                                            monitor.contacts.length > 0 ? (
                                                <div className="mt-3 grid gap-3 lg:grid-cols-2">
                                                    <div className="rounded-[14px] bg-[#171d28] px-4 py-3 text-[13px] text-[#9ca7b9]">
                                                        <div className="text-[12px] tracking-[0.18em] text-[#7f8eab] uppercase">
                                                            Status pages
                                                        </div>
                                                        <div className="mt-2 text-white">
                                                            {monitor.statusPages.join(
                                                                ', ',
                                                            ) || 'None linked'}
                                                        </div>
                                                    </div>
                                                    <div className="rounded-[14px] bg-[#171d28] px-4 py-3 text-[13px] text-[#9ca7b9]">
                                                        <div className="text-[12px] tracking-[0.18em] text-[#7f8eab] uppercase">
                                                            Contacts
                                                        </div>
                                                        <div className="mt-2 text-white">
                                                            {monitor.contacts.join(
                                                                ', ',
                                                            ) ||
                                                                'No contacts attached'}
                                                        </div>
                                                    </div>
                                                </div>
                                            ) : null}
                                        </div>
                                    ))
                                )}
                            </div>
                        </PageCard>

                        <PageCard className="space-y-5 p-6">
                            <SectionHeading
                                title="Status pages and public surface"
                                description="Published status hubs attached to this account, including linked monitors and incident post volume."
                            />
                            <div className="space-y-4">
                                {statusPages.length === 0 ? (
                                    <div className="rounded-[18px] bg-[#121821] px-5 py-5 text-[14px] text-[#9ca7b9]">
                                        No status pages have been created.
                                    </div>
                                ) : (
                                    statusPages.map((statusPage) => (
                                        <div
                                            key={statusPage.id}
                                            className="rounded-[18px] bg-[#121821] px-5 py-5"
                                        >
                                            <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                                <div>
                                                    <div className="flex flex-wrap items-center gap-2 text-[16px] font-semibold text-white">
                                                        <span>
                                                            {statusPage.name}
                                                        </span>
                                                        <span
                                                            className={cn(
                                                                'rounded-full px-2.5 py-1 text-[11px] tracking-[0.16em] uppercase',
                                                                statusPage.published
                                                                    ? 'bg-[#0f2527] text-[#9de5e0]'
                                                                    : 'bg-[#171c33] text-[#dbe1ff]',
                                                            )}
                                                        >
                                                            {statusPage.published
                                                                ? 'Published'
                                                                : 'Draft'}
                                                        </span>
                                                    </div>
                                                    <div className="mt-2 text-[14px] text-[#9ca7b9]">
                                                        {statusPage.headline ??
                                                            statusPage.slug}
                                                    </div>
                                                    <div className="mt-2 flex flex-wrap gap-x-4 gap-y-2 text-[13px] text-[#7081a2]">
                                                        <span>
                                                            {
                                                                statusPage.monitorCount
                                                            }{' '}
                                                            monitors
                                                        </span>
                                                        <span>
                                                            {
                                                                statusPage.incidentsCount
                                                            }{' '}
                                                            incident posts
                                                        </span>
                                                        <span>
                                                            Slug{' '}
                                                            {statusPage.slug}
                                                        </span>
                                                    </div>
                                                </div>
                                                {statusPage.published ? (
                                                    <a
                                                        href={
                                                            statusPage.publicUrl
                                                        }
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="inline-flex items-center gap-2 rounded-[12px] border border-white/10 bg-white/5 px-3 py-2 text-xs text-white"
                                                    >
                                                        Open public page
                                                        <ExternalLink className="size-3.5" />
                                                    </a>
                                                ) : null}
                                            </div>
                                            {statusPage.monitorNames.length >
                                            0 ? (
                                                <div className="mt-3 flex flex-wrap gap-2 text-[12px] text-[#dce6fb]">
                                                    {statusPage.monitorNames.map(
                                                        (monitor) => (
                                                            <span
                                                                key={`${statusPage.id}-${monitor}`}
                                                                className="rounded-full bg-[#171d28] px-3 py-1"
                                                            >
                                                                {monitor}
                                                            </span>
                                                        ),
                                                    )}
                                                </div>
                                            ) : null}
                                        </div>
                                    ))
                                )}
                            </div>
                        </PageCard>
                    </section>

                    <aside className="space-y-5">
                        <PageCard className="space-y-5 p-6">
                            <SectionHeading
                                title="Support actions"
                                description="Update account details, plan overrides, and time-boxed courtesy membership extensions."
                            />

                            <div className="space-y-4">
                                <div className="rounded-[18px] bg-[#121821] px-5 py-5">
                                    <div className="flex items-center gap-2 text-[15px] font-semibold text-white">
                                        <ShieldCheck className="size-4 text-[#7c8cff]" />
                                        Account details
                                    </div>
                                    {account.isAdmin ? (
                                        <div className="mt-2 flex items-start gap-2 text-[13px] text-[#dbe1ff]">
                                            <CheckCircle2 className="mt-0.5 size-4 shrink-0" />
                                            This is the configured main admin.
                                            Its email is locked to
                                            REALUPTIME_MAIN_ADMIN_EMAIL.
                                        </div>
                                    ) : null}
                                    <form
                                        className="mt-4 space-y-3"
                                        onSubmit={(event: FormEvent) => {
                                            event.preventDefault();
                                            accountForm.patch(
                                                `/admin/users/${account.id}`,
                                                {
                                                    preserveScroll: true,
                                                    onSuccess: () =>
                                                        accountForm.reset(
                                                            'password',
                                                            'password_confirmation',
                                                        ),
                                                },
                                            );
                                        }}
                                    >
                                        {Object.values(accountForm.errors)
                                            .length > 0 ? (
                                            <div className="text-sm text-[#ffd4d7]">
                                                {Object.values(
                                                    accountForm.errors,
                                                ).join(' ')}
                                            </div>
                                        ) : null}
                                        <label className="block space-y-1.5">
                                            <span className="text-sm text-[#9ca7b9]">
                                                Name
                                            </span>
                                            <input
                                                value={accountForm.data.name}
                                                onChange={(event) =>
                                                    accountForm.setData(
                                                        'name',
                                                        event.target.value,
                                                    )
                                                }
                                                className="h-10 w-full rounded-md border border-white/10 bg-[#0b1425] px-3 text-sm text-white outline-none"
                                            />
                                        </label>
                                        <label className="block space-y-1.5">
                                            <span className="text-sm text-[#9ca7b9]">
                                                Email
                                            </span>
                                            <input
                                                type="email"
                                                value={accountForm.data.email}
                                                disabled={account.isAdmin}
                                                onChange={(event) =>
                                                    accountForm.setData(
                                                        'email',
                                                        event.target.value,
                                                    )
                                                }
                                                className="h-10 w-full rounded-md border border-white/10 bg-[#0b1425] px-3 text-sm text-white outline-none disabled:cursor-not-allowed disabled:opacity-60"
                                            />
                                        </label>
                                        <div className="grid gap-3 sm:grid-cols-2">
                                            <label className="block space-y-1.5">
                                                <span className="text-sm text-[#9ca7b9]">
                                                    New password
                                                </span>
                                                <input
                                                    type="password"
                                                    value={
                                                        accountForm.data
                                                            .password
                                                    }
                                                    onChange={(event) =>
                                                        accountForm.setData(
                                                            'password',
                                                            event.target.value,
                                                        )
                                                    }
                                                    className="h-10 w-full rounded-md border border-white/10 bg-[#0b1425] px-3 text-sm text-white outline-none"
                                                    placeholder="Leave unchanged"
                                                />
                                            </label>
                                            <label className="block space-y-1.5">
                                                <span className="text-sm text-[#9ca7b9]">
                                                    Confirm password
                                                </span>
                                                <input
                                                    type="password"
                                                    value={
                                                        accountForm.data
                                                            .password_confirmation
                                                    }
                                                    onChange={(event) =>
                                                        accountForm.setData(
                                                            'password_confirmation',
                                                            event.target.value,
                                                        )
                                                    }
                                                    className="h-10 w-full rounded-md border border-white/10 bg-[#0b1425] px-3 text-sm text-white outline-none"
                                                />
                                            </label>
                                        </div>
                                        <div className="flex flex-col gap-2 text-sm text-[#dce6fb]">
                                            <label className="flex items-center gap-2">
                                                <input
                                                    type="checkbox"
                                                    disabled={account.isAdmin}
                                                    checked={
                                                        accountForm.data
                                                            .email_verified
                                                    }
                                                    onChange={(event) =>
                                                        accountForm.setData(
                                                            'email_verified',
                                                            event.target
                                                                .checked,
                                                        )
                                                    }
                                                    className="size-4 rounded border-white/20 bg-[#0b1425] disabled:cursor-not-allowed disabled:opacity-60"
                                                />
                                                Email verified
                                            </label>
                                            <label className="flex items-center gap-2">
                                                <input
                                                    type="checkbox"
                                                    disabled={account.isAdmin}
                                                    checked={
                                                        accountForm.data
                                                            .password_login_enabled
                                                    }
                                                    onChange={(event) =>
                                                        accountForm.setData(
                                                            'password_login_enabled',
                                                            event.target
                                                                .checked,
                                                        )
                                                    }
                                                    className="size-4 rounded border-white/20 bg-[#0b1425] disabled:cursor-not-allowed disabled:opacity-60"
                                                />
                                                Password login enabled
                                            </label>
                                        </div>
                                        <button
                                            type="submit"
                                            disabled={accountForm.processing}
                                            className="inline-flex h-10 items-center gap-2 rounded-md bg-[#7c8cff] px-4 text-sm font-medium text-white disabled:opacity-60"
                                        >
                                            <Save className="size-4" />
                                            Save account
                                        </button>
                                    </form>
                                </div>

                                <div className="rounded-[18px] bg-[#121821] px-5 py-5">
                                    <div className="text-[15px] font-semibold text-white">
                                        Membership override
                                    </div>
                                    <div className="mt-2 text-[14px] text-[#9ca7b9]">
                                        Force a plan indefinitely, or return
                                        plan resolution to Stripe / free
                                        defaults.
                                    </div>
                                    {support.adminOverride ? (
                                        <div className="mt-3 rounded-[14px] border border-[#7c8cff]/20 bg-[#171c33] px-4 py-3 text-[13px] text-[#dbe1ff]">
                                            Active override:{' '}
                                            {support.adminOverride.planLabel}
                                            {support.adminOverride.assignedBy
                                                ? ` by ${support.adminOverride.assignedBy}`
                                                : ''}
                                            {support.adminOverride.assignedAt
                                                ? ` on ${support.adminOverride.assignedAt}`
                                                : ''}
                                        </div>
                                    ) : null}
                                    <div className="mt-4 flex flex-wrap gap-2">
                                        {[
                                            {
                                                value: '',
                                                label: 'Subscription / default',
                                            },
                                            { value: 'free', label: 'Free' },
                                            {
                                                value: 'premium',
                                                label: 'Premium',
                                            },
                                            { value: 'ultra', label: 'Ultra' },
                                        ].map((option) => {
                                            const active =
                                                (support.adminOverride?.plan ??
                                                    '') === option.value;

                                            return (
                                                <button
                                                    key={
                                                        option.value ||
                                                        'default'
                                                    }
                                                    type="button"
                                                    onClick={() =>
                                                        router.patch(
                                                            `/admin/users/${account.id}/membership`,
                                                            {
                                                                admin_plan_override:
                                                                    option.value ||
                                                                    null,
                                                            },
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                    className={cn(
                                                        'rounded-[12px] px-3 py-2 text-xs font-medium',
                                                        active
                                                            ? 'bg-[#7c8cff] text-white'
                                                            : 'border border-white/10 bg-[#171d28] text-[#dce6fb]',
                                                    )}
                                                >
                                                    {option.label}
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>

                                <div className="rounded-[18px] bg-[#121821] px-5 py-5">
                                    <div className="flex items-center gap-2 text-[15px] font-semibold text-white">
                                        <TimerReset className="size-4 text-[#57c7c2]" />
                                        Courtesy extension
                                    </div>
                                    <div className="mt-2 text-[14px] text-[#9ca7b9]">
                                        Add one month of paid access without
                                        permanently overriding the account plan.
                                    </div>
                                    {support.courtesyExtension ? (
                                        <div className="mt-3 rounded-[14px] border border-[#57c7c2]/20 bg-[#0f2527] px-4 py-3 text-[13px] text-[#9de5e0]">
                                            {
                                                support.courtesyExtension
                                                    .planLabel
                                            }{' '}
                                            until{' '}
                                            {support.courtesyExtension
                                                .expiresAt ?? 'set'}
                                            {support.courtesyExtension.grantedBy
                                                ? ` • granted by ${support.courtesyExtension.grantedBy}`
                                                : ''}
                                        </div>
                                    ) : null}
                                    <div className="mt-4 flex flex-wrap gap-2">
                                        {support.supportPlanOptions.map(
                                            (option) => (
                                                <button
                                                    key={option.value}
                                                    type="button"
                                                    onClick={() =>
                                                        router.patch(
                                                            `/admin/users/${account.id}/support-extension`,
                                                            {
                                                                plan: option.value,
                                                            },
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                    className="rounded-[12px] border border-[#57c7c2]/20 bg-[#0f2527] px-3 py-2 text-xs font-medium text-[#9de5e0]"
                                                >
                                                    Add 1 month {option.label}
                                                </button>
                                            ),
                                        )}
                                        {support.courtesyExtension ? (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    router.delete(
                                                        `/admin/users/${account.id}/support-extension`,
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                                className="rounded-[12px] border border-white/10 bg-[#171d28] px-3 py-2 text-xs font-medium text-[#dce6fb]"
                                            >
                                                Clear extension
                                            </button>
                                        ) : null}
                                    </div>
                                </div>

                                {!isCurrentAdmin ? (
                                    <div className="rounded-[18px] border border-[#ff6269]/20 bg-[#2a1621] px-5 py-5">
                                        <div className="text-[15px] font-semibold text-white">
                                            Danger zone
                                        </div>
                                        <div className="mt-2 text-[14px] text-[#ffd4d7]">
                                            Delete the account and workspace
                                            data. Any current Stripe
                                            subscription is cancelled
                                            immediately before deletion.
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => {
                                                if (
                                                    window.confirm(
                                                        `Delete user "${account.email}"? This also deletes monitors, incidents, status pages, and workspace data.`,
                                                    )
                                                ) {
                                                    router.delete(
                                                        `/admin/users/${account.id}`,
                                                    );
                                                }
                                            }}
                                            className="mt-4 inline-flex items-center gap-2 rounded-[14px] border border-[#ff6269]/25 bg-[#231320] px-4 py-2.5 text-sm text-[#ffd4d7]"
                                        >
                                            <Trash2 className="size-4" />
                                            Delete account
                                        </button>
                                    </div>
                                ) : null}
                            </div>
                        </PageCard>

                        <PageCard className="space-y-5 p-6">
                            <SectionHeading
                                title="Automation surface"
                                description="Contacts, webhook integrations, and API tokens attached to this workspace."
                            />

                            <div className="grid gap-3 sm:grid-cols-3 xl:grid-cols-1">
                                <div className="rounded-[16px] bg-[#121821] px-4 py-4">
                                    <div className="flex items-center gap-2 text-sm text-[#9ca7b9]">
                                        <Mail className="size-4 text-[#7c8cff]" />
                                        Contacts
                                    </div>
                                    <div className="mt-2 text-[20px] font-semibold text-white">
                                        {usage.contacts}
                                    </div>
                                </div>
                                <div className="rounded-[16px] bg-[#121821] px-4 py-4">
                                    <div className="flex items-center gap-2 text-sm text-[#9ca7b9]">
                                        <Cable className="size-4 text-[#7c8cff]" />
                                        Integrations
                                    </div>
                                    <div className="mt-2 text-[20px] font-semibold text-white">
                                        {usage.integrations}
                                    </div>
                                </div>
                                <div className="rounded-[16px] bg-[#121821] px-4 py-4">
                                    <div className="flex items-center gap-2 text-sm text-[#9ca7b9]">
                                        <KeyRound className="size-4 text-[#7c8cff]" />
                                        API tokens
                                    </div>
                                    <div className="mt-2 text-[20px] font-semibold text-white">
                                        {usage.apiTokens}
                                    </div>
                                </div>
                            </div>

                            {contacts.length > 0 ? (
                                <div className="space-y-3">
                                    <div className="text-[15px] font-semibold text-white">
                                        Notification contacts
                                    </div>
                                    {contacts.map((contact) => (
                                        <div
                                            key={contact.id}
                                            className="rounded-[16px] bg-[#121821] px-4 py-4 text-[13px] text-[#9ca7b9]"
                                        >
                                            <div className="flex flex-wrap items-center gap-2 text-[14px] font-medium text-white">
                                                <span>{contact.name}</span>
                                                {contact.isPrimary ? (
                                                    <span className="rounded-full bg-[#171c33] px-2 py-1 text-[11px] tracking-[0.16em] text-[#dbe1ff] uppercase">
                                                        Primary
                                                    </span>
                                                ) : null}
                                                {!contact.enabled ? (
                                                    <span className="rounded-full bg-[#231320] px-2 py-1 text-[11px] tracking-[0.16em] text-[#ffd4d7] uppercase">
                                                        Disabled
                                                    </span>
                                                ) : null}
                                            </div>
                                            <div className="mt-1 break-all">
                                                {contact.email}
                                            </div>
                                            <div className="mt-2 text-white">
                                                {contact.monitorNames.join(
                                                    ', ',
                                                ) || 'No monitors assigned'}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : null}

                            {integrations.length > 0 ? (
                                <div className="space-y-3">
                                    <div className="text-[15px] font-semibold text-white">
                                        Webhook integrations
                                    </div>
                                    {integrations.map((integration) => (
                                        <div
                                            key={integration.id}
                                            className="rounded-[16px] bg-[#121821] px-4 py-4 text-[13px] text-[#9ca7b9]"
                                        >
                                            <div className="flex flex-wrap items-center gap-2 text-[14px] font-medium text-white">
                                                <span>{integration.name}</span>
                                                <span className="rounded-full bg-[#171c33] px-2 py-1 text-[11px] tracking-[0.16em] text-[#dbe1ff] uppercase">
                                                    {integration.status}
                                                </span>
                                            </div>
                                            <div className="mt-1">
                                                {integration.provider}
                                            </div>
                                            <div className="mt-2 text-white">
                                                {integration.events.join(
                                                    ', ',
                                                ) || 'All events'}
                                            </div>
                                            {integration.lastError ? (
                                                <div className="mt-2 text-[#ffd4d7]">
                                                    {integration.lastError}
                                                </div>
                                            ) : null}
                                        </div>
                                    ))}
                                </div>
                            ) : null}

                            {apiTokens.length > 0 ? (
                                <div className="space-y-3">
                                    <div className="text-[15px] font-semibold text-white">
                                        API tokens
                                    </div>
                                    {apiTokens.map((token) => (
                                        <div
                                            key={token.id}
                                            className="rounded-[16px] bg-[#121821] px-4 py-4 text-[13px] text-[#9ca7b9]"
                                        >
                                            <div className="text-[14px] font-medium text-white">
                                                {token.name}
                                            </div>
                                            <div className="mt-1">
                                                Created{' '}
                                                {token.createdAt ?? 'Unknown'}
                                            </div>
                                            <div className="mt-1">
                                                Last used {token.lastUsedLabel}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : null}
                        </PageCard>

                        <PageCard className="space-y-5 p-6">
                            <SectionHeading
                                title="Workspace people and sessions"
                                description="Recent membership invites and the latest tracked sessions for this account."
                            />

                            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                                <div className="rounded-[16px] bg-[#121821] px-4 py-4">
                                    <div className="flex items-center gap-2 text-sm text-[#9ca7b9]">
                                        <Users className="size-4 text-[#7c8cff]" />
                                        Team members
                                    </div>
                                    <div className="mt-2 text-[20px] font-semibold text-white">
                                        {usage.acceptedMembers}
                                    </div>
                                </div>
                                <div className="rounded-[16px] bg-[#121821] px-4 py-4">
                                    <div className="text-sm text-[#9ca7b9]">
                                        Pending invites
                                    </div>
                                    <div className="mt-2 text-[20px] font-semibold text-white">
                                        {usage.pendingInvitations}
                                    </div>
                                </div>
                            </div>

                            {team.length > 0 ? (
                                <div className="space-y-3">
                                    {team.map((member) => (
                                        <div
                                            key={member.id}
                                            className="rounded-[16px] bg-[#121821] px-4 py-4 text-[13px] text-[#9ca7b9]"
                                        >
                                            <div className="flex flex-wrap items-center gap-2 text-[14px] font-medium text-white">
                                                <span>
                                                    {member.memberName ??
                                                        member.email}
                                                </span>
                                                <span className="rounded-full bg-[#171c33] px-2 py-1 text-[11px] tracking-[0.16em] text-[#dbe1ff] uppercase">
                                                    {member.status}
                                                </span>
                                            </div>
                                            <div className="mt-1 break-all">
                                                {member.memberEmail ??
                                                    member.email}
                                            </div>
                                            <div className="mt-2">
                                                Invited{' '}
                                                {member.invitedAt ?? 'Unknown'}
                                                {member.acceptedAt
                                                    ? ` • Accepted ${member.acceptedAt}`
                                                    : ''}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : null}

                            {sessions.length > 0 ? (
                                <div className="space-y-3">
                                    {sessions.map((session) => (
                                        <div
                                            key={session.id}
                                            className="rounded-[16px] bg-[#121821] px-4 py-4 text-[13px] text-[#9ca7b9]"
                                        >
                                            <div className="flex flex-wrap items-center gap-2 text-[14px] font-medium text-white">
                                                <span>
                                                    {session.lastActiveLabel}
                                                </span>
                                                <span
                                                    className={cn(
                                                        'rounded-full px-2 py-1 text-[11px] tracking-[0.16em] uppercase',
                                                        session.active
                                                            ? 'bg-[#0f2527] text-[#9de5e0]'
                                                            : 'bg-[#231320] text-[#ffd4d7]',
                                                    )}
                                                >
                                                    {session.active
                                                        ? 'Active'
                                                        : 'Revoked'}
                                                </span>
                                            </div>
                                            <div className="mt-1">
                                                {session.ipAddress ??
                                                    'Unknown IP'}
                                            </div>
                                            <div className="mt-1 break-all">
                                                {session.lastPath ??
                                                    'No path recorded'}
                                            </div>
                                            <div className="mt-2 break-words">
                                                {session.userAgent ??
                                                    'No user agent recorded'}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : null}
                        </PageCard>

                        <PageCard className="space-y-5 p-6">
                            <SectionHeading
                                title="Recent activity"
                                description="Recent incidents and outgoing notifications for support context."
                            />

                            <div className="space-y-3">
                                {recentIncidents.length === 0 ? (
                                    <div className="rounded-[16px] bg-[#121821] px-4 py-4 text-[14px] text-[#9ca7b9]">
                                        No recent incidents for this account.
                                    </div>
                                ) : (
                                    recentIncidents.map((incident) => (
                                        <div
                                            key={incident.id}
                                            className="rounded-[16px] bg-[#121821] px-4 py-4 text-[13px] text-[#9ca7b9]"
                                        >
                                            <div className="flex flex-wrap items-center gap-2 text-[14px] font-medium text-white">
                                                <span>
                                                    {incident.monitor ??
                                                        'Unknown monitor'}
                                                </span>
                                                <span className="rounded-full bg-[#171c33] px-2 py-1 text-[11px] tracking-[0.16em] text-[#dbe1ff] uppercase">
                                                    {incident.status}
                                                </span>
                                            </div>
                                            <div className="mt-2 text-white">
                                                {incident.reason}
                                            </div>
                                            <div className="mt-2">
                                                Started{' '}
                                                {incident.startedAt ??
                                                    'Unknown'}
                                                {incident.resolvedAt
                                                    ? ` • Resolved ${incident.resolvedAt}`
                                                    : ''}
                                                {incident.duration
                                                    ? ` • Duration ${incident.duration}`
                                                    : ''}
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>

                            <div className="space-y-3">
                                {recentNotifications.length === 0 ? (
                                    <div className="rounded-[16px] bg-[#121821] px-4 py-4 text-[14px] text-[#9ca7b9]">
                                        No recent notifications for this
                                        account.
                                    </div>
                                ) : (
                                    recentNotifications.map((notification) => (
                                        <div
                                            key={notification.id}
                                            className="rounded-[16px] bg-[#121821] px-4 py-4 text-[13px] text-[#9ca7b9]"
                                        >
                                            <div className="flex flex-wrap items-center gap-2 text-[14px] font-medium text-white">
                                                <span>{notification.type}</span>
                                                <span className="rounded-full bg-[#171c33] px-2 py-1 text-[11px] tracking-[0.16em] text-[#dbe1ff] uppercase">
                                                    {notification.status}
                                                </span>
                                            </div>
                                            <div className="mt-2 text-white">
                                                {notification.subject}
                                            </div>
                                            <div className="mt-2">
                                                {notification.destination}
                                            </div>
                                            <div className="mt-1">
                                                {notification.monitor ??
                                                    'Unknown monitor'}{' '}
                                                •{' '}
                                                {notification.sentAt ??
                                                    'Unknown time'}
                                            </div>
                                            {notification.failureMessage ? (
                                                <div className="mt-2 text-[#ffd4d7]">
                                                    {
                                                        notification.failureMessage
                                                    }
                                                </div>
                                            ) : null}
                                        </div>
                                    ))
                                )}
                            </div>
                        </PageCard>
                    </aside>
                </div>
            </div>
        </MonitoringLayout>
    );
}

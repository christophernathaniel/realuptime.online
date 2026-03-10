import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Activity, Copy, KeyRound, Mail, Save, ServerCog, Trash2, TriangleAlert } from 'lucide-react';
import type { FormEvent } from 'react';
import { PageCard } from '@/components/monitoring/page-card';
import { PaginationStrip } from '@/components/monitoring/pagination-strip';
import MonitoringLayout from '@/layouts/monitoring-layout';
import type {
    ApiTokenFormData,
    ApiTokenItem,
    ContactFormData,
    IntegrationContact,
    IntegrationRuntime,
    IntegrationSummary,
    NotificationLogItem,
    PaginatedData,
} from '@/types/monitoring';

function MetricCard({ label, value, tone = 'default' }: { label: string; value: string | number; tone?: 'default' | 'good' | 'warning' | 'danger' }) {
    const toneClass = {
        default: 'text-white',
        good: 'text-[#7c8cff]',
        warning: 'text-[#7c8cff]',
        danger: 'text-[#ff7f87]',
    }[tone];

    return (
        <PageCard className="px-5 py-4">
            <div className="text-sm text-[#9ca7b9]">{label}</div>
            <div className={`mt-1 text-[28px] font-semibold ${toneClass}`}>{value}</div>
        </PageCard>
    );
}

function RuntimeRow({ label, value }: { label: string; value: string | number }) {
    return (
        <div className="flex items-center justify-between gap-4 rounded-[14px] bg-[#171d28] px-4 py-3">
            <div className="text-[14px] text-[#9ca7b9]">{label}</div>
            <div className="text-right text-[14px] font-medium text-white">{value}</div>
        </div>
    );
}

function copyText(value: string) {
    if (typeof navigator === 'undefined' || !navigator.clipboard) {
        return;
    }

    void navigator.clipboard.writeText(value);
}

function ContactEditor({ contact }: { contact: IntegrationContact }) {
    const form = useForm<ContactFormData>({
        name: contact.name,
        email: contact.email,
        enabled: contact.enabled,
        is_primary: contact.isPrimary,
    });
    const errors = Object.values(form.errors);

    return (
        <PageCard className="space-y-4 p-5">
            <div className="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <div className="text-[18px] font-semibold text-white">{contact.name}</div>
                    <div className="mt-1 text-[14px] text-[#9ca7b9]">
                        {contact.logsCount} notification logs • {contact.monitorNames.join(', ') || 'Not assigned'}
                    </div>
                </div>
                <button
                    type="button"
                    className="inline-flex items-center gap-2 rounded-[14px] border border-[#ff6269]/25 bg-[#231320] px-4 py-2.5 text-sm text-[#ffd4d7]"
                    onClick={() => {
                        if (window.confirm(`Delete notification contact "${contact.name}"?`)) {
                            router.delete(`/notification-contacts/${contact.id}`, { preserveScroll: true });
                        }
                    }}
                >
                    <Trash2 className="size-4" />
                    Delete
                </button>
            </div>
            <form
                className="grid gap-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(`/notification-contacts/${contact.id}`, { preserveScroll: true });
                }}
            >
                {errors.length > 0 ? (
                    <div className="rounded-[16px] border border-[#ff6269]/20 bg-[#2a1621] px-4 py-3 text-sm text-[#ffd4d7]">
                        {errors.join(' ')}
                    </div>
                ) : null}
                <div className="grid gap-4 lg:grid-cols-2">
                    <label className="space-y-2">
                        <span className="text-[15px] text-[#dce6fb]">Name</span>
                        <input
                            value={form.data.name}
                            onChange={(event) => form.setData('name', event.target.value)}
                            className="h-11 w-full rounded-[14px] border border-white/10 bg-[#0b1425] px-4 text-sm text-white outline-none"
                        />
                    </label>
                    <label className="space-y-2">
                        <span className="text-[15px] text-[#dce6fb]">Email</span>
                        <input
                            value={form.data.email}
                            onChange={(event) => form.setData('email', event.target.value)}
                            className="h-11 w-full rounded-[14px] border border-white/10 bg-[#0b1425] px-4 text-sm text-white outline-none"
                        />
                    </label>
                </div>
                <div className="grid gap-3 md:grid-cols-2">
                    <label className="flex items-center gap-3 rounded-[16px] border border-white/8 bg-[#171d28] px-4 py-3 text-sm text-[#dce6fb]">
                        <input
                            type="checkbox"
                            checked={form.data.enabled}
                            onChange={(event) => form.setData('enabled', event.target.checked)}
                            className="size-4 rounded border-white/15 bg-[#121821]"
                        />
                        Receive notifications
                    </label>
                    <label className="flex items-center gap-3 rounded-[16px] border border-white/8 bg-[#171d28] px-4 py-3 text-sm text-[#dce6fb]">
                        <input
                            type="checkbox"
                            checked={form.data.is_primary}
                            onChange={(event) => form.setData('is_primary', event.target.checked)}
                            className="size-4 rounded border-white/15 bg-[#121821]"
                        />
                        Set as primary contact
                    </label>
                </div>
                <div className="flex justify-end">
                    <button type="submit" className="inline-flex items-center gap-2 rounded-[14px] bg-[#7c8cff] px-4 py-2.5 text-sm font-medium text-white">
                        <Save className="size-4" />
                        Save contact
                    </button>
                </div>
            </form>
        </PageCard>
    );
}

type IntegrationsPageProps = {
    summary: IntegrationSummary;
    runtime: IntegrationRuntime;
    contacts: IntegrationContact[];
    recentLogs: PaginatedData<NotificationLogItem>;
    apiTokens: ApiTokenItem[];
    formDefaults: ContactFormData;
    tokenFormDefaults: ApiTokenFormData;
};

export default function IntegrationsPage({
    summary,
    runtime,
    contacts,
    recentLogs,
    apiTokens,
    formDefaults,
    tokenFormDefaults,
}: IntegrationsPageProps) {
    const contactForm = useForm<ContactFormData>(formDefaults);
    const tokenForm = useForm<ApiTokenFormData>(tokenFormDefaults);
    const contactErrors = Object.values(contactForm.errors);
    const tokenErrors = Object.values(tokenForm.errors);
    const flash = usePage<{
        flash?: {
            apiToken?: {
                name: string;
                token: string;
            } | null;
        };
    }>().props.flash;
    const revealedToken = flash?.apiToken ?? null;
    const sampleToken = revealedToken?.token ?? '<token>';
    const sampleCurl = `curl -H "Authorization: Bearer ${sampleToken}" ${runtime.apiBaseUrl}/monitors`;

    return (
        <MonitoringLayout>
            <Head title="Automation & API" />
            <div className="space-y-5">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h1 className="text-[40px] font-semibold tracking-[-0.06em] text-white lg:text-[46px]">
                            Automation &amp; API<span className="text-[#7c8cff]">.</span>
                        </h1>
                        <div className="mt-2 max-w-[820px] text-[16px] text-[#9ca7b9]">
                            Manage email contacts, issue tokens for automation, and verify that queues, workers, and mail delivery are configured correctly before deployment.
                        </div>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-3 xl:grid-cols-6">
                        <MetricCard label="Contacts" value={summary.contacts} />
                        <MetricCard label="Enabled" value={summary.enabled} tone="good" />
                        <MetricCard label="Tokens" value={summary.apiTokens} />
                        <MetricCard label="Sent" value={summary.emailsSent} tone="good" />
                        <MetricCard label="Pending" value={summary.emailsPending} tone="warning" />
                        <MetricCard label="Failed" value={summary.emailsFailed} tone={summary.emailsFailed > 0 ? 'danger' : 'default'} />
                    </div>
                </div>

                <div className="grid gap-5 xl:grid-cols-[minmax(0,1.08fr)_420px]">
                    <section className="space-y-4">
                        <PageCard className="space-y-5 p-6">
                            <div className="flex items-center gap-3 text-[22px] font-semibold text-white">
                                <KeyRound className="size-5 text-[#7c8cff]" />
                                API access
                            </div>
                            <div className="text-[15px] text-[#9ca7b9]">
                                Generate bearer tokens for scripts, CI pipelines, or external dashboards. Tokens are only shown once.
                            </div>
                            <form
                                className="grid gap-4 md:grid-cols-[minmax(0,1fr)_auto]"
                                onSubmit={(event: FormEvent) => {
                                    event.preventDefault();
                                    tokenForm.post('/api-tokens', {
                                        preserveScroll: true,
                                        onSuccess: () => tokenForm.reset('name'),
                                    });
                                }}
                            >
                                <label className="space-y-2">
                                    <span className="text-[15px] text-[#dce6fb]">Token name</span>
                                    <input
                                        value={tokenForm.data.name}
                                        onChange={(event) => tokenForm.setData('name', event.target.value)}
                                        className="h-11 w-full rounded-[14px] border border-white/10 bg-[#0b1425] px-4 text-sm text-white outline-none"
                                        placeholder="Primary automation"
                                    />
                                </label>
                                <div className="flex items-end">
                                    <button type="submit" className="inline-flex h-11 items-center gap-2 rounded-[14px] bg-[#7c8cff] px-4 text-sm font-medium text-white">
                                        <KeyRound className="size-4" />
                                        Create token
                                    </button>
                                </div>
                            </form>
                            {tokenErrors.length > 0 ? (
                                <div className="rounded-[16px] border border-[#ff6269]/20 bg-[#2a1621] px-4 py-3 text-sm text-[#ffd4d7]">
                                    {tokenErrors.join(' ')}
                                </div>
                            ) : null}

                            {revealedToken ? (
                                <div className="rounded-[18px] border border-[#7c8cff]/20 bg-[#171c33] p-5">
                                    <div className="flex items-start justify-between gap-4">
                                        <div>
                                            <div className="text-[15px] font-medium text-white">New token created: {revealedToken.name}</div>
                                            <div className="mt-1 text-[13px] text-[#9fd6b1]">Copy it now. The plain token will not be shown again.</div>
                                        </div>
                                        <button
                                            type="button"
                                            className="inline-flex items-center gap-2 rounded-[12px] border border-white/10 bg-white/5 px-3 py-2 text-xs text-white"
                                            onClick={() => copyText(revealedToken.token)}
                                        >
                                            <Copy className="size-3.5" />
                                            Copy token
                                        </button>
                                    </div>
                                    <div className="mt-4 overflow-x-auto rounded-[14px] bg-[#081428] px-4 py-3 font-mono text-[12px] text-[#dce6fb]">
                                        {revealedToken.token}
                                    </div>
                                </div>
                            ) : null}

                            <div className="space-y-3">
                                {apiTokens.length === 0 ? (
                                    <div className="rounded-[16px] bg-[#171d28] px-4 py-4 text-[14px] text-[#9ca7b9]">
                                        No tokens issued yet.
                                    </div>
                                ) : (
                                    apiTokens.map((token) => (
                                        <div key={token.id} className="flex flex-col gap-3 rounded-[16px] bg-[#171d28] px-4 py-4 md:flex-row md:items-center md:justify-between">
                                            <div>
                                                <div className="text-[15px] font-medium text-white">{token.name}</div>
                                                <div className="mt-1 text-[13px] text-[#9ca7b9]">Created {token.createdAt ?? 'Unknown'} • {token.lastUsedLabel}</div>
                                            </div>
                                            <button
                                                type="button"
                                                className="inline-flex items-center gap-2 rounded-[12px] border border-[#ff6269]/25 bg-[#231320] px-3 py-2 text-sm text-[#ffd4d7]"
                                                onClick={() => {
                                                    if (window.confirm(`Revoke token "${token.name}"?`)) {
                                                        router.delete(`/api-tokens/${token.id}`, { preserveScroll: true });
                                                    }
                                                }}
                                            >
                                                <Trash2 className="size-4" />
                                                Revoke
                                            </button>
                                        </div>
                                    ))
                                )}
                            </div>
                        </PageCard>

                        <PageCard className="space-y-5 p-6">
                            <div className="flex items-center gap-3 text-[22px] font-semibold text-white">
                                <Mail className="size-5 text-[#7c8cff]" />
                                Add notification contact
                            </div>
                            <form
                                className="grid gap-4"
                                onSubmit={(event: FormEvent) => {
                                    event.preventDefault();
                                    contactForm.post('/notification-contacts', {
                                        preserveScroll: true,
                                        onSuccess: () => contactForm.reset(),
                                    });
                                }}
                            >
                                {contactErrors.length > 0 ? (
                                    <div className="rounded-[16px] border border-[#ff6269]/20 bg-[#2a1621] px-4 py-3 text-sm text-[#ffd4d7]">
                                        {contactErrors.join(' ')}
                                    </div>
                                ) : null}
                                <div className="grid gap-4 lg:grid-cols-2">
                                    <label className="space-y-2">
                                        <span className="text-[15px] text-[#dce6fb]">Name</span>
                                        <input
                                            value={contactForm.data.name}
                                            onChange={(event) => contactForm.setData('name', event.target.value)}
                                            className="h-11 w-full rounded-[14px] border border-white/10 bg-[#0b1425] px-4 text-sm text-white outline-none"
                                            placeholder="Operations"
                                        />
                                    </label>
                                    <label className="space-y-2">
                                        <span className="text-[15px] text-[#dce6fb]">Email</span>
                                        <input
                                            value={contactForm.data.email}
                                            onChange={(event) => contactForm.setData('email', event.target.value)}
                                            className="h-11 w-full rounded-[14px] border border-white/10 bg-[#0b1425] px-4 text-sm text-white outline-none"
                                            placeholder="ops@example.com"
                                        />
                                    </label>
                                </div>
                                <div className="grid gap-3 md:grid-cols-2">
                                    <label className="flex items-center gap-3 rounded-[16px] border border-white/8 bg-[#171d28] px-4 py-3 text-sm text-[#dce6fb]">
                                        <input
                                            type="checkbox"
                                            checked={contactForm.data.enabled}
                                            onChange={(event) => contactForm.setData('enabled', event.target.checked)}
                                            className="size-4 rounded border-white/15 bg-[#121821]"
                                        />
                                        Receive notifications
                                    </label>
                                    <label className="flex items-center gap-3 rounded-[16px] border border-white/8 bg-[#171d28] px-4 py-3 text-sm text-[#dce6fb]">
                                        <input
                                            type="checkbox"
                                            checked={contactForm.data.is_primary}
                                            onChange={(event) => contactForm.setData('is_primary', event.target.checked)}
                                            className="size-4 rounded border-white/15 bg-[#121821]"
                                        />
                                        Set as primary contact
                                    </label>
                                </div>
                                <div className="flex justify-end">
                                    <button type="submit" className="inline-flex items-center gap-2 rounded-[14px] bg-[#7c8cff] px-4 py-2.5 text-sm font-medium text-white">
                                        <Save className="size-4" />
                                        Add contact
                                    </button>
                                </div>
                            </form>
                        </PageCard>

                        <div className="space-y-4">
                            {contacts.length === 0 ? (
                                <PageCard className="p-6 text-[15px] text-[#9ca7b9]">
                                    No contacts yet. Add at least one email address to receive alerts.
                                </PageCard>
                            ) : (
                                contacts.map((contact) => <ContactEditor key={contact.id} contact={contact} />)
                            )}
                        </div>
                    </section>

                    <aside className="space-y-4">
                        <PageCard className="space-y-4 p-6">
                            <div className="flex items-center gap-3 text-[22px] font-semibold text-white">
                                <ServerCog className="size-5 text-[#7c8cff]" />
                                Runtime readiness
                            </div>
                            <div className="space-y-3">
                                <RuntimeRow label="App URL" value={runtime.appUrl} />
                                <RuntimeRow label="API base URL" value={runtime.apiBaseUrl} />
                                <RuntimeRow label="Mail driver" value={runtime.mailer} />
                                <RuntimeRow label="Queue connection" value={runtime.queueConnection} />
                                <RuntimeRow label="Monitor queue" value={runtime.monitorQueue} />
                                <RuntimeRow label="Notification queue" value={runtime.notificationQueue} />
                                <RuntimeRow label="Dispatch batch size" value={runtime.dispatchBatchSize} />
                                <RuntimeRow label="Max dispatch batches" value={runtime.dispatchMaxBatches} />
                                <RuntimeRow label="Claim TTL" value={`${runtime.claimTtlSeconds}s`} />
                                <RuntimeRow label="Due monitors" value={runtime.dueMonitors} />
                                <RuntimeRow label="Claimed monitors" value={runtime.claimedMonitors} />
                                <RuntimeRow label="Stale claims" value={runtime.staleClaims} />
                            </div>
                            <div className="rounded-[16px] border border-white/8 bg-[#0b1425] p-4">
                                <div className="flex items-center gap-2 text-[14px] font-medium text-white">
                                    <Activity className="size-4 text-[#7c8cff]" />
                                    API example
                                </div>
                                <div className="mt-3 overflow-x-auto rounded-[14px] bg-[#081428] px-4 py-3 font-mono text-[12px] text-[#dce6fb]">
                                    {sampleCurl}
                                </div>
                                <button
                                    type="button"
                                    className="mt-3 inline-flex items-center gap-2 rounded-[12px] border border-white/10 bg-white/5 px-3 py-2 text-xs text-white"
                                    onClick={() => copyText(sampleCurl)}
                                >
                                    <Copy className="size-3.5" />
                                    Copy curl
                                </button>
                            </div>
                        </PageCard>

                        <PageCard className="space-y-4 p-6">
                            <div className="flex items-center gap-3 text-[22px] font-semibold text-white">
                                <Mail className="size-5 text-[#7c8cff]" />
                                Email delivery log
                            </div>
                            {recentLogs.data.length === 0 ? (
                                <div className="text-[15px] text-[#9ca7b9]">No notifications have been recorded yet.</div>
                            ) : (
                                recentLogs.data.map((log) => {
                                    const toneClass = log.status === 'Sent'
                                        ? 'text-[#7c8cff]'
                                        : log.status === 'Failed'
                                          ? 'text-[#ff7f87]'
                                          : 'text-[#7c8cff]';

                                    return (
                                        <div key={`${log.subject}-${log.sentAt}`} className="rounded-[16px] bg-[#171d28] px-4 py-4">
                                            <div className="flex items-center justify-between gap-3 text-[14px] text-white">
                                                <span>{log.type}</span>
                                                <span className={toneClass}>{log.status}</span>
                                            </div>
                                            <div className="mt-2 text-[14px] text-[#dce6fb]">{log.subject}</div>
                                            <div className="mt-1 text-[13px] text-[#7f8eab]">{log.monitor} • {log.contact}</div>
                                            <div className="mt-1 text-[13px] text-[#7f8eab]">{log.sentAt}</div>
                                            {log.failureMessage ? (
                                                <div className="mt-3 flex items-start gap-2 rounded-[12px] border border-[#ff6269]/20 bg-[#2a1621] px-3 py-2 text-[12px] text-[#ffd4d7]">
                                                    <TriangleAlert className="mt-0.5 size-3.5 shrink-0" />
                                                    <span>{log.failureMessage}</span>
                                                </div>
                                            ) : null}
                                        </div>
                                    );
                                })
                            )}
                            <PaginationStrip
                                currentPage={recentLogs.currentPage}
                                lastPage={recentLogs.lastPage}
                                from={recentLogs.from}
                                to={recentLogs.to}
                                total={recentLogs.total}
                                previousPageUrl={recentLogs.previousPageUrl}
                                nextPageUrl={recentLogs.nextPageUrl}
                            />
                        </PageCard>
                    </aside>
                </div>
            </div>
        </MonitoringLayout>
    );
}

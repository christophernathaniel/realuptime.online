import { Head, Link, useForm } from '@inertiajs/react';
import { ChevronLeft, Save } from 'lucide-react';
import type { ComponentProps } from 'react';
import { useEffect, useMemo } from 'react';
import { PageCard } from '@/components/monitoring/page-card';
import MonitoringLayout from '@/layouts/monitoring-layout';
import { cn } from '@/lib/utils';
import type {
    MonitorFormData,
    MonitorFormMembership,
    MonitorFormOptions,
    NotificationContactOption,
} from '@/types/monitoring';

type MonitorEditProps = {
    mode: 'create' | 'edit';
    monitor: MonitorFormData;
    contacts: NotificationContactOption[];
    options: MonitorFormOptions;
    membership: MonitorFormMembership;
};

function FieldLabel({ children }: { children: string }) {
    return <label className="text-[15px] font-medium uppercase tracking-[0.14em] text-[#d7dfea]">{children}</label>;
}

function formatInterval(seconds: number) {
    if (seconds < 60) {
        return `${seconds} seconds`;
    }

    if (seconds < 3600) {
        return `${Math.round(seconds / 60)} minutes`;
    }

    return `${Math.round(seconds / 3600)} hours`;
}

function Input({ className, ...props }: ComponentProps<'input'>) {
    return (
        <input
            className={cn(
                'h-12 w-full rounded-[16px] border border-[#2b3544] bg-[#121821] px-4 text-base text-white outline-none placeholder:text-[#70809a] focus:border-[#57c7c2]',
                className,
            )}
            {...props}
        />
    );
}

function Textarea({ className, ...props }: ComponentProps<'textarea'>) {
    return (
        <textarea
            className={cn(
                'min-h-[110px] w-full rounded-[16px] border border-[#2b3544] bg-[#121821] px-4 py-3 text-base text-white outline-none placeholder:text-[#70809a] focus:border-[#57c7c2]',
                className,
            )}
            {...props}
        />
    );
}

function Select({ className, ...props }: ComponentProps<'select'>) {
    return (
        <select
            className={cn(
                'h-12 w-full rounded-[16px] border border-[#2b3544] bg-[#121821] px-4 text-base text-white outline-none focus:border-[#57c7c2]',
                className,
            )}
            {...props}
        />
    );
}

export default function MonitorEdit({ mode, monitor, contacts, options, membership }: MonitorEditProps) {
    const form = useForm<MonitorFormData>(monitor);
    const minimumAllowedInterval = options.intervals[0] ?? form.data.interval_seconds;
    const intervalIndex = Math.max(0, options.intervals.findIndex((value) => value === form.data.interval_seconds));
    const title = mode === 'create' ? 'Create check' : `Edit ${monitor.name}`;
    const customizationLocked = !membership.advancedFeaturesUnlocked;
    const isHttp = form.data.type === 'http';
    const isPing = form.data.type === 'ping';
    const isPort = form.data.type === 'port';
    const supportsLatencyAlerts = isHttp || isPing || isPort;
    const subscriptionRequiredToCreate = mode === 'create' && !membership.canCreate;
    const downtimeWebhookLocked = !membership.supportsDowntimeWebhooks;
    const sectionLinks = customizationLocked
        ? ['Check setup']
        : ['Check setup', 'Notifications & access', 'Timing & alert policy'];

    const monitorTypeDescription = useMemo(() => {
        return options.types.find((type) => type.value === form.data.type)?.label ?? 'Monitor';
    }, [form.data.type, options.types]);

    useEffect(() => {
        if (options.intervals.length > 0 && !options.intervals.includes(form.data.interval_seconds)) {
            form.setData('interval_seconds', minimumAllowedInterval);
        }
    }, [form, form.data.interval_seconds, minimumAllowedInterval, options.intervals]);

    return (
        <MonitoringLayout>
            <Head title={title} />
            <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_250px]">
                <section className="space-y-5">
                    <Link
                        href={mode === 'create' ? '/monitors' : `/monitors/${monitor.id}`}
                        className="inline-flex items-center gap-3 rounded-[16px] border border-[#2b3544] bg-[#171d28] px-4 py-2.5 text-base text-[#d5def3]"
                    >
                        <ChevronLeft className="size-4" />
                        {mode === 'create' ? 'Checks' : 'Check detail'}
                    </Link>

                    <div>
                        <div className="text-[11px] font-semibold uppercase tracking-[0.26em] text-[#7f8b9b]">
                            Configuration studio
                        </div>
                        <h1 className="text-[40px] font-semibold tracking-[-0.06em] text-white lg:text-[44px]">
                            {title}
                            <span className="text-[#7c8cff]">.</span>
                        </h1>
                        <div className="mt-2 text-[16px] text-[#9ca7b9] lg:text-[18px]">{monitorTypeDescription}</div>
                    </div>

                    <PageCard className="grid gap-4 rounded-[20px] p-5 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                        <div>
                            <div className="text-[12px] font-semibold uppercase tracking-[0.22em] text-[#7f8b9b]">Workspace plan</div>
                            <div className="mt-2 text-[24px] font-semibold tracking-[-0.04em] text-white">
                                {membership.planLabel}
                                <span className="text-[#7c8cff]">.</span>
                            </div>
                            <div className="mt-2 text-[15px] text-[#9ca7b9]">
                                {membership.currentMonitorCount} / {membership.monitorLimitLabel} monitors in use. Fastest interval {membership.minimumIntervalLabel}.
                            </div>
                            <div className="mt-2 text-[14px] text-[#9ca7b9]">
                                {customizationLocked
                                    ? 'Free workspaces use the standard check profile: North America, 5-minute cadence, 30-second timeout, and 2 retries.'
                                    : 'Custom check configuration and advanced workspace tooling are unlocked on this workspace.'}
                            </div>
                            {subscriptionRequiredToCreate ? (
                                <div className="mt-3 rounded-[16px] border border-[#7c8cff]/18 bg-[#171c33] px-4 py-3 text-sm text-[#d3daff]">
                                    This workspace has reached its check allowance. Upgrade the membership plan to add another check.
                                </div>
                            ) : null}
                        </div>
                        <div className="flex flex-col gap-2 text-left lg:text-right">
                            <div className="text-sm text-[#9ca7b9]">{membership.priceLabel}</div>
                            {membership.manageUrl ? (
                                <Link
                                    href={membership.manageUrl}
                                    className="inline-flex h-11 items-center justify-center rounded-[14px] bg-[#7c8cff] px-4 text-sm font-medium text-white"
                                >
                                    {membership.planValue === 'free' ? 'Upgrade plan' : 'Manage plan'}
                                </Link>
                            ) : (
                                <div className="text-sm text-[#9ca7b9]">Membership changes are managed by the workspace owner.</div>
                            )}
                        </div>
                    </PageCard>

                    <form
                        className="space-y-5"
                        onSubmit={(event) => {
                            event.preventDefault();
                            if (subscriptionRequiredToCreate) {
                                return;
                            }

                            if (mode === 'create') {
                                form.post('/monitors');
                                return;
                            }

                            form.put(`/monitors/${monitor.id}`);
                        }}
                    >
                        <PageCard className="space-y-6 p-6">
                            <div className="grid gap-5 lg:grid-cols-[1.1fr_0.9fr]">
                                <div className="space-y-3">
                                    <FieldLabel>Check name</FieldLabel>
                                    <Input
                                        value={form.data.name}
                                        onChange={(event) => form.setData('name', event.target.value)}
                                        placeholder="Friendly check name"
                                    />
                                    {form.errors.name ? <div className="text-sm text-[#ff7f86]">{form.errors.name}</div> : null}
                                </div>
                                <div className="space-y-3">
                                    <FieldLabel>Check type</FieldLabel>
                                    <Select value={form.data.type} onChange={(event) => form.setData('type', event.target.value)}>
                                        {options.types.map((type) => (
                                            <option key={type.value} value={type.value}>
                                                {type.label}
                                            </option>
                                        ))}
                                    </Select>
                                </div>
                            </div>

                            <div className="space-y-3">
                                <FieldLabel>{isPing ? 'Hostname or IP to monitor' : isPort ? 'Host and port' : 'URL or domain to monitor'}</FieldLabel>
                                <Input
                                    value={form.data.target}
                                    onChange={(event) => form.setData('target', event.target.value)}
                                    placeholder={isPort ? 'db.example.com:5432' : 'https://example.com'}
                                />
                                {form.errors.target ? <div className="text-sm text-[#ff7f86]">{form.errors.target}</div> : null}
                            </div>

                            {customizationLocked ? (
                                <div className="rounded-[18px] border border-[#2b3544] bg-[#171d28] px-4 py-4 text-sm text-[#9ca7b9]">
                                    Free workspaces use fixed defaults automatically. Upgrade to Premium or Ultra to choose regions, request rules, alert thresholds, recipients, and webhooks.
                                </div>
                            ) : (
                                <>
                                    <div className="grid gap-5 lg:grid-cols-2">
                                        <div className="space-y-3">
                                            <FieldLabel>Region</FieldLabel>
                                            <Select value={form.data.region} onChange={(event) => form.setData('region', event.target.value)}>
                                                {options.regions.map((region) => (
                                                    <option key={region} value={region}>
                                                        {region}
                                                    </option>
                                                ))}
                                            </Select>
                                        </div>
                                        <div className="space-y-3">
                                            <FieldLabel>Retries before incident</FieldLabel>
                                            <Input
                                                type="number"
                                                min={0}
                                                max={5}
                                                value={form.data.retry_limit}
                                                onChange={(event) => form.setData('retry_limit', Number(event.target.value))}
                                            />
                                        </div>
                                    </div>

                                    {isHttp ? (
                                        <div className="grid gap-5 lg:grid-cols-2">
                                            <div className="space-y-3">
                                                <FieldLabel>Request method</FieldLabel>
                                                <Select value={form.data.request_method} onChange={(event) => form.setData('request_method', event.target.value)}>
                                                    {options.methods.map((method) => (
                                                        <option key={method} value={method}>
                                                            {method}
                                                        </option>
                                                    ))}
                                                </Select>
                                            </div>
                                            <div className="space-y-3">
                                                <FieldLabel>Expected status code</FieldLabel>
                                                <Input
                                                    type="number"
                                                    min={100}
                                                    max={599}
                                                    value={form.data.expected_status_code}
                                                    onChange={(event) => form.setData('expected_status_code', Number(event.target.value))}
                                                />
                                            </div>
                                        </div>
                                    ) : null}

                                    {supportsLatencyAlerts ? (
                                        <div className="grid gap-5 lg:grid-cols-2">
                                            <div className="space-y-3">
                                                <FieldLabel>Performance warning threshold (ms)</FieldLabel>
                                                <Input
                                                    type="number"
                                                    min={1}
                                                    value={form.data.latency_threshold_ms}
                                                    onChange={(event) => form.setData('latency_threshold_ms', Number(event.target.value))}
                                                />
                                            </div>
                                            <div className="space-y-3">
                                                <FieldLabel>Slow checks before incident</FieldLabel>
                                                <Input
                                                    type="number"
                                                    min={1}
                                                    max={10}
                                                    value={form.data.degraded_consecutive_checks}
                                                    onChange={(event) => form.setData('degraded_consecutive_checks', Number(event.target.value))}
                                                />
                                            </div>
                                        </div>
                                    ) : null}

                                    {isPing ? (
                                        <div className="space-y-3">
                                            <FieldLabel>Packet count</FieldLabel>
                                            <Input
                                                type="number"
                                                min={1}
                                                max={10}
                                                value={form.data.packet_count}
                                                onChange={(event) => form.setData('packet_count', Number(event.target.value))}
                                            />
                                        </div>
                                    ) : null}

                                    <div className="space-y-3">
                                        <FieldLabel>Critical downtime alert after (minutes)</FieldLabel>
                                        <Input
                                            type="number"
                                            min={1}
                                            value={form.data.critical_alert_after_minutes}
                                            onChange={(event) => form.setData('critical_alert_after_minutes', Number(event.target.value))}
                                        />
                                    </div>

                                    {isHttp ? (
                                        <>
                                            <div className="grid gap-5 lg:grid-cols-2">
                                                <div className="space-y-3">
                                                    <FieldLabel>Authentication username</FieldLabel>
                                                    <Input
                                                        value={form.data.auth_username}
                                                        onChange={(event) => form.setData('auth_username', event.target.value)}
                                                        placeholder="Optional"
                                                    />
                                                </div>
                                                <div className="space-y-3">
                                                    <FieldLabel>Authentication password</FieldLabel>
                                                    <Input
                                                        type="password"
                                                        value={form.data.auth_password}
                                                        onChange={(event) => form.setData('auth_password', event.target.value)}
                                                        placeholder="Optional"
                                                    />
                                                </div>
                                            </div>

                                            <label className="inline-flex items-center gap-3 text-[18px] text-[#d5def3]">
                                                <input
                                                    type="checkbox"
                                                    checked={form.data.follow_redirects}
                                                    onChange={(event) => form.setData('follow_redirects', event.target.checked)}
                                                    className="size-5 rounded border-[#2b3544] bg-[#121821] text-[#7c8cff]"
                                                />
                                                Follow redirects
                                            </label>

                                            <div className="space-y-3">
                                                <FieldLabel>Custom headers (JSON)</FieldLabel>
                                                <Textarea
                                                    value={form.data.custom_headers}
                                                    onChange={(event) => form.setData('custom_headers', event.target.value)}
                                                    placeholder={'{\n  "X-Env": "production"\n}'}
                                                />
                                                {form.errors.custom_headers ? <div className="text-sm text-[#ff7f86]">{form.errors.custom_headers}</div> : null}
                                            </div>
                                        </>
                                    ) : null}
                                </>
                            )}
                        </PageCard>

                        {!customizationLocked ? (
                            <PageCard className="space-y-6 p-6">
                                <div>
                                    <div className="text-[26px] font-semibold tracking-[-0.05em] text-white lg:text-[28px]">How will we notify you?</div>
                                    <div className="mt-2 text-[15px] text-[#9ca7b9] lg:text-[16px]">
                                        Email is the only alert channel in this build. Select the contacts that should receive incident and recovery emails.
                                    </div>
                                </div>

                                <div className="grid gap-4 lg:grid-cols-2">
                                    {contacts.map((contact) => {
                                        const checked = form.data.contact_ids.includes(contact.id);

                                        return (
                                            <label
                                                key={contact.id}
                                                className="flex items-start gap-4 rounded-[20px] border border-[#2b3544] bg-[#171d28] px-4 py-4 text-left"
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={checked}
                                                    onChange={(event) => {
                                                        form.setData(
                                                            'contact_ids',
                                                            event.target.checked
                                                                ? [...form.data.contact_ids, contact.id]
                                                                : form.data.contact_ids.filter((id) => id !== contact.id),
                                                        );
                                                    }}
                                                    className="mt-1 size-5 rounded border-[#2b3544] bg-[#121821] text-[#7c8cff]"
                                                />
                                                <div>
                                                    <div className="text-[17px] font-medium text-white">{contact.name}</div>
                                                    <div className="text-[15px] text-[#9ca7b9]">{contact.email}</div>
                                                </div>
                                            </label>
                                        );
                                    })}
                                </div>
                            </PageCard>
                        ) : null}

                        {!customizationLocked ? (
                            <PageCard className="space-y-6 p-6">
                                <div>
                                    <div className="text-[26px] font-semibold tracking-[-0.05em] text-white lg:text-[28px]">Downtime webhooks</div>
                                    <div className="mt-2 text-[15px] text-[#9ca7b9] lg:text-[16px]">
                                        Add one URL per line to receive a JSON POST when this check goes down.
                                    </div>
                                </div>

                                <div className="space-y-3">
                                    <FieldLabel>Webhook URLs</FieldLabel>
                                    <Textarea
                                        value={form.data.downtime_webhook_urls}
                                        onChange={(event) => form.setData('downtime_webhook_urls', event.target.value)}
                                        placeholder={'https://hooks.example.com/realuptime\nhttps://ops.example.com/incidents'}
                                        disabled={downtimeWebhookLocked}
                                        className={cn(downtimeWebhookLocked && 'cursor-not-allowed opacity-60')}
                                    />
                                    <div className="text-sm text-[#9ca7b9]">
                                        RealUptime sends a `monitor.down` payload with the check, incident, and workspace context. Up to 5 endpoints per check.
                                    </div>
                                    {form.errors.downtime_webhook_urls ? (
                                        <div className="text-sm text-[#ff7f86]">{form.errors.downtime_webhook_urls}</div>
                                    ) : null}
                                </div>

                                {downtimeWebhookLocked ? (
                                    <div className="rounded-[18px] border border-[#7c8cff]/18 bg-[#171c33] px-4 py-3.5">
                                        <div className="text-[16px] font-medium text-[#ffe1a5]">Paid feature</div>
                                        <div className="mt-2 text-sm text-[#d7bb7c]">Downtime webhook delivery requires a paid workspace.</div>
                                        {membership.manageUrl ? (
                                            <Link
                                                href={membership.manageUrl}
                                                className="mt-4 inline-flex h-11 items-center justify-center rounded-[14px] bg-[#7c8cff] px-4 text-sm font-medium text-white"
                                            >
                                                Upgrade workspace
                                            </Link>
                                        ) : null}
                                    </div>
                                ) : null}
                            </PageCard>
                        ) : null}

                        {!customizationLocked ? (
                            <PageCard className="space-y-6 p-6">
                                <div>
                                    <div className="text-[26px] font-semibold tracking-[-0.05em] text-white lg:text-[28px]">Check cadence</div>
                                    <div className="mt-2 text-[15px] text-[#9ca7b9] lg:text-[16px]">
                                        This check will run every {formatInterval(form.data.interval_seconds)}.
                                    </div>
                                </div>

                                <div className="space-y-5">
                                    <input
                                        type="range"
                                        min={0}
                                        max={options.intervals.length - 1}
                                        step={1}
                                        value={intervalIndex}
                                        onChange={(event) => form.setData('interval_seconds', options.intervals[Number(event.target.value)] ?? 300)}
                                        className="h-3 w-full accent-[#7c8cff]"
                                    />
                                    <div className="flex justify-between text-[14px] text-[#8190ad]">
                                        {options.intervals.map((interval) => (
                                            <span key={interval}>
                                                {interval < 60
                                                    ? `${interval}s`
                                                    : interval < 3600
                                                      ? `${Math.round(interval / 60)}m`
                                                      : `${Math.round(interval / 3600)}h`}
                                            </span>
                                        ))}
                                    </div>
                                </div>

                                <div className="grid gap-5 lg:grid-cols-2">
                                    <div className="space-y-3">
                                        <FieldLabel>Timeout (seconds)</FieldLabel>
                                        <Input
                                            type="number"
                                            min={1}
                                            max={60}
                                            value={form.data.timeout_seconds}
                                            onChange={(event) => form.setData('timeout_seconds', Number(event.target.value))}
                                        />
                                    </div>
                                    <div className="space-y-3">
                                        <FieldLabel>Retries</FieldLabel>
                                        <Input
                                            type="number"
                                            min={0}
                                            max={5}
                                            value={form.data.retry_limit}
                                            onChange={(event) => form.setData('retry_limit', Number(event.target.value))}
                                        />
                                    </div>
                                </div>
                            </PageCard>
                        ) : null}

                        <div className="sticky bottom-3 z-20 flex justify-start">
                            <button
                                type="submit"
                                disabled={form.processing || subscriptionRequiredToCreate}
                                className="inline-flex items-center gap-3 rounded-[18px] bg-[#7c8cff] px-7 py-3.5 text-[19px] font-semibold tracking-[-0.04em] text-white transition hover:bg-[#95a3ff] disabled:cursor-not-allowed disabled:opacity-70 lg:text-[21px]"
                            >
                                <Save className="size-5" />
                                {subscriptionRequiredToCreate ? 'Upgrade required' : 'Save changes'}
                            </button>
                        </div>
                    </form>
                </section>

                <aside className="pt-24">
                    <div className="space-y-6 text-[17px]">
                        {sectionLinks.map((section, index) => (
                            <div key={section} className={cn(index === 0 ? 'font-semibold text-[#7c8cff]' : 'text-[#9ca7b9]')}>
                                {section}
                            </div>
                        ))}
                    </div>
                </aside>
            </div>
        </MonitoringLayout>
    );
}

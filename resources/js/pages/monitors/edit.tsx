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

const sectionLinks = ['Monitor details', 'Integrations & Team', 'Maintenance info'];

function FieldLabel({ children }: { children: string }) {
    return <label className="text-[16px] font-medium text-white">{children}</label>;
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
                'h-12 w-full rounded-[16px] border border-white/10 bg-[#091426] px-4 text-base text-white outline-none placeholder:text-[#63718e] focus:border-[#35507d]',
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
                'min-h-[110px] w-full rounded-[16px] border border-white/10 bg-[#091426] px-4 py-3 text-base text-white outline-none placeholder:text-[#63718e] focus:border-[#35507d]',
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
                'h-12 w-full rounded-[16px] border border-white/10 bg-[#091426] px-4 text-base text-white outline-none focus:border-[#35507d]',
                className,
            )}
            {...props}
        />
    );
}

export default function MonitorEdit({ mode, monitor, contacts, options, membership }: MonitorEditProps) {
    const form = useForm<MonitorFormData>(monitor);
    const minimumAllowedInterval = options.intervals[0] ?? form.data.interval_seconds;
    const intervalIndex = Math.max(
        0,
        options.intervals.findIndex((value) => value === form.data.interval_seconds),
    );

    const title = mode === 'create' ? 'Create monitor' : `Edit ${monitor.name}`;
    const isHttpLike = form.data.type === 'http' || form.data.type === 'keyword';
    const isSynthetic = form.data.type === 'synthetic';
    const usesHttpTransport = isHttpLike || isSynthetic;
    const supportsLatencyAlerts = form.data.type === 'http' || form.data.type === 'keyword' || form.data.type === 'ping' || form.data.type === 'synthetic';
    const supportsExpiryAlerts = form.data.type === 'http' || form.data.type === 'keyword' || form.data.type === 'ssl' || form.data.type === 'synthetic';

    const monitorTypeDescription = useMemo(() => {
        return options.types.find((type) => type.value === form.data.type)?.label ?? 'Monitor';
    }, [form.data.type, options.types]);
    const subscriptionRequiredToCreate = mode === 'create' && !membership.canCreate;
    const downtimeWebhookLocked = !membership.supportsDowntimeWebhooks;

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
                        className="inline-flex items-center gap-3 rounded-[16px] border border-white/6 bg-[#1a2339]/95 px-4 py-2.5 text-base text-[#d5def3]"
                    >
                        <ChevronLeft className="size-4" />
                        {mode === 'create' ? 'Monitoring' : 'Monitor detail'}
                    </Link>

                    <div>
                        <h1 className="text-[40px] font-semibold tracking-[-0.06em] text-white lg:text-[44px]">
                            {title}
                            <span className="text-[#3ee072]">.</span>
                        </h1>
                        <div className="mt-2 text-[16px] text-[#8fa0bf] lg:text-[18px]">{monitorTypeDescription}</div>
                    </div>

                    <PageCard className="grid gap-4 rounded-[22px] p-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                        <div>
                            <div className="text-[12px] font-semibold uppercase tracking-[0.22em] text-[#6f82a3]">Workspace plan</div>
                            <div className="mt-2 text-[24px] font-semibold tracking-[-0.04em] text-white">
                                {membership.planLabel}
                                <span className="text-[#3ee072]">.</span>
                            </div>
                            <div className="mt-2 text-[15px] text-[#8fa0bf]">
                                {membership.currentMonitorCount} / {membership.monitorLimitLabel} monitors in use. Fastest interval {membership.minimumIntervalLabel}.
                            </div>
                            <div className="mt-2 text-[14px] text-[#8fa0bf]">
                                {membership.advancedFeaturesUnlocked
                                    ? 'Advanced sections are unlocked for this workspace.'
                                    : 'Free workspaces stay on monitoring-only pages until upgraded.'}
                            </div>
                            {subscriptionRequiredToCreate ? (
                                <div className="mt-3 rounded-[16px] border border-[#ffb454]/18 bg-[#2b2110] px-4 py-3 text-sm text-[#ffd88c]">
                                    This workspace has reached its monitor allowance. Upgrade the membership plan to add another monitor.
                                </div>
                            ) : null}
                        </div>
                        <div className="flex flex-col gap-2 text-left lg:text-right">
                            <div className="text-sm text-[#8fa0bf]">{membership.priceLabel}</div>
                            {membership.manageUrl ? (
                                <Link
                                    href={membership.manageUrl}
                                    className="inline-flex h-11 items-center justify-center rounded-[14px] bg-[#352ef6] px-4 text-sm font-medium text-white"
                                >
                                    {membership.planValue === 'free' ? 'Upgrade plan' : 'Manage plan'}
                                </Link>
                            ) : (
                                <div className="text-sm text-[#8fa0bf]">
                                    Membership changes are managed by the workspace owner.
                                </div>
                            )}
                        </div>
                    </PageCard>

                    <form
                        className="space-y-6"
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
                        <PageCard className="space-y-7 p-7">
                            <div className="grid gap-5 lg:grid-cols-[1.1fr_0.9fr]">
                                <div className="space-y-3">
                                    <FieldLabel>Monitor name</FieldLabel>
                                    <Input
                                        value={form.data.name}
                                        onChange={(event) => form.setData('name', event.target.value)}
                                        placeholder="Friendly monitor name"
                                    />
                                    {form.errors.name ? <div className="text-sm text-[#ff7f86]">{form.errors.name}</div> : null}
                                </div>
                                <div className="space-y-3">
                                    <FieldLabel>Monitor type</FieldLabel>
                                    <Select
                                        value={form.data.type}
                                        onChange={(event) => form.setData('type', event.target.value)}
                                    >
                                        {options.types.map((type) => (
                                            <option key={type.value} value={type.value}>
                                                {type.label}
                                            </option>
                                        ))}
                                    </Select>
                                </div>
                            </div>

                            <div className="space-y-3">
                                <FieldLabel>
                                    {form.data.type === 'heartbeat'
                                        ? 'Heartbeat name or endpoint label'
                                        : form.data.type === 'ping'
                                          ? 'Hostname or IP to monitor'
                                          : form.data.type === 'synthetic'
                                            ? 'Base URL for the synthetic flow'
                                          : 'URL or domain to monitor'}
                                </FieldLabel>
                                <Input
                                    value={form.data.target}
                                    onChange={(event) => form.setData('target', event.target.value)}
                                    placeholder={form.data.type === 'heartbeat' ? 'Daily backup job' : 'https://example.com'}
                                />
                                {form.errors.target ? <div className="text-sm text-[#ff7f86]">{form.errors.target}</div> : null}
                            </div>

                            <div className="grid gap-5 lg:grid-cols-2">
                                <div className="space-y-3">
                                    <FieldLabel>Region</FieldLabel>
                                    <Select
                                        value={form.data.region}
                                        onChange={(event) => form.setData('region', event.target.value)}
                                    >
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

                            {isHttpLike ? (
                                <div className="grid gap-5 lg:grid-cols-2">
                                    <div className="space-y-3">
                                        <FieldLabel>Request method</FieldLabel>
                                        <Select
                                            value={form.data.request_method}
                                            onChange={(event) => form.setData('request_method', event.target.value)}
                                        >
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

                            {form.data.type === 'keyword' ? (
                                <div className="grid gap-5 lg:grid-cols-[1fr_220px]">
                                    <div className="space-y-3">
                                        <FieldLabel>Expected keyword</FieldLabel>
                                        <Input
                                            value={form.data.expected_keyword}
                                            onChange={(event) => form.setData('expected_keyword', event.target.value)}
                                        />
                                    </div>
                                    <div className="space-y-3">
                                        <FieldLabel>Match type</FieldLabel>
                                        <Select
                                            value={form.data.keyword_match_type}
                                            onChange={(event) => form.setData('keyword_match_type', event.target.value)}
                                        >
                                            {options.keywordMatchTypes.map((matchType) => (
                                                <option key={matchType} value={matchType}>
                                                    {matchType}
                                                </option>
                                            ))}
                                        </Select>
                                    </div>
                                </div>
                            ) : null}

                            {isSynthetic ? (
                                <div className="space-y-3">
                                    <FieldLabel>Synthetic steps (JSON)</FieldLabel>
                                    <Textarea
                                        value={form.data.synthetic_steps}
                                        onChange={(event) => form.setData('synthetic_steps', event.target.value)}
                                        placeholder={`[
  {
    "name": "Load login page",
    "method": "GET",
    "url": "/login",
    "expected_status_code": 200,
    "expected_keyword": "Sign in"
  },
  {
    "name": "Submit credentials",
    "method": "POST",
    "url": "/session",
    "body": {
      "email": "<account-email>",
      "password": "<account-password>"
    },
    "expected_status_code": 200,
    "expected_keyword": "Dashboard"
  }
]`}
                                    />
                                    <div className="text-sm text-[#8fa0bf]">
                                        Steps run in order with shared cookies. Each step can define `name`, `method`, `url`, `headers`, `body`, `expected_status_code`, and `expected_keyword`.
                                    </div>
                                    {form.errors.synthetic_steps ? (
                                        <div className="text-sm text-[#ff7f86]">{form.errors.synthetic_steps}</div>
                                    ) : null}
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

                            {form.data.type === 'ping' ? (
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

                            {supportsExpiryAlerts ? (
                                <div className="grid gap-5 lg:grid-cols-2">
                                    <div className="space-y-3">
                                        <FieldLabel>SSL expiry alert threshold (days)</FieldLabel>
                                        <Input
                                            type="number"
                                            min={1}
                                            value={form.data.ssl_threshold_days}
                                            onChange={(event) => form.setData('ssl_threshold_days', Number(event.target.value))}
                                        />
                                    </div>
                                    <div className="space-y-3">
                                        <FieldLabel>Domain expiry alert threshold (days)</FieldLabel>
                                        <Input
                                            type="number"
                                            min={1}
                                            value={form.data.domain_threshold_days}
                                            onChange={(event) => form.setData('domain_threshold_days', Number(event.target.value))}
                                        />
                                    </div>
                                </div>
                            ) : null}

                            {form.data.type === 'heartbeat' ? (
                                <div className="space-y-3">
                                    <FieldLabel>Grace period (seconds)</FieldLabel>
                                    <Input
                                        type="number"
                                        min={0}
                                        value={form.data.heartbeat_grace_seconds}
                                        onChange={(event) => form.setData('heartbeat_grace_seconds', Number(event.target.value))}
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

                            {usesHttpTransport ? (
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
                            ) : null}

                            {usesHttpTransport ? (
                                <div className="space-y-3">
                                    <label className="inline-flex items-center gap-3 text-[18px] text-[#d5def3]">
                                        <input
                                            type="checkbox"
                                            checked={form.data.follow_redirects}
                                            onChange={(event) => form.setData('follow_redirects', event.target.checked)}
                                            className="size-5 rounded border-white/15 bg-[#091426] text-[#352ef6]"
                                        />
                                        Follow redirects
                                    </label>
                                </div>
                            ) : null}

                            {usesHttpTransport ? (
                                <div className="space-y-3">
                                    <FieldLabel>Custom headers (JSON)</FieldLabel>
                                    <Textarea
                                        value={form.data.custom_headers}
                                        onChange={(event) => form.setData('custom_headers', event.target.value)}
                                        placeholder={'{\n  "X-Env": "production"\n}'}
                                    />
                                    {form.errors.custom_headers ? (
                                        <div className="text-sm text-[#ff7f86]">{form.errors.custom_headers}</div>
                                    ) : null}
                                </div>
                            ) : null}
                        </PageCard>

                        <PageCard className="space-y-7 p-7">
                            <div>
                                <div className="text-[26px] font-semibold tracking-[-0.05em] text-white lg:text-[28px]">
                                    How will we notify you?
                                </div>
                                <div className="mt-2 text-[15px] text-[#8fa0bf] lg:text-[16px]">
                                    Email is the only alert channel in this build. Select the contacts that should receive incident and recovery emails.
                                </div>
                            </div>

                            <div className="grid gap-4 lg:grid-cols-2">
                                {contacts.map((contact) => {
                                    const checked = form.data.contact_ids.includes(contact.id);

                                    return (
                                        <label
                                            key={contact.id}
                                            className="flex items-start gap-4 rounded-[20px] border border-white/8 bg-[#111a2c] px-5 py-5 text-left"
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
                                                className="mt-1 size-5 rounded border-white/15 bg-[#091426] text-[#352ef6]"
                                            />
                                            <div>
                                                <div className="text-[17px] font-medium text-white">{contact.name}</div>
                                                <div className="text-[15px] text-[#8fa0bf]">{contact.email}</div>
                                            </div>
                                        </label>
                                    );
                                })}
                            </div>
                        </PageCard>

                        <PageCard className="space-y-7 p-7">
                            <div>
                                <div className="text-[26px] font-semibold tracking-[-0.05em] text-white lg:text-[28px]">
                                    Downtime webhooks
                                </div>
                                <div className="mt-2 text-[15px] text-[#8fa0bf] lg:text-[16px]">
                                    Add one URL per line to receive a JSON POST when this monitor goes down.
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
                                <div className="text-sm text-[#8fa0bf]">
                                    RealUptime sends a `monitor.down` payload with the monitor, incident, and workspace context. Up to 5 endpoints per monitor.
                                </div>
                                {form.errors.downtime_webhook_urls ? (
                                    <div className="text-sm text-[#ff7f86]">{form.errors.downtime_webhook_urls}</div>
                                ) : null}
                            </div>

                            {downtimeWebhookLocked ? (
                                <div className="rounded-[18px] border border-[#ffb454]/18 bg-[#2b2110] px-5 py-4">
                                    <div className="text-[16px] font-medium text-[#ffe1a5]">Premium / Ultra feature</div>
                                    <div className="mt-2 text-sm text-[#d7bb7c]">
                                        Free workspaces cannot add or edit downtime webhook URLs. Existing webhook URLs stay stored but will not send until the workspace is upgraded again.
                                    </div>
                                    {membership.manageUrl ? (
                                        <Link
                                            href={membership.manageUrl}
                                            className="mt-4 inline-flex h-11 items-center justify-center rounded-[14px] bg-[#352ef6] px-4 text-sm font-medium text-white"
                                        >
                                            Upgrade workspace
                                        </Link>
                                    ) : null}
                                </div>
                            ) : null}
                        </PageCard>

                        <PageCard className="space-y-7 p-7">
                            <div>
                                <div className="text-[26px] font-semibold tracking-[-0.05em] text-white lg:text-[28px]">
                                    Monitor interval
                                </div>
                                <div className="mt-2 text-[15px] text-[#8fa0bf] lg:text-[16px]">
                                    Your monitor will be checked every {formatInterval(form.data.interval_seconds)}.
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
                                    className="h-3 w-full accent-[#352ef6]"
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

                        <div className="sticky bottom-3 z-20 flex justify-start">
                            <button
                                type="submit"
                                disabled={form.processing || subscriptionRequiredToCreate}
                                className="inline-flex items-center gap-3 rounded-[18px] bg-[#352ef6] px-8 py-4 text-[20px] font-semibold tracking-[-0.04em] text-white shadow-[0_18px_42px_rgba(53,46,246,0.32)] transition hover:bg-[#4038ff] disabled:cursor-not-allowed disabled:opacity-70 lg:text-[22px]"
                            >
                                <Save className="size-5" />
                                {subscriptionRequiredToCreate ? 'Upgrade required' : 'Save changes'}
                            </button>
                        </div>
                    </form>
                </section>

                <aside className="pt-24">
                    <div className="space-y-7 text-[18px]">
                        {sectionLinks.map((section, index) => (
                            <div key={section} className={cn(index === 0 ? 'font-semibold text-[#3ee072]' : 'text-[#8fa0bf]')}>
                                {section}
                            </div>
                        ))}
                    </div>
                </aside>
            </div>
        </MonitoringLayout>
    );
}

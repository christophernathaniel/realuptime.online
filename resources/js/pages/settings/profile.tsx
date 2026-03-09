import { Transition } from '@headlessui/react';
import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { CheckCircle2, Link2, Link2Off } from 'lucide-react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import InputError from '@/components/input-error';
import { PageCard } from '@/components/monitoring/page-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import MonitoringLayout from '@/layouts/monitoring-layout';
import SettingsLayout from '@/layouts/settings/layout';
import {
    surfaceInputClass,
    surfaceLabelClass,
    surfaceLinkClass,
    surfaceMutedTextClass,
    surfacePrimaryButtonClass,
    surfaceSecondaryButtonClass,
    surfaceSuccessClass,
} from '@/lib/realuptime-theme';
import { send } from '@/routes/verification';
import type { Auth } from '@/types/auth';

const sectionTitleClass = 'text-[20px] font-semibold tracking-[-0.04em] text-white';
const sectionDescriptionClass = 'mt-2 text-[14px] leading-6 text-[#8fa0bf]';

export default function Profile({
    mustVerifyEmail,
    status,
    oauthProviders,
    passwordLoginEnabled,
}: {
    mustVerifyEmail: boolean;
    status?: string;
    oauthProviders: Array<{
        key: string;
        label: string;
        enabled: boolean;
        connected: boolean;
        connectedAs: string | null;
        avatarUrl: string | null;
        redirectUrl: string;
        disconnectUrl: string;
        canDisconnect: boolean;
    }>;
    passwordLoginEnabled: boolean;
}) {
    const page = usePage<{ auth: Auth }>();
    const user = page.props.auth.user;

    return (
        <MonitoringLayout>
            <Head title="Profile settings" />

            <SettingsLayout
                title="Profile"
                description="Update the profile information, sign-in methods, and verification state attached to your RealUptime account."
            >
                <PageCard className="p-6 sm:p-7">
                    <div className="space-y-6">
                        <div>
                            <h2 className={sectionTitleClass}>Profile information</h2>
                            <p className={sectionDescriptionClass}>
                                These details are used across monitor ownership, alerts, status pages, and account recovery.
                            </p>
                        </div>

                        <Form
                            {...ProfileController.update.form()}
                            options={{ preserveScroll: true }}
                            className="space-y-6"
                        >
                            {({ processing, recentlySuccessful, errors }) => (
                                <>
                                    <div className="grid gap-5 md:grid-cols-2">
                                        <div className="grid gap-2.5">
                                            <Label htmlFor="name" className={surfaceLabelClass}>
                                                Name
                                            </Label>
                                            <Input
                                                id="name"
                                                className={surfaceInputClass}
                                                defaultValue={user.name}
                                                name="name"
                                                required
                                                autoComplete="name"
                                                placeholder="Full name"
                                            />
                                            <InputError message={errors.name} />
                                        </div>

                                        <div className="grid gap-2.5">
                                            <Label htmlFor="email" className={surfaceLabelClass}>
                                                Email address
                                            </Label>
                                            <Input
                                                id="email"
                                                type="email"
                                                className={surfaceInputClass}
                                                defaultValue={user.email}
                                                name="email"
                                                required
                                                autoComplete="username"
                                                placeholder="Email address"
                                            />
                                            <InputError message={errors.email} />
                                        </div>
                                    </div>

                                    {mustVerifyEmail && user.email_verified_at === null ? (
                                        <div className="rounded-[20px] border border-[#ffcd62]/16 bg-[#2b2110] px-4 py-4 text-sm text-[#ffd88c]">
                                            Your email address is not verified.{' '}
                                            <Link
                                                href={send()}
                                                as="button"
                                                className={surfaceLinkClass}
                                            >
                                                Resend verification email
                                            </Link>
                                        </div>
                                    ) : (
                                        <div className="rounded-[20px] border border-[#3ee072]/18 bg-[#10273a] px-4 py-4 text-sm text-[#ddffe7]">
                                            <span className="inline-flex items-center gap-2 font-medium">
                                                <CheckCircle2 className="size-4 text-[#3ee072]" />
                                                Email verified
                                            </span>
                                        </div>
                                    )}

                                    {status === 'verification-link-sent' ? (
                                        <div className={surfaceSuccessClass}>
                                            A new verification link has been sent to your email address.
                                        </div>
                                    ) : null}

                                    <div className="flex flex-wrap items-center gap-3">
                                        <Button
                                            disabled={processing}
                                            data-test="update-profile-button"
                                            className={surfacePrimaryButtonClass}
                                        >
                                            Save changes
                                        </Button>

                                        <Transition
                                            show={recentlySuccessful}
                                            enter="transition ease-in-out duration-200"
                                            enterFrom="opacity-0"
                                            leave="transition ease-in-out duration-200"
                                            leaveTo="opacity-0"
                                        >
                                            <p className={surfaceMutedTextClass}>Profile updated.</p>
                                        </Transition>
                                    </div>
                                </>
                            )}
                        </Form>
                    </div>
                </PageCard>

                <PageCard className="p-6 sm:p-7">
                    <div className="space-y-6">
                        <div>
                            <h2 className={sectionTitleClass}>Connected sign-in providers</h2>
                            <p className={sectionDescriptionClass}>
                                Link Google or GitHub so you can sign in without relying only on a password.
                            </p>
                        </div>

                        <div className="grid gap-3">
                            {oauthProviders.map((provider) => (
                                <div
                                    key={provider.key}
                                    className="flex flex-col gap-4 rounded-[22px] border border-white/8 bg-[#101b2f]/85 p-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="flex items-center gap-3">
                                        {provider.avatarUrl ? (
                                            <img
                                                src={provider.avatarUrl}
                                                alt={provider.label}
                                                className="size-11 rounded-full border border-white/10 object-cover"
                                            />
                                        ) : (
                                            <div className="inline-flex size-11 items-center justify-center rounded-full border border-white/10 bg-[#081428] text-[15px] font-semibold text-white">
                                                {provider.label.slice(0, 1)}
                                            </div>
                                        )}
                                        <div>
                                            <div className="text-[15px] font-semibold text-white">
                                                {provider.label}
                                            </div>
                                            <div className="mt-1 text-sm text-[#8fa0bf]">
                                                {provider.connected
                                                    ? `Connected as ${provider.connectedAs ?? provider.label}`
                                                    : provider.enabled
                                                      ? 'Not connected'
                                                      : 'Not configured'}
                                            </div>
                                        </div>
                                    </div>

                                    {provider.connected ? (
                                        <button
                                            type="button"
                                            className={`${surfaceSecondaryButtonClass} inline-flex items-center justify-center gap-2 px-4 text-sm font-medium disabled:cursor-not-allowed disabled:opacity-50`}
                                            disabled={!provider.canDisconnect}
                                            onClick={() =>
                                                router.delete(provider.disconnectUrl, {
                                                    preserveScroll: true,
                                                })
                                            }
                                        >
                                            <Link2Off className="size-4" />
                                            Disconnect
                                        </button>
                                    ) : (
                                        <a
                                            href={provider.redirectUrl}
                                            className={`${surfaceSecondaryButtonClass} inline-flex items-center justify-center gap-2 px-4 text-sm font-medium ${provider.enabled ? '' : 'pointer-events-none opacity-50'}`}
                                        >
                                            <Link2 className="size-4" />
                                            Connect
                                        </a>
                                    )}
                                </div>
                            ))}
                        </div>

                        {!passwordLoginEnabled ? (
                            <p className={surfaceMutedTextClass}>
                                Password login is disabled for this account. Set a password before disconnecting your last OAuth provider.
                            </p>
                        ) : null}
                    </div>
                </PageCard>

                <DeleteUser />
            </SettingsLayout>
        </MonitoringLayout>
    );
}

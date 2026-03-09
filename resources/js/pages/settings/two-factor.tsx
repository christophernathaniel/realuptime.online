import { Form, Head } from '@inertiajs/react';
import { ShieldBan, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { PageCard } from '@/components/monitoring/page-card';
import TwoFactorRecoveryCodes from '@/components/two-factor-recovery-codes';
import TwoFactorSetupModal from '@/components/two-factor-setup-modal';
import { Button } from '@/components/ui/button';
import { useTwoFactorAuth } from '@/hooks/use-two-factor-auth';
import MonitoringLayout from '@/layouts/monitoring-layout';
import SettingsLayout from '@/layouts/settings/layout';
import {
    surfaceDangerButtonClass,
    surfaceMutedTextClass,
    surfacePrimaryButtonClass,
} from '@/lib/realuptime-theme';
import { disable, enable } from '@/routes/two-factor';

type Props = {
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
};

export default function TwoFactor({
    requiresConfirmation = false,
    twoFactorEnabled = false,
}: Props) {
    const {
        qrCodeSvg,
        hasSetupData,
        manualSetupKey,
        clearSetupData,
        fetchSetupData,
        recoveryCodesList,
        fetchRecoveryCodes,
        errors,
    } = useTwoFactorAuth();
    const [showSetupModal, setShowSetupModal] = useState<boolean>(false);

    return (
        <MonitoringLayout>
            <Head title="Two-factor authentication" />

            <SettingsLayout
                title="Two-factor authentication"
                description="Require an authenticator code for sign-in so monitor access and alert settings are protected beyond a password."
            >
                <PageCard className="p-6 sm:p-7">
                    <div className="space-y-6">
                        <div>
                            <h2 className="text-[20px] font-semibold tracking-[-0.04em] text-white">
                                Authentication status
                            </h2>
                            <p className="mt-2 text-[14px] leading-6 text-[#9ca7b9]">
                                Add a second sign-in factor before making changes to monitors, notifications, and account settings.
                            </p>
                        </div>

                        <div className="flex flex-wrap items-center gap-3">
                            <span
                                className={twoFactorEnabled
                                    ? 'inline-flex items-center gap-2 rounded-full border border-[#7c8cff]/25 bg-[#171c33] px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-[#dbe1ff]'
                                    : 'inline-flex items-center gap-2 rounded-full border border-[#ff6b75]/20 bg-[#2a1621] px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-[#ffe6e8]'}
                            >
                                {twoFactorEnabled ? 'Enabled' : 'Disabled'}
                            </span>
                        </div>

                        <p className={surfaceMutedTextClass}>
                            {twoFactorEnabled
                                ? 'You will be prompted for a time-based authentication code whenever you sign in.'
                                : 'Enable two-factor authentication to require a secure code from your authenticator app during sign-in.'}
                        </p>

                        {twoFactorEnabled ? (
                            <Form {...disable.form()}>
                                {({ processing }) => (
                                    <Button
                                        variant="destructive"
                                        type="submit"
                                        disabled={processing}
                                        className={surfaceDangerButtonClass}
                                    >
                                        <ShieldBan className="size-4" />
                                        Disable 2FA
                                    </Button>
                                )}
                            </Form>
                        ) : hasSetupData ? (
                            <Button
                                onClick={() => setShowSetupModal(true)}
                                className={surfacePrimaryButtonClass}
                            >
                                <ShieldCheck className="size-4" />
                                Continue setup
                            </Button>
                        ) : (
                            <Form {...enable.form()} onSuccess={() => setShowSetupModal(true)}>
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className={surfacePrimaryButtonClass}
                                    >
                                        <ShieldCheck className="size-4" />
                                        Enable 2FA
                                    </Button>
                                )}
                            </Form>
                        )}
                    </div>
                </PageCard>

                {twoFactorEnabled ? (
                    <TwoFactorRecoveryCodes
                        recoveryCodesList={recoveryCodesList}
                        fetchRecoveryCodes={fetchRecoveryCodes}
                        errors={errors}
                    />
                ) : null}

                <TwoFactorSetupModal
                    isOpen={showSetupModal}
                    onClose={() => setShowSetupModal(false)}
                    requiresConfirmation={requiresConfirmation}
                    twoFactorEnabled={twoFactorEnabled}
                    qrCodeSvg={qrCodeSvg}
                    manualSetupKey={manualSetupKey}
                    clearSetupData={clearSetupData}
                    fetchSetupData={fetchSetupData}
                    errors={errors}
                />
            </SettingsLayout>
        </MonitoringLayout>
    );
}

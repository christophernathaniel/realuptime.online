import { Form } from '@inertiajs/react';
import { Eye, EyeOff, LockKeyhole, RefreshCw } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import AlertError from '@/components/alert-error';
import { PageCard } from '@/components/monitoring/page-card';
import { Button } from '@/components/ui/button';
import {
    surfaceMutedTextClass,
    surfacePrimaryButtonClass,
    surfaceSecondaryButtonClass,
} from '@/lib/realuptime-theme';
import { regenerateRecoveryCodes } from '@/routes/two-factor';

type Props = {
    recoveryCodesList: string[];
    fetchRecoveryCodes: () => Promise<void>;
    errors: string[];
};

export default function TwoFactorRecoveryCodes({
    recoveryCodesList,
    fetchRecoveryCodes,
    errors,
}: Props) {
    const [codesAreVisible, setCodesAreVisible] = useState<boolean>(false);
    const codesSectionRef = useRef<HTMLDivElement | null>(null);
    const canRegenerateCodes = recoveryCodesList.length > 0 && codesAreVisible;

    const toggleCodesVisibility = useCallback(async () => {
        if (!codesAreVisible && !recoveryCodesList.length) {
            await fetchRecoveryCodes();
        }

        setCodesAreVisible(!codesAreVisible);

        if (!codesAreVisible) {
            setTimeout(() => {
                codesSectionRef.current?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                });
            });
        }
    }, [codesAreVisible, recoveryCodesList.length, fetchRecoveryCodes]);

    useEffect(() => {
        if (!recoveryCodesList.length) {
            fetchRecoveryCodes();
        }
    }, [recoveryCodesList.length, fetchRecoveryCodes]);

    const RecoveryCodeIconComponent = codesAreVisible ? EyeOff : Eye;

    return (
        <PageCard className="p-6 sm:p-7">
            <div className="space-y-6">
                <div>
                    <div className="flex items-center gap-3 text-[20px] font-semibold tracking-[-0.04em] text-white">
                        <LockKeyhole className="size-5 text-[#7c8cff]" />
                        2FA recovery codes
                    </div>
                    <p className="mt-2 text-[14px] leading-6 text-[#9ca7b9]">
                        Recovery codes let you regain access if you lose your authenticator device. Store them in a secure password manager.
                    </p>
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <Button
                        onClick={toggleCodesVisibility}
                        className={surfacePrimaryButtonClass}
                        aria-expanded={codesAreVisible}
                        aria-controls="recovery-codes-section"
                    >
                        <RecoveryCodeIconComponent className="size-4" aria-hidden="true" />
                        {codesAreVisible ? 'Hide' : 'View'} recovery codes
                    </Button>

                    {canRegenerateCodes ? (
                        <Form
                            {...regenerateRecoveryCodes.form()}
                            options={{ preserveScroll: true }}
                            onSuccess={fetchRecoveryCodes}
                        >
                            {({ processing }) => (
                                <Button
                                    variant="secondary"
                                    type="submit"
                                    disabled={processing}
                                    className={surfaceSecondaryButtonClass}
                                    aria-describedby="regenerate-warning"
                                >
                                    <RefreshCw className="size-4" /> Regenerate codes
                                </Button>
                            )}
                        </Form>
                    ) : null}
                </div>

                <div
                    id="recovery-codes-section"
                    className={`relative overflow-hidden transition-all duration-300 ${codesAreVisible ? 'h-auto opacity-100' : 'h-0 opacity-0'}`}
                    aria-hidden={!codesAreVisible}
                >
                    <div className="space-y-3">
                        {errors?.length ? (
                            <AlertError errors={errors} />
                        ) : (
                            <>
                                <div
                                    ref={codesSectionRef}
                                    className="grid gap-2 rounded-[22px] border border-white/8 bg-[#081428] p-5 font-mono text-sm text-[#dce6fb]"
                                    role="list"
                                    aria-label="Recovery codes"
                                >
                                    {recoveryCodesList.length ? (
                                        recoveryCodesList.map((code, index) => (
                                            <div
                                                key={index}
                                                role="listitem"
                                                className="select-text rounded-[10px] bg-white/3 px-3 py-2"
                                            >
                                                {code}
                                            </div>
                                        ))
                                    ) : (
                                        <div
                                            className="space-y-2"
                                            aria-label="Loading recovery codes"
                                        >
                                            {Array.from({ length: 8 }, (_, index) => (
                                                <div
                                                    key={index}
                                                    className="h-4 animate-pulse rounded bg-white/8"
                                                    aria-hidden="true"
                                                />
                                            ))}
                                        </div>
                                    )}
                                </div>

                                <div className={surfaceMutedTextClass}>
                                    <p id="regenerate-warning">
                                        Each recovery code can be used once. If you need a fresh set, regenerate them after storing the new list somewhere secure.
                                    </p>
                                </div>
                            </>
                        )}
                    </div>
                </div>
            </div>
        </PageCard>
    );
}

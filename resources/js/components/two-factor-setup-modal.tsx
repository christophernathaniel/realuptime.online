import { Form } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { Check, Copy, ScanLine } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import AlertError from '@/components/alert-error';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { useAppearance } from '@/hooks/use-appearance';
import { useClipboard } from '@/hooks/use-clipboard';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import {
    surfacePrimaryButtonClass,
    surfaceSecondaryButtonClass,
} from '@/lib/realuptime-theme';
import { confirm } from '@/routes/two-factor';

function GridScanIcon() {
    return (
        <div className="mb-2 rounded-full border border-white/8 bg-[#101b2f] p-1 shadow-[0_18px_40px_rgba(0,0,0,0.18)]">
            <div className="relative overflow-hidden rounded-full border border-white/10 bg-[#081428] p-3 text-[#3ee072]">
                <div className="absolute inset-0 grid grid-cols-5 opacity-45">
                    {Array.from({ length: 5 }, (_, i) => (
                        <div
                            key={`col-${i + 1}`}
                            className="border-r border-white/8 last:border-r-0"
                        />
                    ))}
                </div>
                <div className="absolute inset-0 grid grid-rows-5 opacity-45">
                    {Array.from({ length: 5 }, (_, i) => (
                        <div
                            key={`row-${i + 1}`}
                            className="border-b border-white/8 last:border-b-0"
                        />
                    ))}
                </div>
                <ScanLine className="relative z-20 size-6" />
            </div>
        </div>
    );
}

function TwoFactorSetupStep({
    qrCodeSvg,
    manualSetupKey,
    buttonText,
    onNextStep,
    errors,
}: {
    qrCodeSvg: string | null;
    manualSetupKey: string | null;
    buttonText: string;
    onNextStep: () => void;
    errors: string[];
}) {
    const { resolvedAppearance } = useAppearance();
    const [copiedText, copy] = useClipboard();
    const IconComponent = copiedText === manualSetupKey ? Check : Copy;

    return errors?.length ? (
        <AlertError errors={errors} />
    ) : (
        <>
            <div className="mx-auto flex max-w-md overflow-hidden rounded-[24px] border border-white/8 bg-[#081428] p-5">
                <div className="mx-auto aspect-square w-64 rounded-[20px] border border-white/8 bg-[#101b2f] p-4">
                    <div className="z-10 flex h-full w-full items-center justify-center">
                        {qrCodeSvg ? (
                            <div
                                className="aspect-square w-full rounded-[18px] bg-white p-3 [&_svg]:size-full"
                                dangerouslySetInnerHTML={{
                                    __html: qrCodeSvg,
                                }}
                                style={{
                                    filter:
                                        resolvedAppearance === 'dark'
                                            ? 'invert(1) brightness(1.5)'
                                            : undefined,
                                }}
                            />
                        ) : (
                            <Spinner />
                        )}
                    </div>
                </div>
            </div>

            <div className="flex w-full">
                <Button className={`${surfacePrimaryButtonClass} w-full`} onClick={onNextStep}>
                    {buttonText}
                </Button>
            </div>

            <div className="relative flex w-full items-center justify-center text-[12px] uppercase tracking-[0.22em] text-[#677a99]">
                <div className="absolute inset-0 top-1/2 h-px w-full bg-white/8" />
                <span className="relative bg-[#101b2f] px-3 py-1">Manual setup key</span>
            </div>

            <div className="flex w-full">
                <div className="flex w-full items-stretch overflow-hidden rounded-[18px] border border-white/8 bg-[#081428]">
                    {!manualSetupKey ? (
                        <div className="flex h-full w-full items-center justify-center p-4 text-[#8fa0bf]">
                            <Spinner />
                        </div>
                    ) : (
                        <>
                            <input
                                type="text"
                                readOnly
                                value={manualSetupKey}
                                className="h-full w-full bg-transparent px-4 py-3 text-sm text-white outline-none"
                            />
                            <button
                                type="button"
                                onClick={() => copy(manualSetupKey)}
                                className="border-l border-white/8 px-4 text-[#8fa0bf] transition hover:bg-white/5 hover:text-white"
                            >
                                <IconComponent className="size-4" />
                            </button>
                        </>
                    )}
                </div>
            </div>
        </>
    );
}

function TwoFactorVerificationStep({
    onClose,
    onBack,
}: {
    onClose: () => void;
    onBack: () => void;
}) {
    const [code, setCode] = useState<string>('');
    const pinInputContainerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        setTimeout(() => {
            pinInputContainerRef.current?.querySelector('input')?.focus();
        }, 0);
    }, []);

    return (
        <Form
            {...confirm.form()}
            onSuccess={() => onClose()}
            resetOnError
            resetOnSuccess
        >
            {({
                processing,
                errors,
            }: {
                processing: boolean;
                errors?: { confirmTwoFactorAuthentication?: { code?: string } };
            }) => (
                <div ref={pinInputContainerRef} className="relative w-full space-y-5">
                    <div className="flex w-full flex-col items-center space-y-3 py-2">
                        <InputOTP
                            id="otp"
                            name="code"
                            maxLength={OTP_MAX_LENGTH}
                            onChange={setCode}
                            disabled={processing}
                            pattern={REGEXP_ONLY_DIGITS}
                        >
                            <InputOTPGroup className="gap-2">
                                {Array.from(
                                    { length: OTP_MAX_LENGTH },
                                    (_, index) => (
                                        <InputOTPSlot
                                            key={index}
                                            index={index}
                                            className="h-12 w-12 rounded-[16px] border border-white/10 bg-[#081428] text-base text-white shadow-none first:rounded-[16px] first:border first:border-white/10 last:rounded-[16px] last:border"
                                        />
                                    ),
                                )}
                            </InputOTPGroup>
                        </InputOTP>
                        <InputError
                            message={errors?.confirmTwoFactorAuthentication?.code}
                        />
                    </div>

                    <div className="flex w-full gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            className={`flex-1 ${surfaceSecondaryButtonClass}`}
                            onClick={onBack}
                            disabled={processing}
                        >
                            Back
                        </Button>
                        <Button
                            type="submit"
                            className={`flex-1 ${surfacePrimaryButtonClass}`}
                            disabled={processing || code.length < OTP_MAX_LENGTH}
                        >
                            Confirm
                        </Button>
                    </div>
                </div>
            )}
        </Form>
    );
}

type Props = {
    isOpen: boolean;
    onClose: () => void;
    requiresConfirmation: boolean;
    twoFactorEnabled: boolean;
    qrCodeSvg: string | null;
    manualSetupKey: string | null;
    clearSetupData: () => void;
    fetchSetupData: () => Promise<void>;
    errors: string[];
};

export default function TwoFactorSetupModal({
    isOpen,
    onClose,
    requiresConfirmation,
    twoFactorEnabled,
    qrCodeSvg,
    manualSetupKey,
    clearSetupData,
    fetchSetupData,
    errors,
}: Props) {
    const [showVerificationStep, setShowVerificationStep] =
        useState<boolean>(false);

    const modalConfig = useMemo<{
        title: string;
        description: string;
        buttonText: string;
    }>(() => {
        if (twoFactorEnabled) {
            return {
                title: 'Two-factor authentication enabled',
                description:
                    'Two-factor authentication is now enabled. Scan the QR code or enter the setup key in your authenticator app.',
                buttonText: 'Close',
            };
        }

        if (showVerificationStep) {
            return {
                title: 'Verify authentication code',
                description:
                    'Enter the 6-digit code from your authenticator app.',
                buttonText: 'Continue',
            };
        }

        return {
            title: 'Enable two-factor authentication',
            description:
                'Scan the QR code or enter the setup key in your authenticator app to finish setup.',
            buttonText: 'Continue',
        };
    }, [twoFactorEnabled, showVerificationStep]);

    const handleModalNextStep = useCallback(() => {
        if (requiresConfirmation) {
            setShowVerificationStep(true);
            return;
        }

        clearSetupData();
        onClose();
    }, [requiresConfirmation, clearSetupData, onClose]);

    const resetModalState = useCallback(() => {
        setShowVerificationStep(false);

        if (twoFactorEnabled) {
            clearSetupData();
        }
    }, [twoFactorEnabled, clearSetupData]);

    useEffect(() => {
        if (isOpen && !qrCodeSvg) {
            fetchSetupData();
        }
    }, [isOpen, qrCodeSvg, fetchSetupData]);

    const handleClose = useCallback(() => {
        resetModalState();
        onClose();
    }, [onClose, resetModalState]);

    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && handleClose()}>
            <DialogContent className="border-white/8 bg-[#101b2f] text-white shadow-[0_32px_90px_rgba(0,0,0,0.42)] sm:max-w-md">
                <DialogHeader className="flex items-center justify-center gap-3">
                    <GridScanIcon />
                    <DialogTitle className="text-center text-[24px] font-semibold tracking-[-0.04em] text-white">
                        {modalConfig.title}
                    </DialogTitle>
                    <DialogDescription className="text-center text-[14px] leading-6 text-[#8fa0bf]">
                        {modalConfig.description}
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-col items-center space-y-5">
                    {showVerificationStep ? (
                        <TwoFactorVerificationStep
                            onClose={onClose}
                            onBack={() => setShowVerificationStep(false)}
                        />
                    ) : (
                        <TwoFactorSetupStep
                            qrCodeSvg={qrCodeSvg}
                            manualSetupKey={manualSetupKey}
                            buttonText={modalConfig.buttonText}
                            onNextStep={handleModalNextStep}
                            errors={errors}
                        />
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}

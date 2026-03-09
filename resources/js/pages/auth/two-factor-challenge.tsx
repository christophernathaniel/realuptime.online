import { Form, Head } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import AuthLayout from '@/layouts/auth-layout';
import {
    surfaceInputClass,
    surfaceInfoClass,
    surfaceMutedTextClass,
    surfacePrimaryButtonClass,
} from '@/lib/realuptime-theme';
import { store } from '@/routes/two-factor/login';

export default function TwoFactorChallenge() {
    const [showRecoveryInput, setShowRecoveryInput] = useState<boolean>(false);
    const [code, setCode] = useState<string>('');

    const authConfigContent = useMemo<{
        title: string;
        description: string;
        toggleText: string;
    }>(() => {
        if (showRecoveryInput) {
            return {
                title: 'Recovery code',
                description:
                    'Confirm access by entering one of your emergency recovery codes.',
                toggleText: 'use an authentication code instead',
            };
        }

        return {
            title: 'Authentication code',
            description:
                'Enter the code from your authenticator app to finish signing in.',
            toggleText: 'use a recovery code instead',
        };
    }, [showRecoveryInput]);

    const toggleRecoveryMode = (clearErrors: () => void): void => {
        setShowRecoveryInput(!showRecoveryInput);
        clearErrors();
        setCode('');
    };

    return (
        <AuthLayout
            title={authConfigContent.title}
            description={authConfigContent.description}
        >
            <Head title="Two-factor authentication" />

            <div className="space-y-6">
                <div className={surfaceInfoClass}>
                    Two-factor authentication is enabled for this account. Finish the challenge to access your monitoring workspace.
                </div>

                <Form
                    {...store.form()}
                    className="space-y-5"
                    resetOnError
                    resetOnSuccess={!showRecoveryInput}
                >
                    {({ errors, processing, clearErrors }) => (
                        <>
                            {showRecoveryInput ? (
                                <div className="space-y-2.5">
                                    <Input
                                        name="recovery_code"
                                        type="text"
                                        placeholder="Enter recovery code"
                                        autoFocus={showRecoveryInput}
                                        required
                                        className={surfaceInputClass}
                                    />
                                    <InputError message={errors.recovery_code} />
                                </div>
                            ) : (
                                <div className="space-y-4 text-center">
                                    <div className="flex justify-center">
                                        <InputOTP
                                            name="code"
                                            maxLength={OTP_MAX_LENGTH}
                                            value={code}
                                            onChange={(value) => setCode(value)}
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
                                    </div>
                                    <InputError message={errors.code} />
                                </div>
                            )}

                            <Button
                                type="submit"
                                className={`${surfacePrimaryButtonClass} w-full`}
                                disabled={processing}
                            >
                                Continue
                            </Button>

                            <div className={`text-center ${surfaceMutedTextClass}`}>
                                Or{' '}
                                <button
                                    type="button"
                                    className="cursor-pointer text-[#57c7c2] underline decoration-[#57c7c2]/35 underline-offset-4 transition-colors hover:text-[#86e4de]"
                                    onClick={() => toggleRecoveryMode(clearErrors)}
                                >
                                    {authConfigContent.toggleText}
                                </button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </AuthLayout>
    );
}

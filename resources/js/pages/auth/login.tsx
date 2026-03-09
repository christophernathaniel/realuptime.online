import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { OAuthProviderButtons } from '@/components/oauth-provider-buttons';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import {
    surfaceCheckboxClass,
    surfaceInputClass,
    surfaceLabelClass,
    surfaceMutedTextClass,
    surfacePrimaryButtonClass,
    surfaceSuccessClass,
} from '@/lib/realuptime-theme';
import { store } from '@/routes/login';

type Props = {
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
    oauthProviders: Array<{
        key: string;
        label: string;
        enabled: boolean;
        redirectUrl: string;
    }>;
};

export default function Login({
    status,
    canResetPassword,
    canRegister,
    oauthProviders,
}: Props) {
    return (
        <AuthLayout
            title="Sign in"
            description="Access your monitoring workspace, incidents, email alerts, and status pages."
            variant="form-only"
        >
            <Head title="Log in" />

            {status ? <div className={surfaceSuccessClass}>{status}</div> : null}

            <OAuthProviderButtons providers={oauthProviders} className="mb-6" />

            <div className="mb-6 flex items-center gap-3 text-[11px] font-semibold uppercase tracking-[0.26em] text-[#6f82a3]">
                <span className="h-px flex-1 bg-white/8" />
                Or use email
                <span className="h-px flex-1 bg-white/8" />
            </div>

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-5">
                            <div className="grid gap-2.5">
                                <Label htmlFor="email" className={surfaceLabelClass}>
                                    Email address
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="email@example.com"
                                    className={surfaceInputClass}
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2.5">
                                <div className="flex items-center gap-3">
                                    <Label htmlFor="password" className={surfaceLabelClass}>
                                        Password
                                    </Label>
                                    {canResetPassword ? (
                                        <TextLink
                                            href="/forgot-password"
                                            className="ml-auto text-sm"
                                            tabIndex={5}
                                        >
                                            Forgot password?
                                        </TextLink>
                                    ) : null}
                                </div>
                                <Input
                                    id="password"
                                    type="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="Password"
                                    className={surfaceInputClass}
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center gap-3 rounded-[18px] border border-white/8 bg-[#101b2f]/72 px-4 py-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                    className={surfaceCheckboxClass}
                                />
                                <Label htmlFor="remember" className="text-sm text-[#dce6fb]">
                                    Keep this device signed in
                                </Label>
                            </div>

                            <Button
                                type="submit"
                                className={surfacePrimaryButtonClass}
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing ? <Spinner /> : null}
                                Log in
                            </Button>
                        </div>

                        {canRegister ? (
                            <div className={`text-center ${surfaceMutedTextClass}`}>
                                Don&apos;t have an account?{' '}
                                <TextLink href="/register" tabIndex={5}>
                                    Create one
                                </TextLink>
                            </div>
                        ) : null}
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}

import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { OAuthProviderButtons } from '@/components/oauth-provider-buttons';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import {
    surfaceInputClass,
    surfaceLabelClass,
    surfaceMutedTextClass,
    surfacePrimaryButtonClass,
} from '@/lib/realuptime-theme';

type Props = {
    oauthProviders: Array<{
        key: string;
        label: string;
        enabled: boolean;
        redirectUrl: string;
    }>;
};

export default function Register({ oauthProviders }: Props) {
    const hasOAuthProviders = oauthProviders.length > 0;

    return (
        <AuthLayout
            title="Create your account"
            description="Start monitoring websites, HTTP endpoints, TCP ports, and ping targets from a single workspace."
            variant="form-only"
        >
            <Head title="Register" />

            {hasOAuthProviders ? (
                <>
                    <OAuthProviderButtons providers={oauthProviders} className="mb-6" />

                    <div className="mb-6 flex items-center gap-3 text-[11px] font-semibold uppercase tracking-[0.26em] text-[#6f82a3]">
                        <span className="h-px flex-1 bg-white/8" />
                        Or sign up with email
                        <span className="h-px flex-1 bg-white/8" />
                    </div>
                </>
            ) : null}

            <Form
                action="/register"
                method="post"
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-5">
                            <div className="grid gap-2.5">
                                <Label htmlFor="name" className={surfaceLabelClass}>
                                    Name
                                </Label>
                                <Input
                                    id="name"
                                    type="text"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="name"
                                    name="name"
                                    placeholder="Full name"
                                    className={surfaceInputClass}
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2.5">
                                <Label htmlFor="email" className={surfaceLabelClass}>
                                    Email address
                                </Label>
                                <Input
                                    id="email"
                                    type="text"
                                    required
                                    tabIndex={2}
                                    inputMode="email"
                                    autoComplete="email"
                                    name="email"
                                    placeholder="email@example.com"
                                    className={surfaceInputClass}
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2.5">
                                <Label htmlFor="password" className={surfaceLabelClass}>
                                    Password
                                </Label>
                                <Input
                                    id="password"
                                    type="password"
                                    required
                                    tabIndex={3}
                                    autoComplete="new-password"
                                    name="password"
                                    placeholder="Password"
                                    className={surfaceInputClass}
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2.5">
                                <Label
                                    htmlFor="password_confirmation"
                                    className={surfaceLabelClass}
                                >
                                    Confirm password
                                </Label>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    required
                                    tabIndex={4}
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    placeholder="Confirm password"
                                    className={surfaceInputClass}
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <Button
                                type="submit"
                                className={surfacePrimaryButtonClass}
                                tabIndex={5}
                                data-test="register-user-button"
                            >
                                {processing ? <Spinner /> : null}
                                Create account
                            </Button>
                        </div>

                        <div className={`text-center ${surfaceMutedTextClass}`}>
                            Already have an account?{' '}
                            <TextLink href="/login" tabIndex={6}>
                                Log in
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}

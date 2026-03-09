import { Form, Head } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import {
    surfaceInputClass,
    surfaceLabelClass,
    surfaceMutedTextClass,
    surfacePrimaryButtonClass,
    surfaceSuccessClass,
} from '@/lib/realuptime-theme';

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <AuthLayout
            title="Reset your password"
            description="Enter the account email address and RealUptime will send you a reset link."
        >
            <Head title="Forgot password" />

            {status ? <div className={surfaceSuccessClass}>{status}</div> : null}

            <div className="space-y-6">
                <Form action="/forgot-password" method="post">
                    {({ processing, errors }) => (
                        <div className="space-y-6">
                            <div className="grid gap-2.5">
                                <Label htmlFor="email" className={surfaceLabelClass}>
                                    Email address
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    autoComplete="off"
                                    autoFocus
                                    placeholder="email@example.com"
                                    className={surfaceInputClass}
                                />
                                <InputError message={errors.email} />
                            </div>

                            <Button
                                className={`${surfacePrimaryButtonClass} w-full`}
                                disabled={processing}
                                data-test="email-password-reset-link-button"
                            >
                                {processing ? (
                                    <LoaderCircle className="size-4 animate-spin" />
                                ) : null}
                                Email password reset link
                            </Button>
                        </div>
                    )}
                </Form>

                <div className={`text-center ${surfaceMutedTextClass}`}>
                    Return to <TextLink href="/login">log in</TextLink>
                </div>
            </div>
        </AuthLayout>
    );
}

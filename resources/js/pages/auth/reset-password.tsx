import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import {
    surfaceInputClass,
    surfaceLabelClass,
    surfacePrimaryButtonClass,
} from '@/lib/realuptime-theme';

type Props = {
    token: string;
    email: string;
};

export default function ResetPassword({ token, email }: Props) {
    return (
        <AuthLayout
            title="Choose a new password"
            description="Set a fresh password for your RealUptime account and return to the monitoring workspace."
        >
            <Head title="Reset password" />

            <Form
                action="/reset-password"
                method="post"
                transform={(data) => ({ ...data, token, email })}
                resetOnSuccess={['password', 'password_confirmation']}
                className="space-y-6"
            >
                {({ processing, errors }) => (
                    <div className="grid gap-5">
                        <div className="grid gap-2.5">
                            <Label htmlFor="email" className={surfaceLabelClass}>
                                Email address
                            </Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                autoComplete="email"
                                value={email}
                                readOnly
                                className={`${surfaceInputClass} opacity-80`}
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="grid gap-2.5">
                            <Label htmlFor="password" className={surfaceLabelClass}>
                                New password
                            </Label>
                            <Input
                                id="password"
                                type="password"
                                name="password"
                                autoComplete="new-password"
                                autoFocus
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
                                name="password_confirmation"
                                autoComplete="new-password"
                                placeholder="Confirm password"
                                className={surfaceInputClass}
                            />
                            <InputError message={errors.password_confirmation} />
                        </div>

                        <Button
                            type="submit"
                            className={surfacePrimaryButtonClass}
                            disabled={processing}
                            data-test="reset-password-button"
                        >
                            {processing ? <Spinner /> : null}
                            Reset password
                        </Button>
                    </div>
                )}
            </Form>
        </AuthLayout>
    );
}

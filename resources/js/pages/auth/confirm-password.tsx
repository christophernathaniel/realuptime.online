import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import {
    surfaceInputClass,
    surfaceInfoClass,
    surfaceLabelClass,
    surfacePrimaryButtonClass,
} from '@/lib/realuptime-theme';
import { store } from '@/routes/password/confirm';

export default function ConfirmPassword() {
    return (
        <AuthLayout
            title="Confirm your password"
            description="This workspace contains protected account actions. Re-enter your password to continue."
        >
            <Head title="Confirm password" />

            <div className="space-y-6">
                <div className={surfaceInfoClass}>
                    This confirmation step protects security-sensitive account settings and destructive actions.
                </div>

                <Form {...store.form()} resetOnSuccess={['password']}>
                    {({ processing, errors }) => (
                        <div className="space-y-6">
                            <div className="grid gap-2.5">
                                <Label htmlFor="password" className={surfaceLabelClass}>
                                    Password
                                </Label>
                                <Input
                                    id="password"
                                    type="password"
                                    name="password"
                                    placeholder="Password"
                                    autoComplete="current-password"
                                    autoFocus
                                    className={surfaceInputClass}
                                />
                                <InputError message={errors.password} />
                            </div>

                            <Button
                                className={`${surfacePrimaryButtonClass} w-full`}
                                disabled={processing}
                                data-test="confirm-password-button"
                            >
                                {processing ? <Spinner /> : null}
                                Confirm password
                            </Button>
                        </div>
                    )}
                </Form>
            </div>
        </AuthLayout>
    );
}

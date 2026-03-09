import { Form, Head } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import {
    surfaceInfoClass,
    surfacePrimaryButtonClass,
    surfaceSuccessClass,
} from '@/lib/realuptime-theme';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    return (
        <AuthLayout
            title="Verify your email"
            description="Confirm your inbox before you manage monitors and notifications."
        >
            <Head title="Email verification" />

            <div className="space-y-5">
                <div className={surfaceInfoClass}>
                    Please verify your email address by clicking the link we just sent. This protects monitor ownership, email alerts, and account recovery.
                </div>

                {status === 'verification-link-sent' ? (
                    <div className={surfaceSuccessClass}>
                        A new verification link has been sent to your email address.
                    </div>
                ) : null}

                <Form {...send.form()} className="space-y-4 text-center">
                    {({ processing }) => (
                        <>
                            <Button
                                disabled={processing}
                                className={`${surfacePrimaryButtonClass} w-full`}
                            >
                                {processing ? <Spinner /> : null}
                                Resend verification email
                            </Button>

                            <TextLink
                                href="/logout"
                                method="post"
                                as="button"
                                className="mx-auto block text-sm"
                            >
                                Log out
                            </TextLink>
                        </>
                    )}
                </Form>
            </div>
        </AuthLayout>
    );
}

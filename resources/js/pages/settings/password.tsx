import { Transition } from '@headlessui/react';
import { Form, Head } from '@inertiajs/react';
import { useRef } from 'react';
import PasswordController from '@/actions/App/Http/Controllers/Settings/PasswordController';
import InputError from '@/components/input-error';
import { PageCard } from '@/components/monitoring/page-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import MonitoringLayout from '@/layouts/monitoring-layout';
import SettingsLayout from '@/layouts/settings/layout';
import {
    surfaceInputClass,
    surfaceLabelClass,
    surfaceMutedTextClass,
    surfacePrimaryButtonClass,
} from '@/lib/realuptime-theme';

export default function Password() {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    return (
        <MonitoringLayout>
            <Head title="Password settings" />

            <SettingsLayout
                title="Password"
                description="Protect account access with a strong password and rotate credentials when needed."
            >
                <PageCard className="p-6 sm:p-7">
                    <div className="space-y-6">
                        <div>
                            <h2 className="text-[20px] font-semibold tracking-[-0.04em] text-white">
                                Update password
                            </h2>
                            <p className="mt-2 text-[14px] leading-6 text-[#8fa0bf]">
                                Use a long, unique password so monitor configuration, email alerts, and status pages remain protected.
                            </p>
                        </div>

                        <Form
                            {...PasswordController.update.form()}
                            options={{ preserveScroll: true }}
                            resetOnError={[
                                'password',
                                'password_confirmation',
                                'current_password',
                            ]}
                            resetOnSuccess
                            onError={(errors) => {
                                if (errors.password) {
                                    passwordInput.current?.focus();
                                }

                                if (errors.current_password) {
                                    currentPasswordInput.current?.focus();
                                }
                            }}
                            className="space-y-6"
                        >
                            {({ errors, processing, recentlySuccessful }) => (
                                <>
                                    <div className="grid gap-5 md:grid-cols-3">
                                        <div className="grid gap-2.5">
                                            <Label
                                                htmlFor="current_password"
                                                className={surfaceLabelClass}
                                            >
                                                Current password
                                            </Label>
                                            <Input
                                                id="current_password"
                                                ref={currentPasswordInput}
                                                name="current_password"
                                                type="password"
                                                className={surfaceInputClass}
                                                autoComplete="current-password"
                                                placeholder="Current password"
                                            />
                                            <InputError message={errors.current_password} />
                                        </div>

                                        <div className="grid gap-2.5">
                                            <Label htmlFor="password" className={surfaceLabelClass}>
                                                New password
                                            </Label>
                                            <Input
                                                id="password"
                                                ref={passwordInput}
                                                name="password"
                                                type="password"
                                                className={surfaceInputClass}
                                                autoComplete="new-password"
                                                placeholder="New password"
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
                                                name="password_confirmation"
                                                type="password"
                                                className={surfaceInputClass}
                                                autoComplete="new-password"
                                                placeholder="Confirm password"
                                            />
                                            <InputError
                                                message={errors.password_confirmation}
                                            />
                                        </div>
                                    </div>

                                    <div className="flex flex-wrap items-center gap-3">
                                        <Button
                                            disabled={processing}
                                            data-test="update-password-button"
                                            className={surfacePrimaryButtonClass}
                                        >
                                            Save password
                                        </Button>

                                        <Transition
                                            show={recentlySuccessful}
                                            enter="transition ease-in-out duration-200"
                                            enterFrom="opacity-0"
                                            leave="transition ease-in-out duration-200"
                                            leaveTo="opacity-0"
                                        >
                                            <p className={surfaceMutedTextClass}>Password updated.</p>
                                        </Transition>
                                    </div>
                                </>
                            )}
                        </Form>
                    </div>
                </PageCard>
            </SettingsLayout>
        </MonitoringLayout>
    );
}

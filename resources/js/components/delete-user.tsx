import { Form } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { useRef } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import InputError from '@/components/input-error';
import { PageCard } from '@/components/monitoring/page-card';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    surfaceDangerButtonClass,
    surfaceInputClass,
    surfaceLabelClass,
    surfaceSecondaryButtonClass,
} from '@/lib/realuptime-theme';

export default function DeleteUser() {
    const passwordInput = useRef<HTMLInputElement>(null);

    return (
        <PageCard className="p-6 sm:p-7">
            <div className="space-y-6">
                <div>
                    <h2 className="text-[20px] font-semibold tracking-[-0.04em] text-white">
                        Delete account
                    </h2>
                    <p className="mt-2 text-[14px] leading-6 text-[#8fa0bf]">
                        Permanently remove this account, including monitor configuration, incidents, notifications, and public status pages.
                    </p>
                </div>

                <div className="rounded-[22px] border border-[#ff6b75]/18 bg-[#241824] p-5 text-[#ffe6e8]">
                    <div className="flex items-start gap-3">
                        <span className="inline-flex size-10 items-center justify-center rounded-2xl border border-[#ff6b75]/18 bg-[#2e1d2b] text-[#ff8d95]">
                            <AlertTriangle className="size-4" />
                        </span>
                        <div className="space-y-2">
                            <p className="text-[15px] font-semibold">Irreversible action</p>
                            <p className="text-sm leading-6 text-[#f4c7ca]">
                                Deleting your account removes all associated data permanently. Proceed only if you intend to wipe the entire workspace.
                            </p>
                        </div>
                    </div>
                </div>

                <Dialog>
                    <DialogTrigger asChild>
                        <Button
                            variant="destructive"
                            data-test="delete-user-button"
                            className={surfaceDangerButtonClass}
                        >
                            Delete account
                        </Button>
                    </DialogTrigger>
                    <DialogContent className="border-white/8 bg-[#101b2f] text-white sm:max-w-xl">
                        <DialogTitle className="text-[22px] font-semibold tracking-[-0.04em] text-white">
                            Delete this account?
                        </DialogTitle>
                        <DialogDescription className="text-[14px] leading-6 text-[#8fa0bf]">
                            Once deleted, all monitors, incidents, notifications, and status pages tied to this account are permanently removed. Enter your password to confirm.
                        </DialogDescription>

                        <Form
                            {...ProfileController.destroy.form()}
                            options={{ preserveScroll: true }}
                            onError={() => passwordInput.current?.focus()}
                            resetOnSuccess
                            className="space-y-6"
                        >
                            {({ resetAndClearErrors, processing, errors }) => (
                                <>
                                    <div className="grid gap-2.5">
                                        <Label htmlFor="password" className={surfaceLabelClass}>
                                            Password
                                        </Label>

                                        <Input
                                            id="password"
                                            type="password"
                                            name="password"
                                            ref={passwordInput}
                                            placeholder="Password"
                                            autoComplete="current-password"
                                            className={surfaceInputClass}
                                        />

                                        <InputError message={errors.password} />
                                    </div>

                                    <DialogFooter className="gap-3">
                                        <DialogClose asChild>
                                            <Button
                                                variant="secondary"
                                                className={surfaceSecondaryButtonClass}
                                                onClick={() => resetAndClearErrors()}
                                            >
                                                Cancel
                                            </Button>
                                        </DialogClose>

                                        <Button
                                            variant="destructive"
                                            disabled={processing}
                                            className={surfaceDangerButtonClass}
                                            asChild
                                        >
                                            <button
                                                type="submit"
                                                data-test="confirm-delete-user-button"
                                            >
                                                Delete account
                                            </button>
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
        </PageCard>
    );
}

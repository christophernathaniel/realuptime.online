import { Form, Head } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { PageCard } from '@/components/monitoring/page-card';
import { Button } from '@/components/ui/button';
import MonitoringLayout from '@/layouts/monitoring-layout';
import {
    surfaceMutedTextClass,
    surfacePrimaryButtonClass,
} from '@/lib/realuptime-theme';

type Invitation = {
    workspaceName: string;
    invitedEmail: string;
    canAccept: boolean;
    acceptUrl: string;
};

export default function WorkspaceInvitation({
    invitation,
}: {
    invitation: Invitation;
}) {
    return (
        <MonitoringLayout>
            <Head title="Workspace invitation" />

            <main className="mx-auto flex min-h-[70vh] w-full max-w-2xl items-center px-4 py-10 sm:px-6">
                <PageCard className="w-full p-6 sm:p-8">
                    <div className="flex items-start gap-4">
                        <div className="flex size-11 shrink-0 items-center justify-center rounded-lg border border-white/10 bg-[#101b2f] text-[#9bb4ff]">
                            <UserPlus className="size-5" />
                        </div>
                        <div className="min-w-0">
                            <h1 className="text-[24px] font-semibold text-white">
                                Join {invitation.workspaceName}
                            </h1>
                            <p className={`mt-2 ${surfaceMutedTextClass}`}>
                                This invitation was issued to{' '}
                                {invitation.invitedEmail}.
                            </p>
                        </div>
                    </div>

                    <div className="mt-7 border-t border-white/8 pt-6">
                        {invitation.canAccept ? (
                            <Form action={invitation.acceptUrl} method="post">
                                {({ processing }) => (
                                    <Button
                                        disabled={processing}
                                        className={surfacePrimaryButtonClass}
                                    >
                                        <UserPlus className="size-4" />
                                        Accept invitation
                                    </Button>
                                )}
                            </Form>
                        ) : (
                            <p className={surfaceMutedTextClass}>
                                Sign in with {invitation.invitedEmail} to accept
                                this invitation.
                            </p>
                        )}
                    </div>
                </PageCard>
            </main>
        </MonitoringLayout>
    );
}

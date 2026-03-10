<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\WorkspaceMembership;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected WorkspaceMembership $membership,
        protected User $owner,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = sprintf('You were invited to %s on RealUptime', $this->owner->name);

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.workspace-invitation', [
                'subject' => $subject,
                'preheader' => sprintf('%s invited you to a RealUptime workspace.', $this->owner->name),
                'eyebrow' => 'Workspace invitation',
                'title' => 'You have been invited to collaborate',
                'intro' => sprintf(
                    '%s invited you to join their RealUptime workspace. Sign in with the invited email address, then accept the invitation to access checks, incidents, status pages, and notifications.',
                    $this->owner->name,
                ),
                'toneLabel' => 'Invitation',
                'details' => [
                    ['label' => 'Workspace owner', 'value' => $this->owner->name],
                    ['label' => 'Invited email', 'value' => $this->membership->invited_email],
                ],
                'buttonLabel' => 'Accept invitation',
                'buttonUrl' => route('workspace-invitations.accept', $this->membership->token),
                'footnote' => 'If you did not expect this invitation, you can safely ignore this email.',
            ]);
    }
}

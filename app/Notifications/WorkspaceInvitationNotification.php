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
        return (new MailMessage)
            ->subject(sprintf('You were invited to %s on RealUptime', $this->owner->name))
            ->greeting('Workspace invitation')
            ->line(sprintf('%s invited you to collaborate inside their RealUptime workspace.', $this->owner->name))
            ->line('Sign in with the invited email address, then accept the invitation to access monitors, incidents, status pages, maintenance, and notifications.')
            ->action('Accept invitation', route('workspace-invitations.accept', $this->membership->token))
            ->line(sprintf('Invited email: %s', $this->membership->invited_email));
    }
}

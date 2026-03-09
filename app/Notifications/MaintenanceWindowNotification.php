<?php

namespace App\Notifications;

use App\Models\MaintenanceWindow;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MaintenanceWindowNotification extends Notification
{
    use Queueable;

    public function __construct(protected MaintenanceWindow $window) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $monitorNames = $this->window->monitors->pluck('name')->values()->all();

        $mail = (new MailMessage)
            ->subject(sprintf('Scheduled maintenance: %s', $this->window->title))
            ->greeting('Maintenance scheduled')
            ->line(sprintf('Window: %s', $this->window->title))
            ->line(sprintf(
                'Starts: %s',
                $this->window->starts_at?->timezone(config('app.timezone'))->format('M j, Y H:i:s') ?? 'n/a',
            ))
            ->line(sprintf(
                'Ends: %s',
                $this->window->ends_at?->timezone(config('app.timezone'))->format('M j, Y H:i:s') ?? 'n/a',
            ))
            ->line($this->window->message ?: 'Planned work has been scheduled for the affected monitors.');

        if ($monitorNames !== []) {
            $mail->line(sprintf('Affected monitors: %s', implode(', ', $monitorNames)));
        }

        return $mail;
    }
}

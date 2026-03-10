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
        $subject = sprintf('Scheduled maintenance: %s', $this->window->title);

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.maintenance-window', [
                'subject' => $subject,
                'preheader' => sprintf('Scheduled maintenance for %s.', $this->window->title),
                'eyebrow' => 'Maintenance notice',
                'title' => 'Scheduled maintenance has been planned',
                'intro' => $this->window->message ?: 'Planned work has been scheduled for the affected checks.',
                'toneLabel' => 'Maintenance',
                'details' => array_filter([
                    ['label' => 'Window', 'value' => $this->window->title],
                    [
                        'label' => 'Starts',
                        'value' => $this->window->starts_at?->timezone(config('app.timezone'))->format('M j, Y H:i:s') ?? 'n/a',
                    ],
                    [
                        'label' => 'Ends',
                        'value' => $this->window->ends_at?->timezone(config('app.timezone'))->format('M j, Y H:i:s') ?? 'n/a',
                    ],
                    $monitorNames !== [] ? ['label' => 'Affected checks', 'value' => implode(', ', $monitorNames)] : null,
                ]),
                'buttonLabel' => 'Open dashboard',
                'buttonUrl' => url('/maintenance'),
                'footnote' => 'You can review planned maintenance windows from inside your RealUptime workspace.',
            ]);
    }
}

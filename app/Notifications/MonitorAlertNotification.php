<?php

namespace App\Notifications;

use App\Models\Incident;
use App\Models\Monitor;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MonitorAlertNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Monitor $monitor,
        protected string $type,
        protected ?Incident $incident = null,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = match ($this->type) {
            'down' => sprintf('%s is down', $this->monitor->name),
            'critical' => sprintf('Critical outage: %s', $this->monitor->name),
            'degraded' => sprintf('Performance degraded: %s', $this->monitor->name),
            'ssl_expiry' => sprintf('SSL certificate alert: %s', $this->monitor->name),
            'domain_expiry' => sprintf('Domain expiry alert: %s', $this->monitor->name),
            'recovered' => sprintf('%s is back up', $this->monitor->name),
            'resolved' => sprintf('Alert resolved: %s', $this->monitor->name),
            default => sprintf('Test notification for %s', $this->monitor->name),
        };

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting('Monitoring update')
            ->line(sprintf('Monitor: %s', $this->monitor->name))
            ->line(sprintf('Type: %s', strtoupper($this->monitor->type)))
            ->line(sprintf('Target: %s', $this->monitor->target ?? 'Heartbeat monitor'));

        if ($this->type === 'test') {
            return $mail
                ->line('This is a test email from your monitoring dashboard.')
                ->line('Email notifications are configured correctly.');
        }

        if ($this->incident) {
            $mail->line(sprintf('Incident started: %s', $this->incident->started_at?->timezone(config('app.timezone'))->format('M j, Y H:i:s')));
            $mail->line(sprintf('Incident type: %s', $this->incidentTypeLabel()));
            $mail->line(sprintf('Severity: %s', ucfirst($this->incident->severity)));
        }

        if ($this->type === 'down') {
            return $mail
                ->line(sprintf('Current status: %s', strtoupper($this->monitor->status)))
                ->line(sprintf('Reason: %s', $this->monitor->last_error_message ?? 'The latest check failed.'))
                ->line('The monitor will keep retrying based on the configured interval and retry limit.');
        }

        if ($this->type === 'critical') {
            return $mail
                ->line(sprintf(
                    'The monitor has remained down for at least %d minute(s).',
                    max(1, (int) ($this->monitor->critical_alert_after_minutes ?? 0)),
                ))
                ->line(sprintf('Reason: %s', $this->incident?->reason ?? $this->monitor->last_error_message ?? 'The latest check failed.'));
        }

        if ($this->type === 'degraded') {
            return $mail
                ->line(sprintf('Response time threshold: %s', $this->monitor->latency_threshold_ms ? $this->monitor->latency_threshold_ms.' ms' : 'Not configured'))
                ->line(sprintf('Current response time: %s', $this->monitor->last_response_time_ms ? $this->monitor->last_response_time_ms.' ms' : 'n/a'))
                ->line(sprintf('Reason: %s', $this->incident?->reason ?? 'Latency remained above the configured warning threshold.'));
        }

        if (in_array($this->type, ['ssl_expiry', 'domain_expiry'], true)) {
            return $mail
                ->line(sprintf('Reason: %s', $this->incident?->reason ?? 'The latest expiry threshold was breached.'))
                ->line(sprintf('Target host: %s', $this->monitor->target ?? 'n/a'));
        }

        if ($this->type === 'resolved') {
            return $mail
                ->line(sprintf('The %s incident has cleared.', $this->incidentTypeLabel()))
                ->line(sprintf('Latest response time: %s', $this->monitor->last_response_time_ms ? $this->monitor->last_response_time_ms.' ms' : 'n/a'));
        }

        return $mail
            ->line('The monitor recovered successfully.')
            ->line(sprintf('Latest response time: %s', $this->monitor->last_response_time_ms ? $this->monitor->last_response_time_ms.' ms' : 'n/a'));
    }

    protected function incidentTypeLabel(): string
    {
        return match ($this->incident?->type) {
            Incident::TYPE_DEGRADED_PERFORMANCE => 'Degraded performance',
            Incident::TYPE_SSL_EXPIRY => 'SSL expiry',
            Incident::TYPE_DOMAIN_EXPIRY => 'Domain expiry',
            default => 'Downtime',
        };
    }
}

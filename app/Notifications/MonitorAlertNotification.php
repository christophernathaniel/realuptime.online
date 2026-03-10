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

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.monitor-alert', [
                'subject' => $subject,
                'preheader' => $this->preheader(),
                'eyebrow' => 'RealUptime notification',
                'title' => $this->title(),
                'intro' => $this->intro(),
                'toneLabel' => $this->toneLabel(),
                'details' => $this->details(),
                'buttonLabel' => 'Open check',
                'buttonUrl' => route('monitors.show', $this->monitor),
                'footnote' => 'You are receiving this email because this address is attached to an active RealUptime notification contact.',
            ]);
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

    protected function title(): string
    {
        return match ($this->type) {
            'down' => 'A check has gone down',
            'recovered' => 'A check has recovered',
            default => 'Email notifications are active',
        };
    }

    protected function intro(): string
    {
        return match ($this->type) {
            'down' => $this->incident?->reason ?? $this->monitor->last_error_message ?? 'RealUptime detected a downtime event and opened an incident.',
            'recovered' => 'The downtime incident has ended and the latest check is reporting healthy again.',
            default => 'This is a test message from RealUptime. Delivery is working and your notification pipeline is ready.',
        };
    }

    protected function preheader(): string
    {
        return match ($this->type) {
            'down' => sprintf('%s is down.', $this->monitor->name),
            'recovered' => sprintf('%s has recovered.', $this->monitor->name),
            default => 'RealUptime test email.',
        };
    }

    protected function toneLabel(): string
    {
        return match ($this->type) {
            'down' => 'Downtime',
            'recovered' => 'Recovered',
            default => 'Test',
        };
    }

    /**
     * @return array<int, array{label:string,value:string}>
     */
    protected function details(): array
    {
        $details = [
            ['label' => 'Check', 'value' => $this->monitor->name],
            ['label' => 'Type', 'value' => strtoupper($this->monitor->type)],
            ['label' => 'Target', 'value' => $this->monitor->target ?? 'Heartbeat check'],
        ];

        if ($this->incident?->started_at) {
            $details[] = [
                'label' => 'Started',
                'value' => $this->incident->started_at->timezone(config('app.timezone'))->format('M j, Y H:i:s'),
            ];
        }

        if ($this->incident) {
            $details[] = ['label' => 'Incident', 'value' => $this->incidentTypeLabel()];
            $details[] = ['label' => 'Severity', 'value' => ucfirst($this->incident->severity)];
        }

        if ($this->type === 'down') {
            $details[] = ['label' => 'Status', 'value' => strtoupper($this->monitor->status)];
            $details[] = ['label' => 'Reason', 'value' => $this->monitor->last_error_message ?? 'The latest check failed.'];
        }

        if ($this->type === 'recovered') {
            $details[] = [
                'label' => 'Latest response',
                'value' => $this->monitor->last_response_time_ms ? $this->monitor->last_response_time_ms.' ms' : 'n/a',
            ];
        }

        return $details;
    }
}

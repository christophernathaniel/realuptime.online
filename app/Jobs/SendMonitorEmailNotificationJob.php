<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Models\Monitor;
use App\Models\NotificationContact;
use App\Models\NotificationLog;
use App\Notifications\MonitorAlertNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use Throwable;

class SendMonitorEmailNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public int $notificationLogId,
        public int $monitorId,
        public int $notificationContactId,
        public string $type,
        public ?int $incidentId = null,
    ) {
        $this->onQueue(config('realuptime.queues.notifications'));
    }

    public function handle(): void
    {
        $log = NotificationLog::query()->find($this->notificationLogId);
        $monitor = Monitor::query()->find($this->monitorId);
        $contact = NotificationContact::query()->find($this->notificationContactId);
        $incident = $this->incidentId ? Incident::query()->find($this->incidentId) : null;

        if (! $log) {
            return;
        }

        if (! $monitor || ! $contact) {
            $log->forceFill([
                'status' => 'failed',
                'failure_message' => 'Notification target no longer exists.',
            ])->save();

            return;
        }

        try {
            Notification::route('mail', $contact->email)
                ->notify(new MonitorAlertNotification($monitor, $this->type, $incident));

            $log->forceFill([
                'status' => 'sent',
                'sent_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $log->forceFill([
                'status' => 'failed',
                'failure_message' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }
}

<?php

namespace App\Jobs;

use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use App\Models\NotificationContact;
use App\Models\NotificationLog;
use App\Notifications\MaintenanceWindowNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use Throwable;

class SendMaintenanceEmailNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public int $notificationLogId,
        public int $monitorId,
        public int $notificationContactId,
        public int $maintenanceWindowId,
    ) {
        $this->onQueue(config('realuptime.queues.notifications'));
    }

    public function handle(): void
    {
        $log = NotificationLog::query()->find($this->notificationLogId);
        $monitor = Monitor::query()->find($this->monitorId);
        $contact = NotificationContact::query()->find($this->notificationContactId);
        $window = MaintenanceWindow::query()->with('monitors')->find($this->maintenanceWindowId);

        if (! $log) {
            return;
        }

        if (! $monitor || ! $contact || ! $window) {
            $log->forceFill([
                'status' => 'failed',
                'failure_message' => 'Maintenance notification target no longer exists.',
            ])->save();

            return;
        }

        try {
            Notification::route('mail', $contact->email)
                ->notify(new MaintenanceWindowNotification($window));

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

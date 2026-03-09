<?php

namespace App\Services\Monitoring;

use App\Jobs\SendMaintenanceEmailNotificationJob;
use App\Jobs\SendMonitorEmailNotificationJob;
use App\Models\Incident;
use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use App\Models\NotificationContact;
use App\Models\NotificationLog;
use Illuminate\Support\Collection;

class EmailNotificationService
{
    public function sendTest(Monitor $monitor): void
    {
        $this->dispatch($monitor, 'test');
    }

    public function sendDownAlert(Monitor $monitor, Incident $incident): void
    {
        $this->dispatch($monitor, 'down', $incident);
    }

    public function sendRecoveryAlert(Monitor $monitor, Incident $incident): void
    {
        $this->dispatch($monitor, 'recovered', $incident);
    }

    public function sendCriticalAlert(Monitor $monitor, Incident $incident): void
    {
        $this->dispatch($monitor, 'critical', $incident);
    }

    public function sendDegradedAlert(Monitor $monitor, Incident $incident): void
    {
        $this->dispatch($monitor, 'degraded', $incident);
    }

    public function sendExpiryAlert(Monitor $monitor, Incident $incident): void
    {
        $type = $incident->type === Incident::TYPE_DOMAIN_EXPIRY ? 'domain_expiry' : 'ssl_expiry';

        $this->dispatch($monitor, $type, $incident);
    }

    public function sendResolutionAlert(Monitor $monitor, Incident $incident): void
    {
        $this->dispatch($monitor, 'resolved', $incident);
    }

    public function sendMaintenanceScheduled(MaintenanceWindow $window): void
    {
        $window->loadMissing([
            'user.notificationContacts',
            'monitors.notificationContacts',
        ]);

        /** @var Collection<int, NotificationContact> $contacts */
        $contacts = $window->monitors
            ->flatMap(fn (Monitor $monitor) => $monitor->notificationContacts->where('enabled', true))
            ->unique('id')
            ->values();

        if ($contacts->isEmpty()) {
            $contacts = $window->user?->notificationContacts
                ? $window->user->notificationContacts->where('enabled', true)->values()
                : collect();
        }

        if ($contacts->isEmpty() && $window->user?->email) {
            $contacts = collect([
                $window->user->notificationContacts()->firstOrCreate(
                    ['email' => $window->user->email],
                    ['name' => $window->user->name, 'enabled' => true, 'is_primary' => true],
                ),
            ]);
        }

        $primaryMonitor = $window->monitors->first();

        if (! $primaryMonitor) {
            return;
        }

        foreach ($contacts as $contact) {
            $this->sendMaintenanceToContact($primaryMonitor, $window, $contact);
        }
    }

    protected function dispatch(Monitor $monitor, string $type, ?Incident $incident = null): void
    {
        $contacts = $monitor->notificationContacts()->where('enabled', true)->get();

        if ($contacts->isEmpty() && $monitor->user?->email) {
            $contacts = collect([
                $monitor->user->notificationContacts()->firstOrCreate(
                    ['email' => $monitor->user->email],
                    ['name' => $monitor->user->name, 'enabled' => true, 'is_primary' => true],
                ),
            ]);
        }

        foreach ($contacts as $contact) {
            $this->sendToContact($monitor, $contact, $type, $incident);
        }
    }

    protected function sendToContact(Monitor $monitor, NotificationContact $contact, string $type, ?Incident $incident = null): void
    {
        $subject = match ($type) {
            'down' => sprintf('%s is down', $monitor->name),
            'critical' => sprintf('Critical outage: %s', $monitor->name),
            'degraded' => sprintf('Performance degraded: %s', $monitor->name),
            'ssl_expiry' => sprintf('SSL certificate alert: %s', $monitor->name),
            'domain_expiry' => sprintf('Domain expiry alert: %s', $monitor->name),
            'recovered' => sprintf('%s is back up', $monitor->name),
            'resolved' => sprintf('Alert resolved: %s', $monitor->name),
            default => sprintf('Test notification for %s', $monitor->name),
        };

        $log = NotificationLog::query()->create([
            'monitor_id' => $monitor->id,
            'incident_id' => $incident?->id,
            'notification_contact_id' => $contact->id,
            'channel' => 'email',
            'type' => $type,
            'subject' => $subject,
            'status' => 'pending',
            'payload' => [
                'email' => $contact->email,
                'name' => $contact->name,
            ],
        ]);

        SendMonitorEmailNotificationJob::dispatch(
            notificationLogId: $log->id,
            monitorId: $monitor->id,
            notificationContactId: $contact->id,
            type: $type,
            incidentId: $incident?->id,
        )->afterCommit();
    }

    protected function sendMaintenanceToContact(Monitor $monitor, MaintenanceWindow $window, NotificationContact $contact): void
    {
        $subject = sprintf('Scheduled maintenance: %s', $window->title);

        $log = NotificationLog::query()->create([
            'monitor_id' => $monitor->id,
            'notification_contact_id' => $contact->id,
            'channel' => 'email',
            'type' => 'maintenance',
            'subject' => $subject,
            'status' => 'pending',
            'payload' => [
                'email' => $contact->email,
                'name' => $contact->name,
                'title' => $window->title,
                'monitor_names' => $window->monitors->pluck('name')->values()->all(),
                'starts_at' => $window->starts_at?->toIso8601String(),
                'ends_at' => $window->ends_at?->toIso8601String(),
            ],
        ]);

        SendMaintenanceEmailNotificationJob::dispatch(
            notificationLogId: $log->id,
            monitorId: $monitor->id,
            notificationContactId: $contact->id,
            maintenanceWindowId: $window->id,
        )->afterCommit();
    }
}

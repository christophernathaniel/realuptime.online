<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Models\WorkspaceIntegration;
use App\Services\Monitoring\Integrations\WorkspaceIntegrationProviderManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendWorkspaceIntegrationNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $notificationLogId,
        public int $integrationId,
        public string $event,
        public array $payload,
    ) {
        $this->onQueue(config('realuptime.queues.notifications'));
    }

    public function handle(WorkspaceIntegrationProviderManager $providers): void
    {
        $log = NotificationLog::query()->find($this->notificationLogId);
        $integration = WorkspaceIntegration::query()->find($this->integrationId);

        if (! $log) {
            return;
        }

        if (! $integration) {
            $log->forceFill([
                'status' => 'failed',
                'failure_message' => 'Integration no longer exists.',
            ])->save();

            return;
        }

        try {
            $provider = $providers->for($integration);
            $metadata = $provider->send($integration, $this->event, $this->payload);

            $integration->forceFill([
                'last_tested_at' => now(),
                'last_error_at' => null,
                'last_error_message' => null,
            ])->save();

            $log->forceFill([
                'status' => 'sent',
                'sent_at' => now(),
                'payload' => [
                    ...($log->payload ?? []),
                    ...$metadata,
                ],
            ])->save();
        } catch (Throwable $exception) {
            $integration->forceFill([
                'last_error_at' => now(),
                'last_error_message' => $exception->getMessage(),
            ])->save();

            $log->forceFill([
                'status' => 'failed',
                'failure_message' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }
}

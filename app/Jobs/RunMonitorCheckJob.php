<?php

namespace App\Jobs;

use App\Models\Monitor;
use App\Services\Monitoring\MonitorRunner;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RunMonitorCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public int $monitorId,
        public ?string $checkedAtIso = null,
    ) {
        $this->onQueue(config('realuptime.queues.monitor_checks'));
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("monitor-check:{$this->monitorId}"))->expireAfter(max(180, (int) config('realuptime.dispatch.claim_ttl_seconds', 600))),
        ];
    }

    public function handle(MonitorRunner $runner): void
    {
        $monitor = Monitor::query()
            ->with(['notificationContacts', 'user'])
            ->find($this->monitorId);

        if (! $monitor) {
            return;
        }

        if ($monitor->status === Monitor::STATUS_PAUSED) {
            $monitor->forceFill([
                'check_claimed_at' => null,
                'check_claim_token' => null,
            ])->save();

            return;
        }

        $checkedAt = $this->checkedAtIso
            ? CarbonImmutable::parse($this->checkedAtIso)
            : CarbonImmutable::now();

        $runner->runMonitor($monitor, $checkedAt);
    }

    public function failed(?Throwable $exception = null): void
    {
        Monitor::query()
            ->whereKey($this->monitorId)
            ->update([
                'check_claimed_at' => null,
                'check_claim_token' => null,
            ]);
    }
}

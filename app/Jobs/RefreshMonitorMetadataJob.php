<?php

namespace App\Jobs;

use App\Models\Monitor;
use App\Services\Monitoring\MonitorMetadataRefresher;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class RefreshMonitorMetadataJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public int $uniqueFor = 900;

    public function __construct(
        public int $monitorId,
        public string $checkedAtIso,
    ) {
        $this->onQueue(config('realuptime.queues.monitor_metadata', config('realuptime.queues.monitor_checks')));
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("monitor-metadata:{$this->monitorId}"))->expireAfter(900),
        ];
    }

    public function uniqueId(): string
    {
        return (string) $this->monitorId;
    }

    public function handle(MonitorMetadataRefresher $refresher): void
    {
        $monitor = Monitor::query()->find($this->monitorId);

        if (! $monitor) {
            return;
        }

        $refresher->refresh($monitor, CarbonImmutable::parse($this->checkedAtIso));
    }
}

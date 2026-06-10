<?php

namespace App\Jobs;

use App\Models\Monitor;
use App\Models\ProbeConfirmation;
use App\Services\Monitoring\MonitorRunner;
use App\Services\Monitoring\ProbeConfirmationService;
use App\Support\MonitorQueueResolver;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RunMonitorRecoveryConfirmationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public int $confirmationId,
        public int $monitorId,
        public string $probeRegion,
        public ?string $checkedAtIso = null,
    ) {
        $this->onQueue(MonitorQueueResolver::monitorCheckQueue($this->monitorId, Monitor::TYPE_HTTP, $this->probeRegion));
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("monitor-recovery-confirmation:{$this->confirmationId}:{$this->probeRegion}"))->expireAfter(180),
        ];
    }

    public function handle(MonitorRunner $runner, ProbeConfirmationService $confirmations): void
    {
        $confirmation = ProbeConfirmation::query()->find($this->confirmationId);

        if (! $confirmation || $confirmation->status !== ProbeConfirmation::STATUS_PENDING) {
            return;
        }

        $monitor = Monitor::query()
            ->with(['notificationContacts', 'user'])
            ->find($this->monitorId);

        if (! $monitor || $monitor->status === Monitor::STATUS_PAUSED) {
            return;
        }

        $checkedAt = $this->checkedAtIso
            ? CarbonImmutable::parse($this->checkedAtIso)
            : CarbonImmutable::now();

        $report = $runner->probeMonitor(
            $monitor,
            checkedAt: $checkedAt,
            attemptsOverride: 1,
            queueLagMs: 0,
            probeRegion: $this->probeRegion,
            queueMetadataRefresh: false,
        );

        $confirmations->recordRecoveryResult($confirmation->fresh(), $monitor->fresh(['notificationContacts', 'user']), $this->probeRegion, $report);
    }

    public function failed(?Throwable $exception = null): void
    {
        ProbeConfirmation::query()
            ->whereKey($this->confirmationId)
            ->where('status', ProbeConfirmation::STATUS_PENDING)
            ->update([
                'completed_at' => now(),
                'status' => ProbeConfirmation::STATUS_REJECTED,
            ]);
    }
}

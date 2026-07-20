<?php

namespace App\Services\Monitoring;

use App\Jobs\RunMonitorCheckJob;
use App\Models\Monitor;
use App\Support\MonitorQueueResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MonitorDispatchService
{
    /**
     * @return Collection<int, Monitor>
     */
    public function claimDueMonitors(int $batchSize = 200, ?CarbonImmutable $now = null): Collection
    {
        $now ??= CarbonImmutable::now();
        $staleBefore = $now->subSeconds(max(60, (int) config('realuptime.dispatch.claim_ttl_seconds', 600)));
        $dispatchCutoff = $now->subSeconds(max(60, (int) config('realuptime.dispatch.minimum_interval_seconds', 60)));

        $candidateIds = Monitor::query()
            ->whereIn('status', [Monitor::STATUS_UP, Monitor::STATUS_DOWN])
            ->where(function ($query) use ($now): void {
                $query->whereNull('next_check_at')
                    ->orWhere('next_check_at', '<=', $now);
            })
            ->where(function ($query) use ($staleBefore): void {
                $query->whereNull('check_claimed_at')
                    ->orWhere('check_claimed_at', '<=', $staleBefore);
            })
            ->where(function ($query) use ($dispatchCutoff): void {
                $query->whereNull('last_dispatched_at')
                    ->orWhere('last_dispatched_at', '<=', $dispatchCutoff);
            })
            ->orderBy('next_check_at')
            ->orderBy('id')
            ->limit($batchSize)
            ->pluck('id');

        if ($candidateIds->isEmpty()) {
            return collect();
        }

        $claimToken = Str::uuid()->toString();

        Monitor::query()
            ->whereIn('id', $candidateIds)
            ->whereIn('status', [Monitor::STATUS_UP, Monitor::STATUS_DOWN])
            ->where(function ($query) use ($now): void {
                $query->whereNull('next_check_at')
                    ->orWhere('next_check_at', '<=', $now);
            })
            ->where(function ($query) use ($staleBefore): void {
                $query->whereNull('check_claimed_at')
                    ->orWhere('check_claimed_at', '<=', $staleBefore);
            })
            ->where(function ($query) use ($dispatchCutoff): void {
                $query->whereNull('last_dispatched_at')
                    ->orWhere('last_dispatched_at', '<=', $dispatchCutoff);
            })
            ->update([
                'check_claimed_at' => $now,
                'check_claim_token' => $claimToken,
                'last_dispatched_at' => $now,
            ]);

        return Monitor::query()
            ->where('check_claim_token', $claimToken)
            ->select(['id', 'type', 'region', 'next_check_at', 'check_claim_token'])
            ->orderBy('next_check_at')
            ->orderBy('id')
            ->get();
    }

    public function dispatchNow(Monitor $monitor, ?CarbonImmutable $now = null): bool
    {
        $now ??= CarbonImmutable::now();
        $staleBefore = $now->subSeconds(max(60, (int) config('realuptime.dispatch.claim_ttl_seconds', 600)));
        $monitor->loadMissing('user.subscriptions.items');
        $minimumInterval = $monitor->user?->minimumMonitorIntervalSeconds()
            ?? max(60, (int) config('realuptime.dispatch.minimum_interval_seconds', 60));
        $dispatchCutoff = $now->subSeconds($minimumInterval);
        $claimToken = Str::uuid()->toString();

        $claimed = Monitor::query()
            ->whereKey($monitor->id)
            ->whereIn('status', [Monitor::STATUS_UP, Monitor::STATUS_DOWN])
            ->where(function ($query) use ($staleBefore): void {
                $query->whereNull('check_claimed_at')
                    ->orWhere('check_claimed_at', '<=', $staleBefore);
            })
            ->where(function ($query) use ($dispatchCutoff): void {
                $query->whereNull('last_dispatched_at')
                    ->orWhere('last_dispatched_at', '<=', $dispatchCutoff);
            })
            ->update([
                'check_claimed_at' => $now,
                'check_claim_token' => $claimToken,
                'last_dispatched_at' => $now,
            ]);

        if ($claimed !== 1) {
            return false;
        }

        RunMonitorCheckJob::dispatch(
            $monitor->id,
            $now->toIso8601String(),
            $monitor->type,
            MonitorQueueResolver::usesRegionQueues() ? $monitor->region : null,
            $claimToken,
        );

        return true;
    }

    public function dispatchDueMonitors(int $batchSize = 200, int $maxBatches = 10, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $total = 0;
        $batches = 0;

        while ($batches < $maxBatches) {
            $claimed = $this->claimDueMonitors($batchSize, $now);

            if ($claimed->isEmpty()) {
                break;
            }

            foreach ($claimed as $monitor) {
                RunMonitorCheckJob::dispatch(
                    $monitor->id,
                    $now->toIso8601String(),
                    $monitor->type,
                    MonitorQueueResolver::usesRegionQueues() ? $monitor->region : null,
                    $monitor->check_claim_token,
                );
            }

            $total += $claimed->count();
            $batches++;

            if ($claimed->count() < $batchSize) {
                break;
            }
        }

        return [
            'dispatched' => $total,
            'batches' => $batches,
        ];
    }
}

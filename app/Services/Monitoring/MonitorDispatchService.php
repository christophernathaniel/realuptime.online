<?php

namespace App\Services\Monitoring;

use App\Jobs\RunMonitorCheckJob;
use App\Models\Monitor;
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

        $candidateIds = Monitor::query()
            ->where('status', '!=', Monitor::STATUS_PAUSED)
            ->where(function ($query) use ($now): void {
                $query->whereNull('next_check_at')
                    ->orWhere('next_check_at', '<=', $now);
            })
            ->where(function ($query) use ($staleBefore): void {
                $query->whereNull('check_claimed_at')
                    ->orWhere('check_claimed_at', '<=', $staleBefore);
            })
            ->orderByRaw('coalesce(next_check_at, created_at)')
            ->limit($batchSize)
            ->pluck('id');

        if ($candidateIds->isEmpty()) {
            return collect();
        }

        $claimToken = Str::uuid()->toString();

        Monitor::query()
            ->whereIn('id', $candidateIds)
            ->where('status', '!=', Monitor::STATUS_PAUSED)
            ->where(function ($query) use ($now): void {
                $query->whereNull('next_check_at')
                    ->orWhere('next_check_at', '<=', $now);
            })
            ->where(function ($query) use ($staleBefore): void {
                $query->whereNull('check_claimed_at')
                    ->orWhere('check_claimed_at', '<=', $staleBefore);
            })
            ->update([
                'check_claimed_at' => $now,
                'check_claim_token' => $claimToken,
                'last_dispatched_at' => $now,
            ]);

        return Monitor::query()
            ->where('check_claim_token', $claimToken)
            ->orderByRaw('coalesce(next_check_at, created_at)')
            ->get();
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
                RunMonitorCheckJob::dispatch($monitor->id, $now->toIso8601String());
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

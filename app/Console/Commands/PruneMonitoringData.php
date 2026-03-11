<?php

namespace App\Console\Commands;

use App\Models\CheckResult;
use App\Models\Incident;
use App\Models\NotificationLog;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class PruneMonitoringData extends Command
{
    protected $signature = 'realuptime:prune-monitoring-data';

    protected $description = 'Prune old notification logs and disposable healthy check results';

    public function handle(): int
    {
        $notificationLogRetentionDays = max(1, (int) config('realuptime.retention.notification_logs_days', 30));
        $healthyCheckRetentionDays = max(1, (int) config('realuptime.retention.healthy_check_results_days', 30));
        $chunkSize = max(100, (int) config('realuptime.retention.prune_chunk_size', 1000));

        $notificationLogCutoff = CarbonImmutable::now()->subDays($notificationLogRetentionDays);
        $healthyCheckCutoff = CarbonImmutable::now()->subDays($healthyCheckRetentionDays);

        $deletedLogs = 0;

        NotificationLog::query()
            ->select('id')
            ->where('created_at', '<', $notificationLogCutoff)
            ->orderBy('id')
            ->chunkById($chunkSize, function (Collection $logs) use (&$deletedLogs): void {
                $logIds = $logs->pluck('id')->all();

                if ($logIds === []) {
                    return;
                }

                $deletedLogs += NotificationLog::query()
                    ->whereKey($logIds)
                    ->delete();
            });

        $deletedHealthyResults = 0;

        CheckResult::query()
            ->select(['id', 'meta'])
            ->where('status', 'up')
            ->where('checked_at', '<', $healthyCheckCutoff)
            ->orderBy('id')
            ->chunkById($chunkSize, function (Collection $results) use (&$deletedHealthyResults): void {
                $protectedIds = $this->incidentProtectedCheckResultIds($results->pluck('id')->all());

                $deletableIds = $results
                    ->reject(fn (CheckResult $result) => $protectedIds->contains($result->id) || (bool) data_get($result->meta, 'slow', false))
                    ->pluck('id')
                    ->all();

                if ($deletableIds === []) {
                    return;
                }

                $deletedHealthyResults += CheckResult::query()
                    ->whereKey($deletableIds)
                    ->delete();
            });

        $this->info(sprintf(
            'Deleted %d notification logs and %d healthy check results.',
            $deletedLogs,
            $deletedHealthyResults,
        ));

        return self::SUCCESS;
    }

    /**
     * @param  array<int, int>  $candidateIds
     * @return Collection<int, int>
     */
    protected function incidentProtectedCheckResultIds(array $candidateIds): Collection
    {
        if ($candidateIds === []) {
            return collect();
        }

        return Incident::query()
            ->select([
                'first_check_result_id',
                'last_good_check_result_id',
                'latest_check_result_id',
            ])
            ->where(function ($query) use ($candidateIds): void {
                $query->whereIn('first_check_result_id', $candidateIds)
                    ->orWhereIn('last_good_check_result_id', $candidateIds)
                    ->orWhereIn('latest_check_result_id', $candidateIds);
            })
            ->get()
            ->flatMap(fn (Incident $incident) => [
                $incident->first_check_result_id,
                $incident->last_good_check_result_id,
                $incident->latest_check_result_id,
            ])
            ->filter()
            ->map(fn (mixed $id) => (int) $id)
            ->unique()
            ->values();
    }
}

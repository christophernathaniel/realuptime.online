<?php

namespace App\Services\Monitoring;

use App\Models\NotificationLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class MonitoringDataPruner
{
    public function __construct(
        protected CheckResultRollupCompactor $compactor,
    ) {}

    /**
     * @return array{
     *     notification_logs_deleted: int,
     *     raw_results_rolled: int,
     *     fine_rollups_written: int,
     *     fine_rollups_compacted: int,
     *     daily_rollups_written: int,
     *     expired_raw_results_deleted: int,
     *     expired_rollups_deleted: int
     * }
     */
    public function prune(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now()->startOfSecond();
        $notificationLogRetentionDays = max(1, (int) config('realuptime.retention.notification_logs_days', 30));
        $chunkSize = max(100, (int) config('realuptime.retention.prune_chunk_size', 1000));
        $notificationLogCutoff = $now->subDays($notificationLogRetentionDays);
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

        return [
            'notification_logs_deleted' => $deletedLogs,
            ...$this->compactor->compact($now),
        ];
    }
}

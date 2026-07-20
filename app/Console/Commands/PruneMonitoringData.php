<?php

namespace App\Console\Commands;

use App\Services\Monitoring\MonitoringDataPruner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class PruneMonitoringData extends Command
{
    protected $signature = 'realuptime:prune-monitoring-data';

    protected $description = 'Roll up historical checks and prune expired monitoring data';

    public function handle(MonitoringDataPruner $pruner): int
    {
        $startedAt = microtime(true);
        Log::info('Monitoring data retention started.');

        try {
            $result = $pruner->prune();
        } catch (Throwable $exception) {
            Log::error('Monitoring data retention failed.', [
                'exception' => $exception,
            ]);

            throw $exception;
        }

        $result['duration_ms'] = (int) round((microtime(true) - $startedAt) * 1000);

        Log::info('Monitoring data retention completed.', $result);

        $this->info(sprintf(
            'Deleted %d notification logs. Compacted %d check results into %d 15-minute buckets and %d 15-minute buckets into %d daily buckets. Deleted %d expired check results and %d expired rollups.',
            $result['notification_logs_deleted'],
            $result['raw_results_rolled'],
            $result['fine_rollups_written'],
            $result['fine_rollups_compacted'],
            $result['daily_rollups_written'],
            $result['expired_raw_results_deleted'],
            $result['expired_rollups_deleted'],
        ));

        return self::SUCCESS;
    }
}

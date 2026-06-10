<?php

namespace App\Console\Commands;

use App\Services\Monitoring\MonitorDispatchService;
use Illuminate\Console\Command;

class DispatchMonitorLoop extends Command
{
    protected $signature = 'monitors:dispatch-loop {--batch=} {--max-batches=} {--sleep-ms=1000} {--once}';

    protected $description = 'Continuously dispatch due monitors without waiting for the scheduler tick';

    public function handle(MonitorDispatchService $dispatcher): int
    {
        $batchSize = (int) ($this->option('batch') ?: config('realuptime.dispatch.batch_size', 250));
        $maxBatches = (int) ($this->option('max-batches') ?: config('realuptime.dispatch.max_batches', 12));
        $sleepMs = max(100, (int) ($this->option('sleep-ms') ?: 1000));
        $runOnce = (bool) $this->option('once');

        do {
            $result = $dispatcher->dispatchDueMonitors(
                batchSize: $batchSize,
                maxBatches: $maxBatches,
            );

            if ($runOnce || $result['dispatched'] > 0 || $this->output->isVerbose()) {
                $this->line(sprintf(
                    'Dispatched %d due monitor(s) across %d batch(es).',
                    $result['dispatched'],
                    $result['batches'],
                ));
            }

            if ($runOnce) {
                break;
            }

            usleep($sleepMs * 1000);
        } while (true);

        return self::SUCCESS;
    }
}

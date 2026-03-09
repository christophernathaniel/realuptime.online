<?php

namespace App\Console\Commands;

use App\Services\Monitoring\MonitorDispatchService;
use Illuminate\Console\Command;

class RunDueMonitors extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitors:run-due {--batch=} {--max-batches=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run all monitors that are due for a check';

    /**
     * Execute the console command.
     */
    public function handle(MonitorDispatchService $dispatcher): int
    {
        $result = $dispatcher->dispatchDueMonitors(
            batchSize: (int) ($this->option('batch') ?: config('realuptime.dispatch.batch_size', 250)),
            maxBatches: (int) ($this->option('max-batches') ?: config('realuptime.dispatch.max_batches', 12)),
        );

        $this->info(sprintf(
            'Dispatched %d due monitor(s) across %d batch(es).',
            $result['dispatched'],
            $result['batches'],
        ));

        return self::SUCCESS;
    }
}

<?php

namespace App\Support;

class MonitorQueueResolver
{
    /**
     * @return array<int, string>
     */
    public static function monitorCheckQueues(): array
    {
        $queues = config('realuptime.queues.monitor_check_shards', []);

        if (! is_array($queues) || $queues === []) {
            return [(string) config('realuptime.queues.monitor_checks', 'monitor-checks')];
        }

        return collect($queues)
            ->filter(fn (mixed $queue) => is_string($queue) && trim($queue) !== '')
            ->map(fn (string $queue) => trim($queue))
            ->values()
            ->all();
    }

    public static function monitorCheckQueue(?int $monitorId = null): string
    {
        $queues = self::monitorCheckQueues();

        if (count($queues) === 1 || $monitorId === null) {
            return $queues[0];
        }

        return $queues[abs($monitorId) % count($queues)];
    }
}

<?php

namespace App\Support;

use App\Models\Monitor;

class MonitorQueueResolver
{
    public static function usesRegionQueues(): bool
    {
        return (bool) config('realuptime.probe_regions.use_region_queues', false);
    }

    /**
     * @return array<int, string>
     */
    public static function monitorCheckQueues(?string $monitorType = null, ?string $region = null): array
    {
        $group = self::queueGroupForType($monitorType);
        $queues = match ($group) {
            'http' => config('realuptime.queues.http_monitor_check_shards') ?: config('realuptime.queues.monitor_check_shards', []),
            'network' => config('realuptime.queues.network_monitor_check_shards') ?: config('realuptime.queues.monitor_check_shards', []),
            'synthetic' => config('realuptime.queues.synthetic_monitor_check_shards') ?: config('realuptime.queues.monitor_check_shards', []),
            default => config('realuptime.queues.monitor_check_shards', []),
        };

        if (! is_array($queues) || $queues === []) {
            $resolved = [match ($group) {
                'http' => (string) config('realuptime.queues.http_monitor_checks', config('realuptime.queues.monitor_checks', 'monitor-checks')),
                'network' => (string) config('realuptime.queues.network_monitor_checks', config('realuptime.queues.monitor_checks', 'monitor-checks')),
                'synthetic' => (string) config('realuptime.queues.synthetic_monitor_checks', config('realuptime.queues.monitor_checks', 'monitor-checks')),
                default => (string) config('realuptime.queues.monitor_checks', 'monitor-checks'),
            }];
        } else {
            $resolved = collect($queues)
                ->filter(fn (mixed $queue) => is_string($queue) && trim($queue) !== '')
                ->map(fn (string $queue) => trim($queue))
                ->values()
                ->all();
        }

        if ($region === null || ! self::usesRegionQueues()) {
            return $resolved;
        }

        $token = self::regionQueueToken($region);

        if ($token === null) {
            return $resolved;
        }

        return array_map(
            static fn (string $queue): string => sprintf('%s-%s', $queue, $token),
            $resolved,
        );
    }

    public static function monitorCheckQueue(?int $monitorId = null, ?string $monitorType = null, ?string $region = null): string
    {
        $queues = self::monitorCheckQueues($monitorType, $region);

        if (count($queues) === 1 || $monitorId === null) {
            return $queues[0];
        }

        return $queues[abs($monitorId) % count($queues)];
    }

    public static function queueGroupForType(?string $monitorType): string
    {
        return match ($monitorType) {
            Monitor::TYPE_HTTP, Monitor::TYPE_KEYWORD, Monitor::TYPE_SSL => 'http',
            Monitor::TYPE_PING, Monitor::TYPE_PORT, Monitor::TYPE_HEARTBEAT => 'network',
            Monitor::TYPE_SYNTHETIC => 'synthetic',
            default => 'default',
        };
    }

    public static function regionQueueToken(string $region): ?string
    {
        $configured = config('realuptime.probe_regions.regions', []);

        if (! is_array($configured)) {
            return null;
        }

        $regionConfig = $configured[$region] ?? null;

        if (! is_array($regionConfig)) {
            return null;
        }

        $token = trim((string) ($regionConfig['queue_token'] ?? ''));

        return $token !== '' ? $token : null;
    }
}

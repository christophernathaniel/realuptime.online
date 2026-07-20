<?php

namespace App\Services\Monitoring;

use App\Models\CheckResult;
use App\Models\CheckResultRollup;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CheckResultRollupCompactor
{
    /**
     * @return array{
     *     raw_results_rolled: int,
     *     fine_rollups_written: int,
     *     fine_rollups_compacted: int,
     *     daily_rollups_written: int,
     *     expired_raw_results_deleted: int,
     *     expired_rollups_deleted: int
     * }
     */
    public function compact(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now()->startOfSecond();
        $rawDays = max(1, (int) config('realuptime.retention.raw_check_results_days', 30));
        $fineDays = max($rawDays + 1, (int) config('realuptime.retention.fine_rollup_days', 180));
        $historyDays = max($fineDays + 1, (int) config('realuptime.retention.check_history_days', 730));
        $chunkSize = max(100, (int) config('realuptime.retention.prune_chunk_size', 1000));

        $historyCutoff = $now->subDays($historyDays)->startOfDay();
        $rawCutoff = $this->floorToInterval($now->subDays($rawDays), CheckResultRollup::GRANULARITY_FIFTEEN_MINUTES);
        $fineCutoff = $now->subDays($fineDays)->startOfDay();

        $expiredRawResultsDeleted = $this->deleteInChunks(
            CheckResult::query()->where('checked_at', '<', $historyCutoff),
            $chunkSize,
        );

        $raw = $this->rollRawResults($historyCutoff, $rawCutoff);
        $daily = $this->rollFineBuckets($historyCutoff, $fineCutoff);

        $expiredRollupsDeleted = $this->deleteInChunks(
            CheckResultRollup::query()->where('bucket_ended_at', '<=', $historyCutoff),
            $chunkSize,
        );

        return [
            'raw_results_rolled' => $raw['sources'],
            'fine_rollups_written' => $raw['targets'],
            'fine_rollups_compacted' => $daily['sources'],
            'daily_rollups_written' => $daily['targets'],
            'expired_raw_results_deleted' => $expiredRawResultsDeleted,
            'expired_rollups_deleted' => $expiredRollupsDeleted,
        ];
    }

    /**
     * @return array{sources: int, targets: int}
     */
    protected function rollRawResults(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $sourceCount = 0;
        $targetCount = 0;

        while ($oldest = CheckResult::query()
            ->where('checked_at', '>=', $from)
            ->where('checked_at', '<', $to)
            ->min('checked_at')) {
            $windowStart = $this->floorToInterval(
                CarbonImmutable::parse($oldest),
                CheckResultRollup::GRANULARITY_FIFTEEN_MINUTES,
            )->max($from);
            $windowEnd = $windowStart
                ->addSeconds(CheckResultRollup::GRANULARITY_FIFTEEN_MINUTES)
                ->min($to);

            [$sources, $targets] = DB::transaction(function () use ($windowStart, $windowEnd): array {
                $maxSourceId = CheckResult::query()
                    ->where('checked_at', '>=', $windowStart)
                    ->where('checked_at', '<', $windowEnd)
                    ->max('id');

                if ($maxSourceId === null) {
                    return [0, 0];
                }

                $rows = $this->rawAggregates($windowStart, $windowEnd, (int) $maxSourceId);
                $this->mergeRollups($rows, CheckResultRollup::GRANULARITY_FIFTEEN_MINUTES);

                $deleted = CheckResult::query()
                    ->where('checked_at', '>=', $windowStart)
                    ->where('checked_at', '<', $windowEnd)
                    ->where('id', '<=', $maxSourceId)
                    ->delete();

                return [$deleted, $rows->count()];
            }, 3);

            $sourceCount += $sources;
            $targetCount += $targets;
        }

        return ['sources' => $sourceCount, 'targets' => $targetCount];
    }

    /**
     * @return array{sources: int, targets: int}
     */
    protected function rollFineBuckets(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $sourceCount = 0;
        $targetCount = 0;

        while ($oldest = CheckResultRollup::query()
            ->where('granularity_seconds', CheckResultRollup::GRANULARITY_FIFTEEN_MINUTES)
            ->where('bucket_started_at', '>=', $from)
            ->where('bucket_started_at', '<', $to)
            ->min('bucket_started_at')) {
            $windowStart = CarbonImmutable::parse($oldest)->startOfDay();
            $windowEnd = $windowStart->addDay()->min($to);

            [$sources, $targets] = DB::transaction(function () use ($windowStart, $windowEnd): array {
                $maxSourceId = CheckResultRollup::query()
                    ->where('granularity_seconds', CheckResultRollup::GRANULARITY_FIFTEEN_MINUTES)
                    ->where('bucket_started_at', '>=', $windowStart)
                    ->where('bucket_started_at', '<', $windowEnd)
                    ->max('id');

                if ($maxSourceId === null) {
                    return [0, 0];
                }

                $rows = $this->fineAggregates($windowStart, $windowEnd, (int) $maxSourceId);
                $this->mergeRollups($rows, CheckResultRollup::GRANULARITY_DAY);

                $deleted = CheckResultRollup::query()
                    ->where('granularity_seconds', CheckResultRollup::GRANULARITY_FIFTEEN_MINUTES)
                    ->where('bucket_started_at', '>=', $windowStart)
                    ->where('bucket_started_at', '<', $windowEnd)
                    ->where('id', '<=', $maxSourceId)
                    ->delete();

                return [$deleted, $rows->count()];
            }, 3);

            $sourceCount += $sources;
            $targetCount += $targets;
        }

        return ['sources' => $sourceCount, 'targets' => $targetCount];
    }

    /**
     * @return Collection<int, object>
     */
    protected function rawAggregates(CarbonImmutable $from, CarbonImmutable $to, int $maxSourceId): Collection
    {
        $bucketExpression = 'FLOOR(('.$this->epochExpression('checked_at').') / 900) * 900';
        $slowExpression = $this->slowCheckSqlExpression();

        return CheckResult::query()
            ->where('checked_at', '>=', $from)
            ->where('checked_at', '<', $to)
            ->where('id', '<=', $maxSourceId)
            ->selectRaw("monitor_id, {$bucketExpression} as bucket_timestamp")
            ->selectRaw(
                "COUNT(*) as total_checks,
                SUM(CASE WHEN status = 'up' THEN 1 ELSE 0 END) as up_checks,
                SUM(CASE WHEN status = 'down' THEN 1 ELSE 0 END) as down_checks,
                SUM(CASE WHEN {$slowExpression} THEN 1 ELSE 0 END) as slow_checks,
                COUNT(response_time_ms) as response_time_samples,
                COALESCE(SUM(response_time_ms), 0) as response_time_sum_ms,
                MIN(response_time_ms) as response_time_min_ms,
                MAX(response_time_ms) as response_time_max_ms,
                MIN(checked_at) as first_checked_at,
                MAX(checked_at) as last_checked_at"
            )
            ->groupBy('monitor_id', 'bucket_timestamp')
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    protected function fineAggregates(CarbonImmutable $from, CarbonImmutable $to, int $maxSourceId): Collection
    {
        return CheckResultRollup::query()
            ->where('granularity_seconds', CheckResultRollup::GRANULARITY_FIFTEEN_MINUTES)
            ->where('bucket_started_at', '>=', $from)
            ->where('bucket_started_at', '<', $to)
            ->where('id', '<=', $maxSourceId)
            ->selectRaw('monitor_id, ? as bucket_timestamp', [$from->timestamp])
            ->selectRaw(
                'SUM(total_checks) as total_checks,
                SUM(up_checks) as up_checks,
                SUM(down_checks) as down_checks,
                SUM(slow_checks) as slow_checks,
                SUM(response_time_samples) as response_time_samples,
                SUM(response_time_sum_ms) as response_time_sum_ms,
                MIN(response_time_min_ms) as response_time_min_ms,
                MAX(response_time_max_ms) as response_time_max_ms,
                MIN(first_checked_at) as first_checked_at,
                MAX(last_checked_at) as last_checked_at'
            )
            ->groupBy('monitor_id')
            ->get();
    }

    /**
     * @param  Collection<int, object>  $aggregates
     */
    protected function mergeRollups(Collection $aggregates, int $granularitySeconds): void
    {
        if ($aggregates->isEmpty()) {
            return;
        }

        $bucketStarts = $aggregates
            ->map(fn (object $row): CarbonImmutable => CarbonImmutable::createFromTimestampUTC((int) $row->bucket_timestamp))
            ->unique(fn (CarbonImmutable $time): int => $time->timestamp)
            ->values();
        $existing = CheckResultRollup::query()
            ->where('granularity_seconds', $granularitySeconds)
            ->whereIn('bucket_started_at', $bucketStarts)
            ->get()
            ->keyBy(fn (CheckResultRollup $rollup): string => $this->rollupKey(
                $rollup->monitor_id,
                $rollup->bucket_started_at->timestamp,
            ));
        $now = now();

        $records = $aggregates->map(function (object $row) use ($existing, $granularitySeconds, $now): array {
            $bucketStart = CarbonImmutable::createFromTimestampUTC((int) $row->bucket_timestamp);
            $current = $existing->get($this->rollupKey((int) $row->monitor_id, $bucketStart->timestamp));

            return [
                'monitor_id' => (int) $row->monitor_id,
                'granularity_seconds' => $granularitySeconds,
                'bucket_started_at' => $bucketStart,
                'bucket_ended_at' => $bucketStart->addSeconds($granularitySeconds),
                'total_checks' => (int) $row->total_checks + (int) ($current?->total_checks ?? 0),
                'up_checks' => (int) $row->up_checks + (int) ($current?->up_checks ?? 0),
                'down_checks' => (int) $row->down_checks + (int) ($current?->down_checks ?? 0),
                'slow_checks' => (int) $row->slow_checks + (int) ($current?->slow_checks ?? 0),
                'response_time_samples' => (int) $row->response_time_samples + (int) ($current?->response_time_samples ?? 0),
                'response_time_sum_ms' => (int) $row->response_time_sum_ms + (int) ($current?->response_time_sum_ms ?? 0),
                'response_time_min_ms' => $this->nullableMin($row->response_time_min_ms, $current?->response_time_min_ms),
                'response_time_max_ms' => $this->nullableMax($row->response_time_max_ms, $current?->response_time_max_ms),
                'first_checked_at' => $this->nullableDateMin($row->first_checked_at, $current?->first_checked_at),
                'last_checked_at' => $this->nullableDateMax($row->last_checked_at, $current?->last_checked_at),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->all();

        foreach (array_chunk($records, 1000) as $chunk) {
            CheckResultRollup::query()->upsert(
                $chunk,
                ['monitor_id', 'granularity_seconds', 'bucket_started_at'],
                [
                    'bucket_ended_at',
                    'total_checks',
                    'up_checks',
                    'down_checks',
                    'slow_checks',
                    'response_time_samples',
                    'response_time_sum_ms',
                    'response_time_min_ms',
                    'response_time_max_ms',
                    'first_checked_at',
                    'last_checked_at',
                    'updated_at',
                ],
            );
        }
    }

    protected function epochExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "CAST(strftime('%s', {$column}) AS INTEGER)",
            'pgsql' => "CAST(EXTRACT(EPOCH FROM {$column}) AS BIGINT)",
            'sqlsrv' => "DATEDIFF_BIG(second, '1970-01-01', {$column})",
            default => "UNIX_TIMESTAMP({$column})",
        };
    }

    protected function slowCheckSqlExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "json_extract(meta, '$.slow') IN (1, '1', 'true')",
            'pgsql' => "(meta->>'slow') IN ('true', '1')",
            'sqlsrv' => "JSON_VALUE(meta, '$.slow') IN ('true', '1')",
            default => "JSON_UNQUOTE(JSON_EXTRACT(meta, '$.slow')) IN ('true', '1')",
        };
    }

    protected function floorToInterval(CarbonImmutable $time, int $seconds): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestampUTC(intdiv($time->timestamp, $seconds) * $seconds);
    }

    protected function rollupKey(int $monitorId, int $bucketTimestamp): string
    {
        return $monitorId.':'.$bucketTimestamp;
    }

    protected function nullableMin(mixed $left, mixed $right): ?int
    {
        $values = collect([$left, $right])->filter(fn ($value): bool => $value !== null);

        return $values->isEmpty() ? null : (int) $values->min();
    }

    protected function nullableMax(mixed $left, mixed $right): ?int
    {
        $values = collect([$left, $right])->filter(fn ($value): bool => $value !== null);

        return $values->isEmpty() ? null : (int) $values->max();
    }

    protected function nullableDateMin(mixed $left, mixed $right): ?CarbonImmutable
    {
        $values = collect([$left, $right])
            ->filter()
            ->map(fn ($value): CarbonImmutable => CarbonImmutable::parse($value));

        return $values->isEmpty() ? null : $values->sortBy(fn (CarbonImmutable $value): int => $value->timestamp)->first();
    }

    protected function nullableDateMax(mixed $left, mixed $right): ?CarbonImmutable
    {
        $values = collect([$left, $right])
            ->filter()
            ->map(fn ($value): CarbonImmutable => CarbonImmutable::parse($value));

        return $values->isEmpty() ? null : $values->sortByDesc(fn (CarbonImmutable $value): int => $value->timestamp)->first();
    }

    protected function deleteInChunks(Builder $query, int $chunkSize): int
    {
        $deleted = 0;

        do {
            $ids = (clone $query)->orderBy('id')->limit($chunkSize)->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted += $query->getModel()::query()->whereKey($ids)->delete();
        } while ($ids->count() === $chunkSize);

        return $deleted;
    }
}

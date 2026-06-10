<?php

namespace App\Services\Monitoring;

use App\Jobs\RunMonitorRecoveryConfirmationJob;
use App\Models\CheckResult;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\ProbeConfirmation;
use App\Support\MonitorQueueResolver;
use Carbon\CarbonImmutable;

class ProbeConfirmationService
{
    public function __construct(protected MonitorRunner $runner) {}

    /**
     * @param  array<string, mixed>  $primaryReport
     */
    public function shouldConfirmRecovery(Monitor $monitor, ?Incident $incident, array $primaryReport): bool
    {
        if (! config('realuptime.confirmations.recovery_enabled', true)) {
            return false;
        }

        if (! $incident || $incident->resolved_at) {
            return false;
        }

        if (($primaryReport['outcome']->status ?? null) !== 'up') {
            return false;
        }

        if (! $this->incidentNeedsRegionalConfirmation($incident, $primaryReport)) {
            return false;
        }

        return $this->confirmationRegionsFor($monitor->region) !== [];
    }

    public function requestRecoveryConfirmation(
        Monitor $monitor,
        Incident $incident,
        CheckResult $primaryCheckResult,
        CarbonImmutable $checkedAt,
        array $primaryReport,
    ): ProbeConfirmation {
        $existing = ProbeConfirmation::query()
            ->where('monitor_id', $monitor->id)
            ->where('incident_id', $incident->id)
            ->where('kind', ProbeConfirmation::KIND_RECOVERY)
            ->where('status', ProbeConfirmation::STATUS_PENDING)
            ->latest('requested_at')
            ->first();

        if ($existing) {
            return $existing;
        }

        $confirmationRegions = $this->confirmationRegionsFor($monitor->region);

        $confirmation = ProbeConfirmation::query()->create([
            'monitor_id' => $monitor->id,
            'incident_id' => $incident->id,
            'primary_check_result_id' => $primaryCheckResult->id,
            'kind' => ProbeConfirmation::KIND_RECOVERY,
            'status' => ProbeConfirmation::STATUS_PENDING,
            'primary_region' => (string) ($primaryReport['probeRegion'] ?? $monitor->region),
            'confirmation_regions' => $confirmationRegions,
            'results' => [[
                'source' => 'primary',
                'region' => $primaryReport['probeRegion'] ?? $monitor->region,
                'status' => $primaryReport['outcome']->status,
                'http_status_code' => $primaryReport['outcome']->httpStatusCode,
                'error_type' => $primaryReport['outcome']->errorType,
                'error_message' => $primaryReport['outcome']->errorMessage,
                'response_time_ms' => $primaryReport['outcome']->responseTimeMs,
                'checked_at' => $primaryReport['outcome']->checkedAt?->toIso8601String(),
            ]],
            'meta' => [
                'attempt_history' => $primaryReport['attemptHistory'] ?? [],
                'queue_lag_ms' => $primaryReport['queueLagMs'] ?? null,
                'status_policy' => $primaryReport['statusPolicy'] ?? null,
            ],
            'requested_at' => $checkedAt,
        ]);

        foreach ($confirmationRegions as $region) {
            RunMonitorRecoveryConfirmationJob::dispatch(
                $confirmation->id,
                $monitor->id,
                $region,
                $checkedAt->toIso8601String(),
            )->afterCommit();
        }

        return $confirmation;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function recordRecoveryResult(ProbeConfirmation $confirmation, Monitor $monitor, string $region, array $report): ProbeConfirmation
    {
        if ($confirmation->status !== ProbeConfirmation::STATUS_PENDING) {
            return $confirmation;
        }

        $results = collect($confirmation->results ?? [])
            ->reject(fn (array $result) => ($result['source'] ?? null) === 'confirmation' && ($result['region'] ?? null) === $region)
            ->values();

        $results->push([
            'source' => 'confirmation',
            'region' => $region,
            'status' => $report['outcome']->status,
            'http_status_code' => $report['outcome']->httpStatusCode,
            'error_type' => $report['outcome']->errorType,
            'error_message' => $report['outcome']->errorMessage,
            'response_time_ms' => $report['outcome']->responseTimeMs,
            'checked_at' => $report['outcome']->checkedAt?->toIso8601String(),
            'meta' => $report['meta'] ?? [],
        ]);

        $confirmation->forceFill([
            'results' => $results->all(),
        ])->save();

        $requiredSuccesses = max(1, (int) config('realuptime.confirmations.required_successes', 1));
        $confirmationSuccesses = $results
            ->filter(fn (array $result) => ($result['source'] ?? null) === 'confirmation' && ($result['status'] ?? null) === 'up')
            ->count();
        $receivedConfirmationCount = $results
            ->filter(fn (array $result) => ($result['source'] ?? null) === 'confirmation')
            ->count();
        $expectedConfirmationCount = count($confirmation->confirmation_regions ?? []);

        if ($confirmationSuccesses >= $requiredSuccesses) {
            $confirmation->forceFill([
                'status' => ProbeConfirmation::STATUS_CONFIRMED,
                'completed_at' => now(),
            ])->save();

            $this->runner->finalizeConfirmedRecovery($monitor, $confirmation->fresh());

            return $confirmation->fresh();
        }

        if ($receivedConfirmationCount >= $expectedConfirmationCount) {
            $confirmation->forceFill([
                'status' => ProbeConfirmation::STATUS_REJECTED,
                'completed_at' => now(),
            ])->save();
        }

        return $confirmation->fresh();
    }

    /**
     * @return array<int, string>
     */
    public function confirmationRegionsFor(?string $primaryRegion): array
    {
        if (! MonitorQueueResolver::usesRegionQueues()) {
            return [];
        }

        $regions = config('realuptime.probe_regions.regions', []);

        if (! is_array($regions) || $primaryRegion === null) {
            return [];
        }

        $regionConfig = $regions[$primaryRegion] ?? null;

        if (! is_array($regionConfig)) {
            return [];
        }

        return collect($regionConfig['confirmation_regions'] ?? [])
            ->filter(fn (mixed $region) => is_string($region) && trim($region) !== '' && trim($region) !== $primaryRegion)
            ->map(fn (string $region) => trim($region))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $primaryReport
     */
    protected function incidentNeedsRegionalConfirmation(Incident $incident, array $primaryReport): bool
    {
        $reportMeta = $primaryReport['meta'] ?? [];
        $reportStatus = (int) ($primaryReport['outcome']->httpStatusCode ?? 0);
        $incidentStatus = (int) ($incident->http_status_code ?? 0);

        return (bool) data_get($reportMeta, 'cdn.edge_detected', false)
            || (bool) data_get($incident->meta, 'cdn.edge_detected', false)
            || ($reportStatus >= 520 && $reportStatus <= 526)
            || ($incidentStatus >= 520 && $incidentStatus <= 526);
    }
}

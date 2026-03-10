<?php

namespace App\Services\Monitoring;

use App\Models\CheckResult;
use App\Models\HeartbeatEvent;
use App\Models\Incident;
use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use Carbon\CarbonImmutable;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Throwable;

class MonitorRunner
{
    public function __construct(
        protected EmailNotificationService $notifications,
        protected WebhookNotificationService $webhooks,
        protected DomainMetadataResolver $domainMetadata,
        protected TlsMetadataResolver $tlsMetadata,
    ) {}

    public function runDueMonitors(): int
    {
        $now = CarbonImmutable::now();

        $monitors = Monitor::query()
            ->where('status', '!=', Monitor::STATUS_PAUSED)
            ->where(function ($query) use ($now): void {
                $query->whereNull('next_check_at')
                    ->orWhere('next_check_at', '<=', $now);
            })
            ->with(['notificationContacts', 'user'])
            ->orderByRaw('coalesce(next_check_at, created_at)')
            ->get();

        foreach ($monitors as $monitor) {
            $this->runMonitor($monitor, $now);
        }

        return $monitors->count();
    }

    public function runMonitor(Monitor $monitor, ?CarbonImmutable $checkedAt = null): MonitorCheckOutcome
    {
        $checkedAt ??= CarbonImmutable::now();
        $attempts = max(1, $monitor->retry_limit + 1);
        $outcome = null;
        $attemptHistory = [];
        $previousStatus = $monitor->status;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $outcome = $this->performCheck($monitor, $checkedAt, $attempt);
            $attemptHistory[] = $this->attemptHistoryEntry($outcome, $attempt);

            if ($outcome->isUp()) {
                break;
            }
        }

        $meta = [
            ...$outcome->meta,
            'attempt_history' => $attemptHistory,
        ];

        $hasActiveMaintenance = $monitor->maintenanceWindows()
            ->where('status', '!=', MaintenanceWindow::STATUS_CANCELLED)
            ->where('starts_at', '<=', $checkedAt)
            ->where('ends_at', '>=', $checkedAt)
            ->exists();
        $shouldStoreCheckResult = $this->shouldStoreCheckResult($monitor, $outcome, $checkedAt, $previousStatus);
        $checkResult = $shouldStoreCheckResult
            ? CheckResult::query()->create([
                'monitor_id' => $monitor->id,
                'status' => $outcome->status,
                'checked_at' => $outcome->checkedAt,
                'attempts' => $outcome->attempts,
                'response_time_ms' => $outcome->responseTimeMs,
                'http_status_code' => $outcome->httpStatusCode,
                'error_type' => $outcome->errorType,
                'error_message' => $outcome->errorMessage,
                'keyword_match' => $outcome->keywordMatch,
                'meta' => $meta,
            ])
            : new CheckResult([
                'monitor_id' => $monitor->id,
                'status' => $outcome->status,
                'checked_at' => $outcome->checkedAt,
                'attempts' => $outcome->attempts,
                'response_time_ms' => $outcome->responseTimeMs,
                'http_status_code' => $outcome->httpStatusCode,
                'error_type' => $outcome->errorType,
                'error_message' => $outcome->errorMessage,
                'keyword_match' => $outcome->keywordMatch,
                'meta' => $meta,
            ]);

        $effectiveIntervalSeconds = $this->effectiveIntervalSeconds($monitor);
        $monitor->forceFill([
            'status' => $outcome->isUp() ? Monitor::STATUS_UP : Monitor::STATUS_DOWN,
            'last_checked_at' => $checkedAt,
            'last_result_stored_at' => $shouldStoreCheckResult ? $checkedAt : $monitor->last_result_stored_at,
            'next_check_at' => $checkedAt->addSeconds($effectiveIntervalSeconds),
            'check_claimed_at' => null,
            'check_claim_token' => null,
            'last_response_time_ms' => $outcome->responseTimeMs,
            'last_http_status' => $outcome->httpStatusCode,
            'last_error_type' => $outcome->errorType,
            'last_error_message' => $outcome->errorMessage,
            'last_status_changed_at' => $previousStatus === ($outcome->isUp() ? Monitor::STATUS_UP : Monitor::STATUS_DOWN)
                ? $monitor->last_status_changed_at
                : $checkedAt,
        ])->save();

        if (! $outcome->isUp()) {
            $this->resolveIncidentByType($monitor, Incident::TYPE_DEGRADED_PERFORMANCE, $checkResult, $checkedAt);

            $activeIncident = $this->openOrUpdateIncident(
                $monitor,
                Incident::TYPE_DOWNTIME,
                Incident::SEVERITY_MAJOR,
                $checkResult,
                $checkedAt,
                $outcome->errorMessage ?? 'Monitor check failed.',
                [
                    ...$meta,
                    'suppressed_by_maintenance' => $hasActiveMaintenance,
                ],
                $hasActiveMaintenance ? null : 'down',
            );

            $this->maybeEscalateDowntimeIncident($monitor, $activeIncident, $checkResult, $checkedAt, $hasActiveMaintenance);

            return $outcome;
        }

        $this->resolveIncidentByType($monitor, Incident::TYPE_DOWNTIME, $checkResult, $checkedAt);
        $this->evaluateDegradedPerformance($monitor, $checkResult, $checkedAt, $hasActiveMaintenance);
        $this->evaluateExpiryIncidents($monitor, $checkResult, $checkedAt, $hasActiveMaintenance);

        return $outcome;
    }

    protected function performCheck(Monitor $monitor, CarbonImmutable $checkedAt, int $attempt): MonitorCheckOutcome
    {
        return match ($monitor->type) {
            Monitor::TYPE_HTTP, Monitor::TYPE_KEYWORD => $this->checkHttp($monitor, $checkedAt, $attempt),
            Monitor::TYPE_PING => $this->checkPing($monitor, $checkedAt, $attempt),
            Monitor::TYPE_PORT => $this->checkPort($monitor, $checkedAt, $attempt),
            Monitor::TYPE_SSL => $this->checkSsl($monitor, $checkedAt, $attempt),
            Monitor::TYPE_HEARTBEAT => $this->checkHeartbeat($monitor, $checkedAt, $attempt),
            Monitor::TYPE_SYNTHETIC => $this->checkSynthetic($monitor, $checkedAt, $attempt),
            default => MonitorCheckOutcome::down($checkedAt, $attempt, 'unsupported_type', 'Unsupported monitor type.'),
        };
    }

    protected function checkHttp(Monitor $monitor, CarbonImmutable $checkedAt, int $attempt): MonitorCheckOutcome
    {
        $started = microtime(true);

        try {
            $request = Http::timeout($monitor->timeout_seconds)
                ->withOptions(['allow_redirects' => $monitor->follow_redirects])
                ->withHeaders($monitor->custom_headers ?? []);

            if ($monitor->auth_username && $monitor->auth_password) {
                $request = $request->withBasicAuth($monitor->auth_username, $monitor->auth_password);
            }

            $response = $request->send($monitor->request_method ?: 'GET', $monitor->target ?: '');
            $responseTime = (int) round((microtime(true) - $started) * 1000);
            $httpStatus = $response->status();

            if ($monitor->expected_status_code && $httpStatus !== $monitor->expected_status_code) {
                return MonitorCheckOutcome::down(
                    $checkedAt,
                    $attempt,
                    'invalid_status',
                    sprintf('Expected HTTP %d but received %d.', $monitor->expected_status_code, $httpStatus),
                    $responseTime,
                    $httpStatus,
                    null,
                    ['body_preview' => mb_substr($response->body(), 0, 240)],
                );
            }

            if ($monitor->type === Monitor::TYPE_KEYWORD && $monitor->expected_keyword) {
                $matched = $this->matchKeyword($response->body(), $monitor->expected_keyword, $monitor->keyword_match_type ?: 'contains');

                if (! $matched) {
                    return MonitorCheckOutcome::down(
                        $checkedAt,
                        $attempt,
                        'keyword_mismatch',
                        sprintf('Keyword validation failed for "%s".', $monitor->expected_keyword),
                        $responseTime,
                        $httpStatus,
                        false,
                        ['body_preview' => mb_substr($response->body(), 0, 240)],
                    );
                }

                $this->refreshMonitorMetadata($monitor, $checkedAt);

                return MonitorCheckOutcome::up(
                    $checkedAt,
                    $attempt,
                    $responseTime,
                    $httpStatus,
                    true,
                    $this->responseAlertMeta($monitor, $responseTime, $checkedAt),
                );
            }

            $this->refreshMonitorMetadata($monitor, $checkedAt);

            return MonitorCheckOutcome::up(
                $checkedAt,
                $attempt,
                $responseTime,
                $httpStatus,
                null,
                $this->responseAlertMeta($monitor, $responseTime, $checkedAt),
            );
        } catch (Throwable $exception) {
            return MonitorCheckOutcome::down(
                $checkedAt,
                $attempt,
                class_basename($exception),
                $exception->getMessage(),
            );
        }
    }

    protected function checkPing(Monitor $monitor, CarbonImmutable $checkedAt, int $attempt): MonitorCheckOutcome
    {
        $count = max(1, $monitor->packet_count ?: 1);
        $timeout = max(1, $monitor->timeout_seconds);
        $waitArgument = PHP_OS_FAMILY === 'Darwin'
            ? (string) ($timeout * 1000)
            : (string) $timeout;

        $result = Process::timeout(max(5, $timeout * $count))
            ->run(['ping', '-c', (string) $count, '-W', $waitArgument, $monitor->target ?: '']);

        $output = trim($result->output()."\n".$result->errorOutput());

        if (! $result->successful()) {
            return MonitorCheckOutcome::down($checkedAt, $attempt, 'ping_failed', $output ?: 'All packets were lost.');
        }

        preg_match('/= [\d\.]+\/([\d\.]+)\/[\d\.]+\/[\d\.]+ ms/', $output, $matches);
        $avgLatency = isset($matches[1]) ? (int) round((float) $matches[1]) : null;

        if ($monitor->latency_threshold_ms && $avgLatency !== null && $avgLatency > $monitor->latency_threshold_ms) {
            return MonitorCheckOutcome::up(
                $checkedAt,
                $attempt,
                $avgLatency,
                null,
                null,
                [
                    'slow' => true,
                    'latency_threshold_ms' => $monitor->latency_threshold_ms,
                    'performance_reason' => sprintf('Average latency %dms exceeded the %dms threshold.', $avgLatency, $monitor->latency_threshold_ms),
                    'raw_output' => $output,
                ],
            );
        }

        return MonitorCheckOutcome::up($checkedAt, $attempt, $avgLatency, null, null, ['raw_output' => $output]);
    }

    protected function shouldStoreCheckResult(
        Monitor $monitor,
        MonitorCheckOutcome $outcome,
        CarbonImmutable $checkedAt,
        string $previousStatus,
    ): bool {
        if ($monitor->type !== Monitor::TYPE_PING) {
            return true;
        }

        if (! $outcome->isUp() || (bool) data_get($outcome->meta, 'slow', false)) {
            return true;
        }

        if ($previousStatus !== Monitor::STATUS_UP) {
            return true;
        }

        if ($monitor->openIncidents()->exists()) {
            return true;
        }

        if (! $monitor->last_result_stored_at) {
            return true;
        }

        $sampleSeconds = max(60, (int) config('realuptime.ping.healthy_result_sample_seconds', 300));

        return $monitor->last_result_stored_at->diffInSeconds($checkedAt) >= $sampleSeconds;
    }

    protected function checkPort(Monitor $monitor, CarbonImmutable $checkedAt, int $attempt): MonitorCheckOutcome
    {
        [$host, $port] = $this->parsePortTarget((string) ($monitor->target ?? ''));

        if (! $host || ! $port) {
            return MonitorCheckOutcome::down($checkedAt, $attempt, 'invalid_target', 'Port monitors require a target in the format host:port.');
        }

        $started = microtime(true);
        $socket = $this->openTcpSocket($host, $port, max(1, $monitor->timeout_seconds), $errno, $errorMessage);
        $responseTime = (int) round((microtime(true) - $started) * 1000);

        if (! is_resource($socket)) {
            return MonitorCheckOutcome::down(
                $checkedAt,
                $attempt,
                'port_unreachable',
                trim(($errorMessage ?: 'TCP connection failed.').' ('.$host.':'.$port.')'),
                $responseTime,
            );
        }

        fclose($socket);

        return MonitorCheckOutcome::up(
            $checkedAt,
            $attempt,
            $responseTime,
            null,
            null,
            [
                ...$this->responseAlertMeta($monitor, $responseTime, $checkedAt),
                'port' => $port,
            ],
        );
    }

    protected function openTcpSocket(string $host, int $port, int $timeoutSeconds, ?int &$errno = 0, ?string &$errorMessage = null)
    {
        return @stream_socket_client(
            sprintf('tcp://%s:%d', $host, $port),
            $errno,
            $errorMessage,
            $timeoutSeconds,
            STREAM_CLIENT_CONNECT
        );
    }

    protected function checkSsl(Monitor $monitor, CarbonImmutable $checkedAt, int $attempt): MonitorCheckOutcome
    {
        $host = parse_url($monitor->target ?: '', PHP_URL_HOST) ?: $monitor->target;

        if (! $host) {
            return MonitorCheckOutcome::down($checkedAt, $attempt, 'invalid_host', 'A valid domain is required for SSL checks.');
        }

        try {
            $ssl = $this->tlsMetadata->resolve($host, $monitor->timeout_seconds);

            if (! $ssl) {
                return MonitorCheckOutcome::down($checkedAt, $attempt, 'invalid_certificate', 'The SSL certificate could not be parsed.');
            }

            $expiresAt = $ssl['expires_at'];
            $issuer = $ssl['issuer'];
            $daysRemaining = $checkedAt->diffInDays($expiresAt, false);

            $monitor->forceFill([
                'ssl_expires_at' => $expiresAt,
                'ssl_checked_at' => $checkedAt,
                'ssl_issuer' => $issuer,
            ])->save();

            $this->refreshDomainMetadata($monitor, $checkedAt);
            $meta = [
                'issuer' => $issuer,
                'expires_at' => $expiresAt->toIso8601String(),
                ...$this->expirationAlertMeta($monitor, $checkedAt),
            ];

            return MonitorCheckOutcome::up($checkedAt, $attempt, null, null, null, $meta);
        } catch (Throwable $exception) {
            return MonitorCheckOutcome::down($checkedAt, $attempt, class_basename($exception), $exception->getMessage());
        }
    }

    protected function checkHeartbeat(Monitor $monitor, CarbonImmutable $checkedAt, int $attempt): MonitorCheckOutcome
    {
        $lastHeartbeat = $monitor->last_heartbeat_at
            ?? HeartbeatEvent::query()->where('monitor_id', $monitor->id)->latest('received_at')->value('received_at');

        $baseline = $lastHeartbeat
            ? CarbonImmutable::parse($lastHeartbeat)
            : CarbonImmutable::parse($monitor->created_at);

        $deadline = $baseline->addSeconds($this->effectiveIntervalSeconds($monitor) + ($monitor->heartbeat_grace_seconds ?: 0));

        if ($checkedAt->gt($deadline)) {
            return MonitorCheckOutcome::down(
                $checkedAt,
                $attempt,
                'heartbeat_missing',
                'No heartbeat ping was received before the grace deadline.',
                null,
                null,
                null,
                ['deadline' => $deadline->toIso8601String()],
            );
        }

        return MonitorCheckOutcome::up($checkedAt, $attempt, null, null, null, [
            'last_heartbeat_at' => $baseline->toIso8601String(),
            'next_deadline' => $deadline->toIso8601String(),
        ]);
    }

    protected function checkSynthetic(Monitor $monitor, CarbonImmutable $checkedAt, int $attempt): MonitorCheckOutcome
    {
        $steps = collect($monitor->synthetic_steps ?? [])->values();

        if ($steps->isEmpty()) {
            return MonitorCheckOutcome::down(
                $checkedAt,
                $attempt,
                'invalid_synthetic_config',
                'Synthetic monitors require at least one configured step.',
            );
        }

        $transactionStarted = microtime(true);
        $cookieJar = new CookieJar;
        $stepResults = [];
        $lastStatusCode = null;

        foreach ($steps as $index => $step) {
            $stepNumber = $index + 1;
            $stepName = trim((string) data_get($step, 'name')) ?: sprintf('Step %d', $stepNumber);
            $stepUrl = $this->resolveSyntheticStepUrl($monitor, (string) data_get($step, 'url', ''));
            $method = strtoupper((string) data_get($step, 'method', 'GET'));

            if (! $stepUrl) {
                $stepResults[] = [
                    'name' => $stepName,
                    'method' => $method,
                    'url' => data_get($step, 'url'),
                    'status' => 'down',
                    'error_type' => 'invalid_step_url',
                    'error_message' => 'Relative step URLs require a base URL on the monitor.',
                ];

                return MonitorCheckOutcome::down(
                    $checkedAt,
                    $attempt,
                    'invalid_step_url',
                    sprintf('Synthetic step %d "%s" has an invalid URL.', $stepNumber, $stepName),
                    null,
                    null,
                    null,
                    ['transaction_steps' => $stepResults, 'failed_step' => $stepNumber],
                );
            }

            $stepStarted = microtime(true);

            try {
                $request = Http::timeout($monitor->timeout_seconds)
                    ->withOptions([
                        'allow_redirects' => $monitor->follow_redirects,
                        'cookies' => $cookieJar,
                    ])
                    ->withHeaders([
                        ...($monitor->custom_headers ?? []),
                        ...$this->syntheticStepHeaders($step),
                    ]);

                if ($monitor->auth_username && $monitor->auth_password) {
                    $request = $request->withBasicAuth($monitor->auth_username, $monitor->auth_password);
                }

                $response = $request->send($method, $stepUrl, $this->syntheticStepRequestOptions($step));
                $stepTime = (int) round((microtime(true) - $stepStarted) * 1000);
                $lastStatusCode = $response->status();
                $expectedStatusCode = (int) data_get($step, 'expected_status_code', 200);
                $expectedKeyword = trim((string) data_get($step, 'expected_keyword', ''));

                if ($lastStatusCode !== $expectedStatusCode) {
                    $stepResults[] = [
                        'name' => $stepName,
                        'method' => $method,
                        'url' => $stepUrl,
                        'status' => 'down',
                        'response_time_ms' => $stepTime,
                        'http_status_code' => $lastStatusCode,
                        'expected_status_code' => $expectedStatusCode,
                    ];

                    return MonitorCheckOutcome::down(
                        $checkedAt,
                        $attempt,
                        'invalid_status',
                        sprintf('Synthetic step %d "%s" expected HTTP %d but received %d.', $stepNumber, $stepName, $expectedStatusCode, $lastStatusCode),
                        (int) round((microtime(true) - $transactionStarted) * 1000),
                        $lastStatusCode,
                        null,
                        [
                            'transaction_steps' => $stepResults,
                            'failed_step' => $stepNumber,
                            'body_preview' => mb_substr($response->body(), 0, 240),
                        ],
                    );
                }

                if ($expectedKeyword !== '' && ! str_contains($response->body(), $expectedKeyword)) {
                    $stepResults[] = [
                        'name' => $stepName,
                        'method' => $method,
                        'url' => $stepUrl,
                        'status' => 'down',
                        'response_time_ms' => $stepTime,
                        'http_status_code' => $lastStatusCode,
                        'expected_keyword' => $expectedKeyword,
                    ];

                    return MonitorCheckOutcome::down(
                        $checkedAt,
                        $attempt,
                        'keyword_mismatch',
                        sprintf('Synthetic step %d "%s" did not contain the expected keyword.', $stepNumber, $stepName),
                        (int) round((microtime(true) - $transactionStarted) * 1000),
                        $lastStatusCode,
                        false,
                        [
                            'transaction_steps' => $stepResults,
                            'failed_step' => $stepNumber,
                            'body_preview' => mb_substr($response->body(), 0, 240),
                        ],
                    );
                }

                $stepResults[] = [
                    'name' => $stepName,
                    'method' => $method,
                    'url' => $stepUrl,
                    'status' => 'up',
                    'response_time_ms' => $stepTime,
                    'http_status_code' => $lastStatusCode,
                ];
            } catch (Throwable $exception) {
                $stepResults[] = [
                    'name' => $stepName,
                    'method' => $method,
                    'url' => $stepUrl,
                    'status' => 'down',
                    'error_type' => class_basename($exception),
                    'error_message' => $exception->getMessage(),
                ];

                return MonitorCheckOutcome::down(
                    $checkedAt,
                    $attempt,
                    class_basename($exception),
                    sprintf('Synthetic step %d "%s" failed: %s', $stepNumber, $stepName, $exception->getMessage()),
                    (int) round((microtime(true) - $transactionStarted) * 1000),
                    null,
                    null,
                    ['transaction_steps' => $stepResults, 'failed_step' => $stepNumber],
                );
            }
        }

        $totalResponseTime = (int) round((microtime(true) - $transactionStarted) * 1000);
        $this->refreshMonitorMetadata($monitor, $checkedAt);

        return MonitorCheckOutcome::up(
            $checkedAt,
            $attempt,
            $totalResponseTime,
            $lastStatusCode,
            null,
            [
                ...$this->responseAlertMeta($monitor, $totalResponseTime, $checkedAt),
                'synthetic' => true,
                'transaction_steps' => $stepResults,
                'transaction_step_count' => count($stepResults),
            ],
        );
    }

    protected function resolveSyntheticStepUrl(Monitor $monitor, string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $url) === 1) {
            return $url;
        }

        $baseUrl = trim((string) ($monitor->target ?? ''));

        if ($baseUrl === '' || preg_match('/^https?:\/\//i', $baseUrl) !== 1) {
            return null;
        }

        return rtrim($baseUrl, '/').'/'.ltrim($url, '/');
    }

    /**
     * @param  array<string, mixed>  $step
     * @return array<string, string>
     */
    protected function syntheticStepHeaders(array $step): array
    {
        $headers = data_get($step, 'headers');

        if (! is_array($headers)) {
            return [];
        }

        return collect($headers)
            ->filter(fn ($value, $key) => is_string($key) && $key !== '' && (is_scalar($value) || $value === null))
            ->map(fn ($value) => (string) $value)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $step
     * @return array<string, mixed>
     */
    protected function syntheticStepRequestOptions(array $step): array
    {
        $body = data_get($step, 'body');

        if ($body === null || $body === '') {
            return [];
        }

        if (is_string($body)) {
            return ['body' => $body];
        }

        return ['json' => $body];
    }

    protected function matchKeyword(string $body, string $keyword, string $matchType): bool
    {
        return match ($matchType) {
            'exact' => trim($body) === $keyword,
            'regex' => (bool) preg_match($keyword, $body),
            default => str_contains($body, $keyword),
        };
    }

    protected function refreshMonitorMetadata(Monitor $monitor, CarbonImmutable $checkedAt): void
    {
        $this->refreshDomainMetadata($monitor, $checkedAt);
        $this->refreshSslMetadata($monitor, $checkedAt);
    }

    protected function effectiveIntervalSeconds(Monitor $monitor): int
    {
        $user = $monitor->relationLoaded('user') ? $monitor->user : $monitor->user()->first();

        if (! $user) {
            return (int) $monitor->interval_seconds;
        }

        return max((int) $monitor->interval_seconds, $user->minimumMonitorIntervalSeconds());
    }

    protected function refreshDomainMetadata(Monitor $monitor, CarbonImmutable $checkedAt): void
    {
        if (! in_array($monitor->type, [Monitor::TYPE_HTTP, Monitor::TYPE_KEYWORD, Monitor::TYPE_SSL, Monitor::TYPE_SYNTHETIC], true)) {
            return;
        }

        if ($monitor->domain_checked_at && $monitor->domain_checked_at->gt($checkedAt->subDay())) {
            return;
        }

        $host = $this->monitorHost($monitor);

        if (! $host) {
            return;
        }

        $domain = $this->domainMetadata->resolve($host, $monitor->timeout_seconds);

        $monitor->forceFill([
            'domain_expires_at' => $domain['expires_at'] ?? $monitor->domain_expires_at,
            'domain_registrar' => $domain['registrar'] ?? $monitor->domain_registrar,
            'domain_checked_at' => $checkedAt,
        ])->save();
    }

    protected function refreshSslMetadata(Monitor $monitor, CarbonImmutable $checkedAt): void
    {
        if ($monitor->type === Monitor::TYPE_SSL) {
            return;
        }

        if (! in_array($monitor->type, [Monitor::TYPE_HTTP, Monitor::TYPE_KEYWORD, Monitor::TYPE_SYNTHETIC], true)) {
            return;
        }

        if ($monitor->ssl_checked_at && $monitor->ssl_checked_at->gt($checkedAt->subHours(12))) {
            return;
        }

        $target = (string) ($monitor->target ?? '');

        if (! str_starts_with(strtolower($target), 'https://')) {
            return;
        }

        $host = $this->monitorHost($monitor);

        if (! $host) {
            return;
        }

        $ssl = $this->tlsMetadata->resolve($host, $monitor->timeout_seconds);

        if (! $ssl) {
            return;
        }

        $monitor->forceFill([
            'ssl_expires_at' => $ssl['expires_at'],
            'ssl_issuer' => $ssl['issuer'],
            'ssl_checked_at' => $checkedAt,
        ])->save();
    }

    protected function monitorHost(Monitor $monitor): ?string
    {
        if ($monitor->type === Monitor::TYPE_PORT) {
            [$host] = $this->parsePortTarget((string) ($monitor->target ?? ''));

            return $host;
        }

        $host = parse_url((string) ($monitor->target ?? ''), PHP_URL_HOST);

        if (is_string($host) && $host !== '') {
            return $host;
        }

        $target = trim((string) ($monitor->target ?? ''));

        return $target !== '' ? $target : null;
    }

    protected function attemptHistoryEntry(MonitorCheckOutcome $outcome, int $attempt): array
    {
        return [
            'attempt' => $attempt,
            'status' => $outcome->status,
            'checked_at' => $outcome->checkedAt->toIso8601String(),
            'response_time_ms' => $outcome->responseTimeMs,
            'http_status_code' => $outcome->httpStatusCode,
            'error_type' => $outcome->errorType,
            'error_message' => $outcome->errorMessage,
            'slow' => (bool) data_get($outcome->meta, 'slow', false),
        ];
    }

    protected function responseAlertMeta(Monitor $monitor, int $responseTime, CarbonImmutable $checkedAt): array
    {
        return [
            ...$this->expirationAlertMeta($monitor, $checkedAt),
            ...($monitor->latency_threshold_ms && $responseTime > $monitor->latency_threshold_ms
                ? [
                    'slow' => true,
                    'latency_threshold_ms' => $monitor->latency_threshold_ms,
                    'performance_reason' => sprintf('Response time %dms exceeded the %dms threshold.', $responseTime, $monitor->latency_threshold_ms),
                ]
                : []),
        ];
    }

    protected function expirationAlertMeta(Monitor $monitor, CarbonImmutable $checkedAt): array
    {
        $meta = [];

        if ($monitor->ssl_expires_at) {
            $meta['ssl_days_remaining'] = $checkedAt->diffInDays($monitor->ssl_expires_at, false);
            $meta['ssl_expiring'] = $monitor->ssl_threshold_days !== null
                && $meta['ssl_days_remaining'] <= $monitor->ssl_threshold_days;
        }

        if ($monitor->domain_expires_at) {
            $meta['domain_days_remaining'] = $checkedAt->diffInDays($monitor->domain_expires_at, false);
            $meta['domain_expiring'] = $monitor->domain_threshold_days !== null
                && $meta['domain_days_remaining'] <= $monitor->domain_threshold_days;
        }

        return $meta;
    }

    protected function evaluateDegradedPerformance(
        Monitor $monitor,
        CheckResult $checkResult,
        CarbonImmutable $checkedAt,
        bool $hasActiveMaintenance,
    ): void {
        if (! $this->supportsLatencyAlerts($monitor) || ! $monitor->latency_threshold_ms) {
            $this->resolveIncidentByType($monitor, Incident::TYPE_DEGRADED_PERFORMANCE, $checkResult, $checkedAt);

            return;
        }

        $isSlow = (bool) data_get($checkResult->meta, 'slow', false);

        if (! $isSlow || $checkResult->status !== 'up') {
            $this->resolveIncidentByType($monitor, Incident::TYPE_DEGRADED_PERFORMANCE, $checkResult, $checkedAt);

            return;
        }

        $requiredChecks = max(1, (int) ($monitor->degraded_consecutive_checks ?: 3));
        $recentChecks = CheckResult::query()
            ->where('monitor_id', $monitor->id)
            ->latest('checked_at')
            ->limit($requiredChecks)
            ->get();

        $sustainedSlow = $recentChecks->count() === $requiredChecks
            && $recentChecks->every(fn (CheckResult $result) => $result->status === 'up' && (bool) data_get($result->meta, 'slow', false));

        if (! $sustainedSlow) {
            return;
        }

        $latencies = $recentChecks
            ->pluck('response_time_ms')
            ->filter(fn ($value) => $value !== null)
            ->map(fn ($value) => (int) $value)
            ->values()
            ->all();

        $reason = data_get($checkResult->meta, 'performance_reason')
            ?: sprintf('Response time exceeded %dms for %d consecutive checks.', $monitor->latency_threshold_ms, $requiredChecks);

        $this->openOrUpdateIncident(
            $monitor,
            Incident::TYPE_DEGRADED_PERFORMANCE,
            Incident::SEVERITY_WARNING,
            $checkResult,
            $checkedAt,
            $reason,
            [
                'latency_threshold_ms' => $monitor->latency_threshold_ms,
                'degraded_consecutive_checks' => $requiredChecks,
                'p95_response_time_ms' => $this->percentile($latencies, 95),
                'suppressed_by_maintenance' => $hasActiveMaintenance,
            ],
            $hasActiveMaintenance ? null : 'degraded',
        );
    }

    protected function evaluateExpiryIncidents(
        Monitor $monitor,
        CheckResult $checkResult,
        CarbonImmutable $checkedAt,
        bool $hasActiveMaintenance,
    ): void {
        $this->evaluateExpiryIncident(
            $monitor,
            $checkResult,
            $checkedAt,
            $hasActiveMaintenance,
            Incident::TYPE_SSL_EXPIRY,
            $monitor->ssl_expires_at,
            $monitor->ssl_threshold_days,
            'ssl_days_remaining',
            'ssl_expiry',
            'SSL certificate expires in %d day(s).',
        );

        $this->evaluateExpiryIncident(
            $monitor,
            $checkResult,
            $checkedAt,
            $hasActiveMaintenance,
            Incident::TYPE_DOMAIN_EXPIRY,
            $monitor->domain_expires_at,
            $monitor->domain_threshold_days,
            'domain_days_remaining',
            'domain_expiry',
            'Domain registration expires in %d day(s).',
        );
    }

    protected function evaluateExpiryIncident(
        Monitor $monitor,
        CheckResult $checkResult,
        CarbonImmutable $checkedAt,
        bool $hasActiveMaintenance,
        string $incidentType,
        mixed $expiresAt,
        ?int $thresholdDays,
        string $daysRemainingKey,
        string $notificationType,
        string $reasonTemplate,
    ): void {
        if (! $expiresAt || $thresholdDays === null) {
            $this->resolveIncidentByType($monitor, $incidentType, $checkResult, $checkedAt);

            return;
        }

        $daysRemaining = (int) $checkedAt->diffInDays(CarbonImmutable::parse($expiresAt), false);

        if ($daysRemaining > $thresholdDays) {
            $this->resolveIncidentByType($monitor, $incidentType, $checkResult, $checkedAt);

            return;
        }

        $this->openOrUpdateIncident(
            $monitor,
            $incidentType,
            Incident::SEVERITY_WARNING,
            $checkResult,
            $checkedAt,
            sprintf($reasonTemplate, $daysRemaining),
            [
                $daysRemainingKey => $daysRemaining,
                'threshold_days' => $thresholdDays,
                'expires_at' => CarbonImmutable::parse($expiresAt)->toIso8601String(),
                'suppressed_by_maintenance' => $hasActiveMaintenance,
            ],
            $hasActiveMaintenance ? null : $notificationType,
        );
    }

    protected function resolveIncidentByType(
        Monitor $monitor,
        string $type,
        CheckResult $checkResult,
        CarbonImmutable $checkedAt,
    ): ?Incident {
        $incident = $this->openIncidentForType($monitor, $type);

        if (! $incident) {
            return null;
        }

        $incident->forceFill([
            'latest_check_result_id' => $checkResult->id,
            'resolved_at' => $checkedAt,
            'duration_seconds' => $incident->started_at?->diffInSeconds($checkedAt),
        ])->save();

        if (! data_get($incident->meta, 'suppressed_by_maintenance', false)) {
            $this->sendResolutionNotification($monitor, $incident);
        }

        return $incident;
    }

    protected function openOrUpdateIncident(
        Monitor $monitor,
        string $type,
        string $severity,
        CheckResult $checkResult,
        CarbonImmutable $checkedAt,
        string $reason,
        array $meta = [],
        ?string $notificationType = null,
    ): Incident {
        $incident = $this->openIncidentForType($monitor, $type);
        $mergedMeta = $incident
            ? [...($incident->meta ?? []), ...$meta]
            : $meta;

        if (! $incident) {
            $incident = Incident::query()->create([
                'monitor_id' => $monitor->id,
                'first_check_result_id' => $checkResult->id,
                'last_good_check_result_id' => $this->lastHealthyCheckResultId($monitor, $type, $checkedAt),
                'latest_check_result_id' => $checkResult->id,
                'started_at' => $checkedAt,
                'type' => $type,
                'severity' => $severity,
                'reason' => $reason,
                'error_type' => $checkResult->error_type,
                'http_status_code' => $checkResult->http_status_code,
                'meta' => $mergedMeta,
            ]);

            if ($notificationType !== null) {
                $this->sendOpenNotification($monitor, $incident, $notificationType);
            }

            return $incident;
        }

        $incident->forceFill([
            'latest_check_result_id' => $checkResult->id,
            'severity' => $this->moreSevere($incident->severity, $severity),
            'reason' => $reason,
            'error_type' => $checkResult->error_type ?? $incident->error_type,
            'http_status_code' => $checkResult->http_status_code ?? $incident->http_status_code,
            'meta' => $mergedMeta,
        ])->save();

        return $incident;
    }

    protected function maybeEscalateDowntimeIncident(
        Monitor $monitor,
        Incident $incident,
        CheckResult $checkResult,
        CarbonImmutable $checkedAt,
        bool $hasActiveMaintenance,
    ): void {
        if ($hasActiveMaintenance || $incident->critical_alert_sent_at || ! $monitor->critical_alert_after_minutes) {
            return;
        }

        if (! $incident->started_at || $incident->started_at->diffInMinutes($checkedAt) < $monitor->critical_alert_after_minutes) {
            return;
        }

        $incident->forceFill([
            'latest_check_result_id' => $checkResult->id,
            'severity' => Incident::SEVERITY_CRITICAL,
            'critical_alert_sent_at' => $checkedAt,
        ])->save();

        // Reserved by policy: prolonged downtime changes severity, but does not trigger another email.
    }

    protected function openIncidentForType(Monitor $monitor, string $type): ?Incident
    {
        return Incident::query()
            ->where('monitor_id', $monitor->id)
            ->where('type', $type)
            ->whereNull('resolved_at')
            ->latest('started_at')
            ->first();
    }

    protected function lastHealthyCheckResultId(Monitor $monitor, string $incidentType, CarbonImmutable $checkedAt): ?int
    {
        return CheckResult::query()
            ->where('monitor_id', $monitor->id)
            ->where('checked_at', '<', $checkedAt)
            ->latest('checked_at')
            ->limit(200)
            ->get()
            ->first(fn (CheckResult $result) => $this->isHealthyForIncidentType($result, $incidentType))
            ?->id;
    }

    protected function isHealthyForIncidentType(CheckResult $result, string $incidentType): bool
    {
        return match ($incidentType) {
            Incident::TYPE_DOWNTIME => $result->status === 'up',
            Incident::TYPE_DEGRADED_PERFORMANCE => $result->status === 'up' && ! (bool) data_get($result->meta, 'slow', false),
            Incident::TYPE_SSL_EXPIRY => ! (bool) data_get($result->meta, 'ssl_expiring', false),
            Incident::TYPE_DOMAIN_EXPIRY => ! (bool) data_get($result->meta, 'domain_expiring', false),
            default => true,
        };
    }

    protected function sendOpenNotification(Monitor $monitor, Incident $incident, string $notificationType): void
    {
        $monitor = $monitor->relationLoaded('notificationContacts') && $monitor->relationLoaded('user')
            ? $monitor
            : $monitor->fresh(['notificationContacts', 'user']);

        switch ($notificationType) {
            case 'down':
                $this->notifications->sendDownAlert($monitor, $incident);
                $this->webhooks->sendDownAlert($monitor, $incident);
                break;
        }
    }

    protected function sendResolutionNotification(Monitor $monitor, Incident $incident): void
    {
        $monitor = $monitor->relationLoaded('notificationContacts') && $monitor->relationLoaded('user')
            ? $monitor
            : $monitor->fresh(['notificationContacts', 'user']);

        if ($incident->type === Incident::TYPE_DOWNTIME) {
            $this->notifications->sendRecoveryAlert($monitor, $incident);
        }
    }

    protected function supportsLatencyAlerts(Monitor $monitor): bool
    {
        return in_array($monitor->type, [Monitor::TYPE_HTTP, Monitor::TYPE_PORT, Monitor::TYPE_KEYWORD, Monitor::TYPE_PING, Monitor::TYPE_SYNTHETIC], true);
    }

    /**
     * @return array{0:?string,1:?int}
     */
    protected function parsePortTarget(string $target): array
    {
        $target = trim($target);

        if ($target === '') {
            return [null, null];
        }

        if (preg_match('/^\[([^\]]+)\]:(\d+)$/', $target, $matches) === 1) {
            return [$matches[1], (int) $matches[2]];
        }

        $lastColon = strrpos($target, ':');

        if ($lastColon === false) {
            return [null, null];
        }

        $host = trim(substr($target, 0, $lastColon));
        $port = (int) trim(substr($target, $lastColon + 1));

        if ($host === '' || $port < 1 || $port > 65535) {
            return [null, null];
        }

        return [$host, $port];
    }

    /**
     * @param  array<int, int>  $values
     */
    protected function percentile(array $values, int $percentile): ?int
    {
        if ($values === []) {
            return null;
        }

        sort($values);

        $index = (int) ceil((count($values) * $percentile) / 100) - 1;
        $index = max(0, min(count($values) - 1, $index));

        return $values[$index];
    }

    protected function moreSevere(string $left, string $right): string
    {
        $rank = [
            Incident::SEVERITY_WARNING => 1,
            Incident::SEVERITY_MAJOR => 2,
            Incident::SEVERITY_CRITICAL => 3,
        ];

        return ($rank[$right] ?? 0) > ($rank[$left] ?? 0) ? $right : $left;
    }
}

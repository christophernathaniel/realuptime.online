<?php

namespace App\Http\Requests\Monitoring;

use App\Models\Monitor;
use App\Support\WorkspaceResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use JsonException;

class UpsertMonitorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $workspace = app(WorkspaceResolver::class)->current($this);
        $workspaceOwnerId = $workspace->id;
        $type = $this->input('type');
        $requiresUrl = $type === Monitor::TYPE_HTTP;
        $requiresHost = in_array($type, [Monitor::TYPE_PING, Monitor::TYPE_PORT], true);
        $minimumInterval = $workspace->minimumMonitorIntervalSeconds();

        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in([
                Monitor::TYPE_HTTP,
                Monitor::TYPE_PORT,
                Monitor::TYPE_PING,
            ])],
            'target' => [Rule::requiredIf($requiresUrl || $requiresHost), 'nullable', 'string', 'max:2048'],
            'request_method' => [Rule::requiredIf($type === Monitor::TYPE_HTTP), 'nullable', Rule::in(['GET', 'POST'])],
            'interval_seconds' => ['required', 'integer', 'min:'.$minimumInterval, 'max:86400'],
            'timeout_seconds' => ['required', 'integer', 'min:1', 'max:60'],
            'retry_limit' => ['required', 'integer', 'min:0', 'max:5'],
            'follow_redirects' => ['nullable', 'boolean'],
            'custom_headers' => ['nullable', 'string'],
            'auth_username' => ['nullable', 'string', 'max:255'],
            'auth_password' => ['nullable', 'string', 'max:255'],
            'expected_status_code' => ['nullable', 'integer', 'between:100,599'],
            'expected_keyword' => ['nullable', 'string', 'max:255'],
            'keyword_match_type' => ['nullable', Rule::in(['contains', 'exact', 'regex'])],
            'packet_count' => ['nullable', 'integer', 'min:1', 'max:10'],
            'synthetic_steps' => ['nullable', 'string'],
            'latency_threshold_ms' => ['nullable', 'integer', 'min:1', 'max:60000'],
            'degraded_consecutive_checks' => ['nullable', 'integer', 'min:1', 'max:10'],
            'critical_alert_after_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'downtime_webhook_urls' => ['nullable', 'string'],
            'capability_names' => ['nullable', 'string', 'max:2000'],
            'ssl_threshold_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'domain_threshold_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'heartbeat_grace_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'region' => ['required', 'string', 'max:120'],
            'contact_ids' => ['nullable', 'array'],
            'contact_ids.*' => [
                'integer',
                Rule::exists('notification_contacts', 'id')->where(fn ($query) => $query->where('user_id', $workspaceOwnerId)),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function monitorData(): array
    {
        $data = $this->validated();
        $workspace = app(WorkspaceResolver::class)->current($this);
        /** @var Monitor|null $currentMonitor */
        $currentMonitor = $this->route('monitor');
        $headers = trim((string) ($data['custom_headers'] ?? ''));

        try {
            $customHeaders = $headers === '' ? null : json_decode($headers, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw ValidationException::withMessages([
                'custom_headers' => 'Custom headers must be valid JSON.',
            ]);
        }
        $syntheticSteps = $this->decodeSyntheticSteps((string) ($data['synthetic_steps'] ?? ''), (string) $data['type']);
        $downtimeWebhookUrls = $this->decodeDowntimeWebhookUrls(
            $workspace,
            $currentMonitor,
            $data['downtime_webhook_urls'] ?? null,
        );

        $data['custom_headers'] = $customHeaders;
        $data['synthetic_steps'] = $syntheticSteps;
        $data['downtime_webhook_urls'] = $downtimeWebhookUrls;
        $data['follow_redirects'] = $this->boolean('follow_redirects');
        $data['critical_alert_after_minutes'] = $data['critical_alert_after_minutes'] ?? 30;

        if ($data['type'] === Monitor::TYPE_PORT) {
            $data['target'] = $this->normalizePortTarget((string) $data['target']);
        }

        if ($workspace->allowsAdvancedWorkspaceFeatures()) {
            if (! in_array($data['type'], [Monitor::TYPE_HTTP], true)) {
                $data['follow_redirects'] = true;
                $data['custom_headers'] = null;
                $data['auth_username'] = null;
                $data['auth_password'] = null;
            }

            if ($data['type'] !== Monitor::TYPE_HTTP) {
                $data['request_method'] = null;
                $data['expected_status_code'] = null;
            }
        } else {
            $data['request_method'] = $data['type'] === Monitor::TYPE_HTTP ? 'GET' : null;
            $data['timeout_seconds'] = 30;
            $data['retry_limit'] = 2;
            $data['follow_redirects'] = true;
            $data['custom_headers'] = null;
            $data['auth_username'] = null;
            $data['auth_password'] = null;
            $data['expected_status_code'] = $data['type'] === Monitor::TYPE_HTTP ? 200 : null;
            $data['latency_threshold_ms'] = 1500;
            $data['degraded_consecutive_checks'] = 3;
            $data['critical_alert_after_minutes'] = 30;
            $data['region'] = 'North America';
            $data['contact_ids'] = [];
        }

        if (! in_array($data['type'], [Monitor::TYPE_HTTP], true)) {
            $data['follow_redirects'] = true;
            $data['custom_headers'] = null;
            $data['auth_username'] = null;
            $data['auth_password'] = null;
        }

        if ($data['type'] !== Monitor::TYPE_HTTP) {
            $data['request_method'] = null;
            $data['expected_status_code'] = null;
        }

        $data['expected_keyword'] = null;
        $data['keyword_match_type'] = null;

        if ($data['type'] !== Monitor::TYPE_PING) {
            $data['packet_count'] = null;
        } else {
            $data['packet_count'] = $data['packet_count'] ?? 1;
        }

        if (! in_array($data['type'], [Monitor::TYPE_HTTP, Monitor::TYPE_PORT, Monitor::TYPE_PING], true)) {
            $data['latency_threshold_ms'] = null;
            $data['degraded_consecutive_checks'] = null;
        } else {
            $data['degraded_consecutive_checks'] = $data['degraded_consecutive_checks'] ?? 3;
        }

        if ($data['type'] !== Monitor::TYPE_HTTP) {
            $data['ssl_threshold_days'] = null;
            $data['domain_threshold_days'] = $data['domain_threshold_days'] ?? 30;
        } else {
            $data['domain_threshold_days'] = $data['domain_threshold_days'] ?? 30;
            $data['ssl_threshold_days'] = $data['ssl_threshold_days'] ?? 21;
        }

        $data['heartbeat_grace_seconds'] = null;
        $data['synthetic_steps'] = null;

        return $data;
    }

    /**
     * @return array<int, int>
     */
    public function contactIds(): array
    {
        $workspace = app(WorkspaceResolver::class)->current($this);

        if (! $workspace->allowsAdvancedWorkspaceFeatures()) {
            return [];
        }

        return array_map('intval', $this->validated('contact_ids', []));
    }

    /**
     * @return array<int, string>
     */
    public function capabilityNames(): array
    {
        $workspace = app(WorkspaceResolver::class)->current($this);

        if (! $workspace->allowsAdvancedWorkspaceFeatures()) {
            return [];
        }

        return collect(preg_split('/[\r\n,]+/', (string) $this->validated('capability_names', '')) ?: [])
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->unique(fn (string $name) => mb_strtolower($name))
            ->take(12)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>|null
     */
    protected function decodeDowntimeWebhookUrls($workspace, ?Monitor $monitor, ?string $rawUrls): ?array
    {
        $existingUrls = collect($monitor?->downtime_webhook_urls ?? [])
            ->map(fn (mixed $url) => trim((string) $url))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (! $this->has('downtime_webhook_urls') && $monitor !== null) {
            return $existingUrls === [] ? null : $existingUrls;
        }

        $urls = collect(preg_split('/\r\n|\r|\n/', trim((string) $rawUrls)) ?: [])
            ->map(fn (string $url) => trim($url))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (count($urls) > 5) {
            throw ValidationException::withMessages([
                'downtime_webhook_urls' => 'Add up to 5 downtime webhook URLs per monitor.',
            ]);
        }

        foreach ($urls as $index => $url) {
            $validatedUrl = filter_var($url, FILTER_VALIDATE_URL);
            $scheme = parse_url($url, PHP_URL_SCHEME);

            if (! $validatedUrl || ! in_array($scheme, ['http', 'https'], true)) {
                throw ValidationException::withMessages([
                    'downtime_webhook_urls' => sprintf(
                        'Downtime webhook URL %d must be a valid HTTP or HTTPS URL.',
                        $index + 1,
                    ),
                ]);
            }
        }

        if (! $workspace->supportsDowntimeWebhooks()) {
            if ($urls !== $existingUrls && $urls !== []) {
                throw ValidationException::withMessages([
                    'downtime_webhook_urls' => 'Downtime webhook URLs are available on Premium and Ultra workspaces only.',
                ]);
            }

            return $existingUrls === [] ? null : $existingUrls;
        }

        return $urls === [] ? null : $urls;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    protected function decodeSyntheticSteps(string $steps, string $type): ?array
    {
        if ($type !== Monitor::TYPE_SYNTHETIC) {
            return null;
        }

        $steps = trim($steps);

        if ($steps === '') {
            throw ValidationException::withMessages([
                'synthetic_steps' => 'Synthetic transaction steps are required.',
            ]);
        }

        try {
            $decoded = json_decode($steps, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw ValidationException::withMessages([
                'synthetic_steps' => 'Synthetic steps must be valid JSON.',
            ]);
        }

        if (! is_array($decoded) || $decoded === []) {
            throw ValidationException::withMessages([
                'synthetic_steps' => 'Provide at least one synthetic transaction step.',
            ]);
        }

        if (count($decoded) > 10) {
            throw ValidationException::withMessages([
                'synthetic_steps' => 'Synthetic monitors can contain up to 10 steps.',
            ]);
        }

        return array_map(function (mixed $step, int $index): array {
            if (! is_array($step)) {
                throw ValidationException::withMessages([
                    'synthetic_steps' => sprintf('Synthetic step %d must be an object.', $index + 1),
                ]);
            }

            $url = trim((string) ($step['url'] ?? ''));
            $method = strtoupper(trim((string) ($step['method'] ?? 'GET')));
            $expectedStatusCode = $step['expected_status_code'] ?? 200;
            $expectedKeyword = isset($step['expected_keyword']) ? trim((string) $step['expected_keyword']) : null;
            $headers = $step['headers'] ?? null;

            if ($url === '') {
                throw ValidationException::withMessages([
                    'synthetic_steps' => sprintf('Synthetic step %d must include a URL or path.', $index + 1),
                ]);
            }

            if (! in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                throw ValidationException::withMessages([
                    'synthetic_steps' => sprintf('Synthetic step %d uses an unsupported HTTP method.', $index + 1),
                ]);
            }

            if (! is_int($expectedStatusCode) || $expectedStatusCode < 100 || $expectedStatusCode > 599) {
                throw ValidationException::withMessages([
                    'synthetic_steps' => sprintf('Synthetic step %d must use a valid expected status code.', $index + 1),
                ]);
            }

            if ($headers !== null && ! is_array($headers)) {
                throw ValidationException::withMessages([
                    'synthetic_steps' => sprintf('Synthetic step %d headers must be an object.', $index + 1),
                ]);
            }

            return [
                'name' => trim((string) ($step['name'] ?? '')) ?: sprintf('Step %d', $index + 1),
                'method' => $method,
                'url' => $url,
                'headers' => $headers,
                'body' => $step['body'] ?? null,
                'expected_status_code' => $expectedStatusCode,
                'expected_keyword' => $expectedKeyword ?: null,
            ];
        }, $decoded, array_keys($decoded));
    }

    protected function normalizePortTarget(string $target): string
    {
        $target = trim($target);

        if ($target === '') {
            throw ValidationException::withMessages([
                'target' => 'Enter a host and port in the format host:port.',
            ]);
        }

        if (preg_match('/^\[([^\]]+)\]:(\d+)$/', $target, $matches) === 1) {
            $host = $matches[1];
            $port = (int) $matches[2];
        } else {
            $lastColon = strrpos($target, ':');

            if ($lastColon === false) {
                throw ValidationException::withMessages([
                    'target' => 'Port monitors require a host and port in the format host:port.',
                ]);
            }

            $host = trim(substr($target, 0, $lastColon));
            $port = (int) trim(substr($target, $lastColon + 1));
        }

        if ($host === '' || $port < 1 || $port > 65535) {
            throw ValidationException::withMessages([
                'target' => 'Use a valid host and a TCP port between 1 and 65535.',
            ]);
        }

        return str_contains($host, ':') ? sprintf('[%s]:%d', $host, $port) : sprintf('%s:%d', $host, $port);
    }
}

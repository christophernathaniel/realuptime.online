<?php

namespace App\Http\Requests\Monitoring;

use App\Models\WorkspaceIntegration;
use App\Services\Security\PublicNetworkGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class UpsertWorkspaceIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        /** @var WorkspaceIntegration|null $workspaceIntegration */
        $workspaceIntegration = $this->route('workspaceIntegration');

        return [
            'provider' => [
                $workspaceIntegration ? 'nullable' : 'required',
                'string',
                Rule::in([
                    WorkspaceIntegration::PROVIDER_WEBHOOK,
                    WorkspaceIntegration::PROVIDER_SLACK,
                ]),
            ],
            'name' => ['required', 'string', 'max:255'],
            'enabled' => ['nullable', 'boolean'],
            'webhook_url' => array_values(array_filter([
                $workspaceIntegration ? 'nullable' : 'required',
                'string',
                'max:2048',
                'url:http,https',
            ])),
            'events' => ['nullable', 'array', 'min:1'],
            'events.*' => [
                'string',
                Rule::in([
                    'monitor.down',
                    'monitor.recovered',
                ]),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function integrationData(): array
    {
        /** @var WorkspaceIntegration|null $workspaceIntegration */
        $workspaceIntegration = $this->route('workspaceIntegration');
        $provider = $workspaceIntegration?->provider ?? $this->validated('provider');
        $existingConfig = $workspaceIntegration?->config ?? [];
        $webhookUrl = trim((string) ($this->validated('webhook_url') ?? ''));
        $webhookUrl = $webhookUrl !== '' ? $webhookUrl : (string) ($existingConfig['webhook_url'] ?? '');
        $events = collect($this->validated('events') ?? ['monitor.down', 'monitor.recovered'])
            ->filter(fn (mixed $event) => is_string($event) && $event !== '')
            ->unique()
            ->values()
            ->all();

        try {
            if ($provider === WorkspaceIntegration::PROVIDER_SLACK) {
                app(PublicNetworkGuard::class)->validateSlackWebhookUrl($webhookUrl);
            } else {
                app(PublicNetworkGuard::class)->validateHttpUrl($webhookUrl);
            }
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'webhook_url' => $exception->getMessage(),
            ]);
        }

        return [
            'provider' => $provider,
            'name' => $this->validated('name'),
            'status' => $this->boolean('enabled', true)
                ? WorkspaceIntegration::STATUS_ACTIVE
                : WorkspaceIntegration::STATUS_DISABLED,
            'config' => [
                ...$existingConfig,
                'webhook_url' => $webhookUrl,
            ],
            'scopes' => $events,
        ];
    }
}

<?php

namespace App\Http\Requests\Monitoring;

use App\Support\WorkspaceResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertMaintenanceWindowRequest extends FormRequest
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
        $workspaceOwnerId = app(WorkspaceResolver::class)->current($this)->id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:1000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'notify_contacts' => ['nullable', 'boolean'],
            'monitor_ids' => ['required', 'array', 'min:1'],
            'monitor_ids.*' => [
                'integer',
                Rule::exists('monitors', 'id')->where(fn ($query) => $query->where('user_id', $workspaceOwnerId)),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function maintenanceData(): array
    {
        return [
            'title' => $this->validated('title'),
            'message' => $this->validated('message'),
            'starts_at' => $this->validated('starts_at'),
            'ends_at' => $this->validated('ends_at'),
            'notify_contacts' => $this->boolean('notify_contacts'),
        ];
    }

    /**
     * @return array<int, int>
     */
    public function monitorIds(): array
    {
        return array_map('intval', $this->validated('monitor_ids', []));
    }
}

<?php

namespace App\Http\Requests\Monitoring;

use App\Models\StatusPage;
use App\Support\WorkspaceResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpsertStatusPageRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug((string) ($this->input('slug') ?: $this->input('name'))),
        ]);
    }

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
        /** @var StatusPage|null $statusPage */
        $statusPage = $this->route('statusPage');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('status_pages', 'slug')
                    ->where(fn ($query) => $query->where('user_id', $workspaceOwnerId))
                    ->ignore($statusPage?->id),
            ],
            'headline' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'published' => ['nullable', 'boolean'],
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
    public function statusPageData(): array
    {
        return [
            'name' => $this->validated('name'),
            'slug' => $this->validated('slug'),
            'headline' => $this->validated('headline') ?: $this->validated('name'),
            'description' => $this->validated('description'),
            'published' => $this->boolean('published', true),
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

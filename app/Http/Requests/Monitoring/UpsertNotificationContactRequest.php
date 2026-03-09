<?php

namespace App\Http\Requests\Monitoring;

use App\Models\NotificationContact;
use App\Support\WorkspaceResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertNotificationContactRequest extends FormRequest
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
        /** @var NotificationContact|null $notificationContact */
        $notificationContact = $this->route('notificationContact');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email:rfc',
                'max:255',
                Rule::unique('notification_contacts', 'email')
                    ->where(fn ($query) => $query->where('user_id', $workspaceOwnerId))
                    ->ignore($notificationContact?->id),
            ],
            'enabled' => ['nullable', 'boolean'],
            'is_primary' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function contactData(): array
    {
        return [
            'name' => $this->validated('name'),
            'email' => $this->validated('email'),
            'enabled' => $this->boolean('enabled', true),
            'is_primary' => $this->boolean('is_primary'),
        ];
    }
}

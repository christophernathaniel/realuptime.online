<?php

namespace App\Http\Requests\Monitoring;

use App\Models\StatusPageIncident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStatusPageIncidentUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                StatusPageIncident::STATUS_INVESTIGATING,
                StatusPageIncident::STATUS_IDENTIFIED,
                StatusPageIncident::STATUS_MONITORING,
                StatusPageIncident::STATUS_RESOLVED,
            ])],
            'message' => ['required', 'string', 'max:5000'],
        ];
    }
}

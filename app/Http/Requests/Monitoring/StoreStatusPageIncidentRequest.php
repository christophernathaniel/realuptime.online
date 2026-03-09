<?php

namespace App\Http\Requests\Monitoring;

use App\Models\StatusPageIncident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStatusPageIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'status' => ['required', Rule::in([
                StatusPageIncident::STATUS_INVESTIGATING,
                StatusPageIncident::STATUS_IDENTIFIED,
                StatusPageIncident::STATUS_MONITORING,
                StatusPageIncident::STATUS_RESOLVED,
            ])],
            'impact' => ['required', Rule::in([
                StatusPageIncident::IMPACT_MINOR,
                StatusPageIncident::IMPACT_MAJOR,
                StatusPageIncident::IMPACT_CRITICAL,
            ])],
            'monitor_ids' => ['required', 'array', 'min:1'],
            'monitor_ids.*' => ['integer'],
        ];
    }
}

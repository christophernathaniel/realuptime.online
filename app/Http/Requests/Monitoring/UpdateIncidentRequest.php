<?php

namespace App\Http\Requests\Monitoring;

use Illuminate\Foundation\Http\FormRequest;

class UpdateIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'operator_notes' => ['nullable', 'string', 'max:5000'],
            'root_cause_summary' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Validator;

class PasswordUpdateRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $currentPasswordRules = $this->user()?->password_login_enabled
            ? $this->currentPasswordRules()
            : ['nullable', 'string'];

        return [
            'current_password' => $currentPasswordRules,
            'password' => $this->passwordRules(),
        ];
    }

    /**
     * @return array<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->user()?->password_login_enabled) {
                    return;
                }

                $confirmedAt = (int) $this->session()->get('auth.password_confirmed_at', 0);

                if (Date::now()->unix() - $confirmedAt > 900) {
                    $validator->errors()->add(
                        'current_password',
                        'Sign out and sign in with your connected provider again before setting a password.',
                    );
                }
            },
        ];
    }
}

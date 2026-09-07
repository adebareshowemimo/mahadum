<?php

namespace App\Http\Requests\Auth;

use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GoogleAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('phone')) && ($this->input('dial_code') === null || is_string($this->input('dial_code')))) {
            $this->merge([
                'phone' => Phone::normalize($this->input('phone'), $this->input('dial_code')) ?? $this->input('phone'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'id_token' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
            'account_type' => ['nullable', Rule::in(['individual', 'family', 'educator_school', 'institution'])],
            'organization_name' => ['required_if:account_type,educator_school,institution', 'nullable', 'string', 'max:255'],
            'dial_code' => ['nullable', 'string', 'max:6'],
            'phone' => ['required_with:account_type', 'nullable', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/', 'max:20', Rule::unique('users', 'phone')],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'referral_code' => ['nullable', 'string', 'max:50'],
        ];
    }
}

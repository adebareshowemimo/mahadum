<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GoogleAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_token' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
            'account_type' => ['nullable', Rule::in(['individual', 'family', 'educator_school', 'institution'])],
            'organization_name' => ['required_if:account_type,educator_school,institution', 'nullable', 'string', 'max:255'],
            'phone' => ['required_with:account_type', 'nullable', 'string', 'max:20'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'referral_code' => ['nullable', 'string', 'max:50'],
        ];
    }
}

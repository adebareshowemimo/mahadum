<?php

namespace App\Http\Requests\Auth;

use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public endpoint
    }

    /**
     * Normalise the phone to a canonical `+<country><subscriber>` string before
     * validation so `unique:users,phone` can't be defeated by formatting.
     */
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
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'dial_code' => ['nullable', 'string', 'max:6'],
            'phone' => ['required', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/', 'max:20', 'unique:users,phone'],
            'username' => ['nullable', 'string', 'alpha_dash', 'max:50', 'unique:users,username'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'device_name' => ['required', 'string', 'max:255'],
            // Public signup choices. parent/learner remain accepted for older
            // clients and class-invitation registration.
            'account_type' => ['nullable', 'in:individual,family,educator_school,institution,parent,learner'],
            'organization_name' => ['required_if:account_type,educator_school,institution', 'nullable', 'string', 'max:255'],
            'family_name' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'referral_code' => ['nullable', 'string', 'max:50'],
            'class_invitation_token' => ['nullable', 'string', 'size:64'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.unique' => 'That phone number is already registered to another account.',
            'email.unique' => 'That email address is already registered.',
        ];
    }

    public function accountType(): string
    {
        return $this->input('account_type', 'family');
    }
}

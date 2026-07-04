<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public endpoint
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'username' => ['nullable', 'string', 'alpha_dash', 'max:50', 'unique:users,username'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'device_name' => ['required', 'string', 'max:255'],
            // age-gate branch: parent (default) manages children; learner = adult self-learner
            'account_type' => ['nullable', 'in:parent,learner'],
            'family_name' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'referral_code' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function accountType(): string
    {
        return $this->input('account_type', 'parent');
    }
}

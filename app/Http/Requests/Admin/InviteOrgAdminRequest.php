<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteOrgAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route guard: can:organizations.manage
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            // Must be new — an existing account is handled via the Users directory.
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'A user with this email already exists — assign the school_admin role from the Users directory instead.',
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'username' => ['nullable', 'string', 'max:80', 'unique:users,username'],
            'phone' => ['nullable', 'string', 'max:20', 'unique:users,phone'],
            'locale' => ['nullable', 'string', 'max:10'],
            'status' => ['nullable', Rule::in(['active', 'suspended'])],
            'role' => ['required', Rule::in(['super_admin', 'content_owner', 'school_admin', 'teacher', 'supervisor', 'parent', 'student'])],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $role = (string) $this->input('role');
            $organizationId = $this->input('organization_id');

            if (in_array($role, ['school_admin', 'teacher', 'supervisor'], true) && ! $organizationId) {
                $validator->errors()->add('organization_id', 'Choose an organization for this role.');
            }

            if ($organizationId && in_array($role, ['super_admin', 'content_owner', 'parent'], true)) {
                $validator->errors()->add('organization_id', 'This global role cannot be assigned to an organization during account creation.');
            }
        });
    }
}

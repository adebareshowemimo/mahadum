<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Spatie\Permission\Models\Role;

class AssignRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route guard: can:roles.assign
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', 'exists:'.Role::class.',name'],
            'action' => ['required', 'in:assign,revoke'],
        ];
    }
}

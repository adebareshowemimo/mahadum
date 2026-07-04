<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

class StoreSchoolClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route guard: can:create/update,SchoolClass (policy)
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'level' => ['nullable', 'string', 'max:100'],
            'teacher_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}

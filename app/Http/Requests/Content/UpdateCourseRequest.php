<?php

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route guard: can:update,course
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'level_band' => ['nullable', 'string', 'max:50'],
            'language_id' => ['sometimes', 'integer', 'exists:languages,id'],
        ];
    }
}

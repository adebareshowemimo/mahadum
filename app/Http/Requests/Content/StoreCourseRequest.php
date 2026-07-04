<?php

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;

class StoreCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route guard: can:create,Course
    }

    public function rules(): array
    {
        return [
            'language_id' => ['required', 'integer', 'exists:languages,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'level_band' => ['nullable', 'string', 'max:50'],
        ];
    }
}

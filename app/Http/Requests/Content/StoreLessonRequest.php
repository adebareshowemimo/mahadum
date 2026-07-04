<?php

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;

class StoreLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route guard: can:content.lessons.manage
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'integer', 'min:1'],
            'est_minutes' => ['nullable', 'integer', 'min:1'],
            'is_locked_by_default' => ['boolean'],
        ];
    }
}

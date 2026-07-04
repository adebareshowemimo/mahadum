<?php

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;

class StoreCourseLevelRequest extends FormRequest
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
            'has_assessment' => ['boolean'],
        ];
    }
}

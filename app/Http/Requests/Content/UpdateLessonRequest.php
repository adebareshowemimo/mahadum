<?php

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route guard: can:content.lessons.manage
    }

    public function rules(): array
    {
        $standalone = $this->route('lesson')?->course_level_id === null;

        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'kind' => ['prohibited'],
            'position' => ['sometimes', 'integer', 'min:1'],
            'est_minutes' => ['sometimes', 'integer', 'min:1'],
            'is_free_preview' => ['prohibited'],
            'is_locked_by_default' => ['boolean'],
            'language_id' => [$standalone ? 'sometimes' : 'prohibited', 'integer', 'exists:languages,id'],
            'standalone_level' => [$standalone ? 'sometimes' : 'prohibited', 'integer', 'between:1,10'],
            'summary' => [$standalone ? 'sometimes' : 'prohibited', 'nullable', 'string', 'max:1000'],
        ];
    }
}

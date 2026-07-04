<?php

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;

class ParseQuizImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route guard: can:content.lessons.manage
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:5120'],
        ];
    }
}

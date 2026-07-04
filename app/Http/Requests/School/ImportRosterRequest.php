<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

class ImportRosterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route guard: can:schools.roster.import
    }

    public function rules(): array
    {
        return [
            // Provide rows inline OR upload a CSV (display_name[,level]).
            'students' => ['required_without:file', 'array'],
            'students.*.display_name' => ['required_with:students', 'string', 'max:255'],
            'students.*.level' => ['nullable', 'string', 'max:100'],
            'students.*.class_id' => ['nullable', 'integer'],
            'file' => ['required_without:students', 'file', 'mimes:csv,txt', 'max:2048'],
            'class_id' => ['nullable', 'integer'], // default class for all rows
        ];
    }
}

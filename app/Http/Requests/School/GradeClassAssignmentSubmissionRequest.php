<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

class GradeClassAssignmentSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route guard: can:schools.assignments.review + controller ownership check
    }

    public function rules(): array
    {
        return [
            'passed' => ['required', 'boolean'],
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

class StoreClassAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route guard: can:schools.assignments.create + controller ownership check
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'due_at' => ['nullable', 'date'],
            'coin_reward' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ];
    }
}

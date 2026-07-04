<?php

namespace App\Http\Requests\Family;

use Illuminate\Foundation\Http\FormRequest;

class ReviewChoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:approve,reject,more_evidence'],
        ];
    }
}

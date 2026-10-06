<?php

namespace App\Http\Requests\Learning;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'learner_id' => ['required', 'integer', 'exists:learner_profiles,id'],
            'question_id' => ['required', 'integer', 'exists:questions,id'],
            'request_id' => ['required', 'string', 'max:128'],
            'answer' => ['required', 'array'], // {option_id} | {option_ids:[]} | {text}
            'time_ms' => ['nullable', 'integer', 'min:0'],
        ];
    }
}

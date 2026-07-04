<?php

namespace App\Http\Requests\Learning;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'learner_id' => ['required', 'integer', 'exists:learner_profiles,id'],
            'language_id' => ['required', 'integer', 'exists:languages,id'],
            'answers' => ['nullable', 'array'],
            'score' => ['nullable', 'numeric', 'between:0,1'], // optional pre-computed proficiency
        ];
    }
}

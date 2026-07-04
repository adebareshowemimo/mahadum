<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

class StoreClassAssignmentSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null; // ResolvesLearner::learner() authorizes against the specific profile
    }

    public function rules(): array
    {
        return [
            'learner_id' => ['required', 'integer', 'exists:learner_profiles,id'],
            'media' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime,audio/mpeg,audio/aac,audio/wav,audio/webm,audio/ogg', 'max:51200'],
        ];
    }
}

<?php

namespace App\Http\Requests\Learning;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssignmentSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'learner_id' => ['required', 'integer', 'exists:learner_profiles,id'],
            'component_id' => ['required', 'integer', 'exists:lesson_components,id'],
            // A short recorded clip — video or audio, up to 50 MB.
            'media' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime,audio/mpeg,audio/aac,audio/wav,audio/webm,audio/ogg', 'max:51200'],
        ];
    }
}

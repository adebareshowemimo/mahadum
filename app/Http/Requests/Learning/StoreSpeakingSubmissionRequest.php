<?php

namespace App\Http\Requests\Learning;

use Illuminate\Foundation\Http\FormRequest;

class StoreSpeakingSubmissionRequest extends FormRequest
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
            'audio' => ['nullable', 'file', 'mimetypes:audio/mpeg,audio/aac,audio/wav,audio/webm,audio/ogg', 'max:10240'],
        ];
    }
}

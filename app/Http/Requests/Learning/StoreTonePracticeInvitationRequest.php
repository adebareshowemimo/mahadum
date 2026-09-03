<?php

namespace App\Http\Requests\Learning;

use Illuminate\Foundation\Http\FormRequest;

class StoreTonePracticeInvitationRequest extends FormRequest
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
            'recipient_email' => ['required', 'email:rfc', 'max:255', 'exists:users,email'],
        ];
    }
}

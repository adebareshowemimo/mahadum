<?php

namespace App\Http\Requests\Family;

use Illuminate\Foundation\Http\FormRequest;

class AddChildRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'age_band' => ['nullable', 'string', 'max:50'],
            'target_language_id' => ['nullable', 'integer', 'exists:languages,id'],
            // Verifiable parental consent (COPPA / NDPA) — the guardian must
            // affirmatively consent to creating and processing the child's data.
            'consent' => ['accepted'],
        ];
    }
}

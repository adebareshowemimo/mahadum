<?php

namespace App\Http\Requests\Family;

use Illuminate\Foundation\Http\FormRequest;

class StoreChoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee_learner_profile_id' => ['required', 'integer', 'exists:learner_profiles,id'],
            'coin_reward' => ['required', 'integer', 'min:0'],
            'due_at' => ['nullable', 'date'],
        ];
    }
}

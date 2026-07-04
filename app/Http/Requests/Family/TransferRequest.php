<?php

namespace App\Http\Requests\Family;

use Illuminate\Foundation\Http\FormRequest;

class TransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'to_learner_id' => ['required', 'integer', 'exists:learner_profiles,id'],
            'coins' => ['required', 'integer', 'min:1'],
        ];
    }
}

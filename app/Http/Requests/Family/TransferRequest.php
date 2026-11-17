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
            'coins' => ['required_without:amount_minor', 'prohibits:amount_minor', 'integer', 'min:1'],
            'amount_minor' => ['required_without:coins', 'prohibits:coins', 'integer', 'min:1'],
        ];
    }
}

<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

class TelcoSubscribeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'msisdn' => ['required', 'string', 'regex:/^\+?[0-9]{10,15}$/'],
            'operator' => ['required', 'in:mtn,airtel,glo,t2'],
        ];
    }
}

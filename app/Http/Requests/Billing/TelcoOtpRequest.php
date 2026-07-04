<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

class TelcoOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'msisdn' => ['required', 'string', 'regex:/^\+?[0-9]{10,15}$/'],
            'operator' => ['required', 'in:mtn,airtel,glo,t2'],
        ];
    }
}

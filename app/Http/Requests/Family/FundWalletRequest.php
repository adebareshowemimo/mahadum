<?php

namespace App\Http\Requests\Family;

use Illuminate\Foundation\Http\FormRequest;

class FundWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'], // minor units (kobo)
            'gateway' => ['required', 'in:flutterwave,monnify,paystack'],
        ];
    }
}

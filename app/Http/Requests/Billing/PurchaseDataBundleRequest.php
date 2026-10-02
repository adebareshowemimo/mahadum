<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseDataBundleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'biller_code' => ['required', 'string', 'max:255'],
            'product_code' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'regex:/^0[789][01][0-9]{8}$/'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'consent' => ['accepted'],
        ];
    }
}

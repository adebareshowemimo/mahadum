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
            'operator' => ['required', 'in:mtn,airtel,glo,t2'],
            'bundle_mb' => ['required', 'integer', 'min:1'],
            'consent' => ['accepted'], // explicit carrier-billing consent
        ];
    }
}

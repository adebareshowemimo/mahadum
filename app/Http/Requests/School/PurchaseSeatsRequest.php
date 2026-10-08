<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseSeatsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['purchase_request_key' => $this->header('Idempotency-Key')]);
    }

    public function authorize(): bool
    {
        return true; // route guard: can:schools.seats.purchase
    }

    public function rules(): array
    {
        return [
            'purchase_request_key' => ['required', 'string', 'min:8', 'max:128'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'term_label' => ['nullable', 'string', 'max:100'],
            'auto_renew' => ['boolean'],
            // Annual registration fee is charged by default; a top-up within the
            // same academic year can opt out.
            'include_registration' => ['boolean'],
        ];
    }
}

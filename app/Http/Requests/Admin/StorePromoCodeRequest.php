<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePromoCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route guard: can:promocodes.manage
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:promo_codes,code'],
            'discount_type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'integer', 'min:1'],
            'applicable_tier' => ['nullable', 'string', 'max:50'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

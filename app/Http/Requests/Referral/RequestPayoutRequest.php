<?php

namespace App\Http\Requests\Referral;

use App\Services\Settings;
use Illuminate\Foundation\Http\FormRequest;

class RequestPayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $floor = (int) app(Settings::class)->get('referral.payout_floor_minor', 500_000);

        return [
            'amount_minor' => ['required', 'integer', 'min:'.$floor],
            'method' => ['required', 'in:bank,coins'],
        ];
    }

    public function messages(): array
    {
        $floor = (int) app(Settings::class)->get('referral.payout_floor_minor', 500_000);

        return [
            'amount_minor.min' => 'The minimum payout is ₦'.number_format($floor / 100).'.',
        ];
    }
}

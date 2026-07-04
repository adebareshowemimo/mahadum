<?php

namespace App\Http\Requests\School;

use App\Services\Settings;
use Illuminate\Foundation\Http\FormRequest;

class RequestTeacherCompensationPayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null; // route guard: can:payouts.request
    }

    public function rules(): array
    {
        // Reuses the referral payout floor/cap — one set of admin-tunable
        // payout limits across every payout source, rather than a parallel
        // "teaching" floor/cap that would need its own settings UI.
        $floor = (int) app(Settings::class)->get('referral.payout_floor_minor', 500_000);

        return [
            'amount_minor' => ['required', 'integer', 'min:'.$floor],
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

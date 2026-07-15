<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

class AwardClassBadgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route guard: can:schools.badges.award + own-class check in the controller
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'badge_code' => ['required', 'string', 'exists:badges,code'],
        ];
    }
}

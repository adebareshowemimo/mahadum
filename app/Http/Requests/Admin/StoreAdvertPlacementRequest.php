<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdvertPlacementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route guard: can:adverts.manage
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', Rule::in(['leaderboard', 'inline', 'profile_data_topup'])],
            'size' => ['nullable', 'string', 'max:50'],
            'media_asset_id' => ['required', 'integer', 'exists:media_assets,id'],
            'target_url' => ['required', 'url', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ];
    }
}

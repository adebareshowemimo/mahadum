<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClassLearnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'learner_id' => [
                'required',
                'integer',
                Rule::exists('learner_profiles', 'id')->where(
                    fn ($query) => $query->where('organization_id', app('currentTenantId'))->whereNull('deleted_at'),
                ),
            ],
        ];
    }
}

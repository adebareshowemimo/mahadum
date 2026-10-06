<?php

namespace App\Http\Requests\School;

class UpdateSchoolClassRequest extends StoreSchoolClassRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        // Partial updates preserve the existing name, but an explicit name must be valid.
        $rules['name'] = ['sometimes', 'required', 'string', 'max:255'];

        return $rules;
    }
}

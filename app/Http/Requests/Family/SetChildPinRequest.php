<?php

namespace App\Http\Requests\Family;

use Illuminate\Foundation\Http\FormRequest;

class SetChildPinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** `pin: null` clears the child's PIN (and un-protects the profile). */
    public function rules(): array
    {
        return [
            'pin' => ['present', 'nullable', 'string', 'regex:/^\d{4,8}$/'],
        ];
    }
}

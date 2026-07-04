<?php

namespace App\Http\Requests\Family;

use Illuminate\Foundation\Http\FormRequest;

class SetPinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'pin' => ['required', 'string', 'regex:/^\d{4,8}$/'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SwitchProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Record-level authorization is handled by the route's can:view,learner guard.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'pin' => ['nullable', 'string', 'min:4', 'max:8'],
        ];
    }
}

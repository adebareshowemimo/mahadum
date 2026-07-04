<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string', 'max:255'], // stable per install
            'platform' => ['required', 'in:ios,android,web'],
            'push_token' => ['nullable', 'string', 'max:512'],
        ];
    }
}

<?php

namespace App\Http\Requests\Referral;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SendReferralInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'channel' => ['required', 'in:email,phone'],
            'contact' => ['required', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('channel') === 'email' && ! filter_var($this->input('contact'), FILTER_VALIDATE_EMAIL)) {
                $validator->errors()->add('contact', 'Enter a valid email address.');
            }
            if ($this->input('channel') === 'phone' && preg_match_all('/\d/', (string) $this->input('contact')) < 7) {
                $validator->errors()->add('contact', 'Enter a valid phone number.');
            }
        });
    }
}

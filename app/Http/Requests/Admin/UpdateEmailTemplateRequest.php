<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route guard: can:emails.templates.manage
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'greeting' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'action_text' => ['nullable', 'string', 'max:100', 'required_with:action_url'],
            'action_url' => ['nullable', 'string', 'max:500', 'required_with:action_text'],
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'content_mode' => ['required', Rule::in(['structured', 'html'])],
            'include_branding' => ['required', 'boolean'],
            'greeting' => ['nullable', 'string', 'max:255'],
            'body' => [Rule::requiredIf($this->input('content_mode') === 'structured'), 'nullable', 'string', 'max:10000'],
            'html_body' => [Rule::requiredIf($this->input('content_mode') === 'html'), 'nullable', 'string', 'max:100000'],
            'action_text' => ['nullable', 'string', 'max:100', 'required_with:action_url'],
            'action_url' => ['nullable', 'string', 'max:500', 'required_with:action_text'],
        ];
    }
}

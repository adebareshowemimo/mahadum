<?php

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMediaAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:50', 'distinct'],
            'folder' => ['nullable', 'string', 'max:255', 'regex:/^[^<>:"|?*\\x00-\\x1F]+$/'],
        ];
    }
}

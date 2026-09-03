<?php

namespace App\Http\Requests\Learning;

use App\Models\LearnerProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLearnerAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route guard: can:update,learner.
    }

    public function rules(): array
    {
        return [
            'avatar_id' => [
                'required_without:photo',
                Rule::prohibitedIf(fn () => $this->hasFile('photo')),
                'integer',
                Rule::in(LearnerProfile::AVATAR_IDS),
            ],
            'photo' => [
                'required_without:avatar_id',
                Rule::prohibitedIf(fn () => $this->filled('avatar_id')),
                'file',
                'max:5120',
                'mimetypes:image/jpeg,image/png,image/webp',
                'dimensions:min_width=96,min_height=96,max_width=4096,max_height=4096',
            ],
        ];
    }
}

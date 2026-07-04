<?php

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;

class UploadMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // Local-disk upload for now; production swaps in a managed video vendor.
            // PHP upload_max_filesize / post_max_size still apply at the server level.
            'file' => [
                'required',
                'file',
                'max:102400', // 100 MB
                'mimetypes:video/mp4,video/webm,video/ogg,video/quicktime,audio/mpeg,audio/aac,audio/wav,audio/ogg,image/jpeg,image/png,image/webp',
            ],
        ];
    }
}

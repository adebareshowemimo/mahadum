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
                'max:307200', // 300 MB (Laravel measures file sizes in KiB)
                // Some phones and video editors produce valid MP4 files that
                // PHP fileinfo reports as application/octet-stream. Require a
                // known media extension as a second check before allowing that
                // generic MIME type; executable/script extensions still fail.
                'extensions:mp4,m4v,webm,ogv,mov,mp3,m4a,aac,wav,ogg,oga,jpg,jpeg,png,webp',
                'mimetypes:video/mp4,video/x-m4v,application/mp4,video/webm,video/ogg,video/quicktime,video/x-quicktime,audio/mpeg,audio/mp4,audio/x-m4a,audio/aac,audio/x-aac,audio/wav,audio/x-wav,audio/webm,audio/ogg,application/ogg,image/jpeg,image/png,image/webp,application/octet-stream',
            ],
            // Browser directory uploads send the relative parent path separately.
            // Keep it display-only: stored files retain UUID names on the public disk.
            'folder' => ['nullable', 'string', 'max:255', 'regex:/^[^<>:"|?*\\x00-\\x1F]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.extensions' => 'Choose an MP4, WebM, MOV, supported audio file, or supported image.',
            'file.mimetypes' => 'The selected file is not a supported video, audio, or image.',
        ];
    }
}

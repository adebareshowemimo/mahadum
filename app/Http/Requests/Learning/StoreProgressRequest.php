<?php

namespace App\Http\Requests\Learning;

use Illuminate\Foundation\Http\FormRequest;

class StoreProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'learner_id' => ['required', 'integer', 'exists:learner_profiles,id'],
            'component_id' => ['required', 'integer', 'exists:lesson_components,id'],
            'watched_seconds' => ['nullable', 'integer', 'min:0'],
            'completed' => ['boolean'],

            // Video tracking (xAPI Video Profile). Deltas accumulate server-side;
            // position/duration are absolute. `event` drives which verb is emitted.
            'event' => ['nullable', 'string', 'in:played,paused,seeked,heartbeat,completed'],
            'watched_delta' => ['nullable', 'numeric', 'min:0'],
            'play_delta' => ['nullable', 'integer', 'min:0'],
            'position_seconds' => ['nullable', 'numeric', 'min:0'],
            'duration_seconds' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}

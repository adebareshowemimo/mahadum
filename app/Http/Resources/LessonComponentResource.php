<?php

namespace App\Http\Resources;

use App\Models\LessonComponent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LessonComponent
 */
class LessonComponentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'position' => $this->position,
            'title' => $this->title,
            'is_required' => (bool) $this->is_required,
            'xp_value' => $this->xp_value,
            'settings' => $this->settings,
            'detail' => $this->detail(),
        ];
    }

    /**
     * The typed detail row for this component (CMS/authoring view — full data,
     * including is_correct. The learner-facing /play payload strips answers).
     */
    private function detail(): ?array
    {
        return match ($this->type) {
            'video' => $this->whenLoaded('video', fn () => $this->video ? [
                'title' => $this->video->title,
                'presenter_name' => $this->video->presenter_name,
                'duration_seconds' => $this->video->duration_seconds,
                'default_quality' => $this->video->default_quality,
                'status' => $this->video->status,
                'source_type' => $this->video->source_type,
                'external_url' => $this->video->external_url,
                // Playable URL for the CMS preview (null until a source is attached).
                'src' => $this->video->relationLoaded('sourceAsset') && $this->video->sourceAsset
                    ? url('storage/'.$this->video->sourceAsset->url)
                    : null,
            ] : null),
            'quiz' => $this->whenLoaded('quiz', fn () => $this->quiz ? [
                'pass_threshold' => (float) $this->quiz->pass_threshold,
                'hearts_enabled' => (bool) $this->quiz->hearts_enabled,
                'questions' => $this->quiz->relationLoaded('questions')
                    ? $this->quiz->questions->map(fn ($q) => [
                        'id' => $q->id,
                        'type' => $q->type,
                        'prompt' => $q->prompt,
                        'target_text' => $q->target_text,
                        'prompt_audio_asset_id' => $q->prompt_audio_asset_id,
                        'prompt_image_asset_id' => $q->prompt_image_asset_id,
                        'prompt_audio' => $q->relationLoaded('promptAudioAsset') && $q->promptAudioAsset
                            ? url('storage/'.$q->promptAudioAsset->url)
                            : null,
                        'prompt_image' => $q->relationLoaded('promptImageAsset') && $q->promptImageAsset
                            ? url('storage/'.$q->promptImageAsset->url)
                            : null,
                        'options' => $q->relationLoaded('options')
                            ? $q->options->map(fn ($o) => [
                                'id' => $o->id,
                                'label' => $o->label,
                                'is_correct' => (bool) $o->is_correct,
                                'match_target' => $o->match_target,
                            ])->values()
                            : [],
                    ])->values()
                    : [],
            ] : null),
            'speaking' => $this->whenLoaded('speakingPrompt', fn () => $this->speakingPrompt ? [
                'prompt_text' => $this->speakingPrompt->prompt_text,
                'target_text' => $this->speakingPrompt->target_text,
            ] : null),
            'assignment' => $this->whenLoaded('assignment', fn () => $this->assignment ? [
                'prompt' => $this->assignment->prompt,
                'expected_media' => $this->assignment->expected_media,
                'max_duration_seconds' => $this->assignment->max_duration_seconds,
                'coin_reward' => $this->assignment->coin_reward,
            ] : null),
            'exercise' => $this->whenLoaded('exercise', fn () => $this->exercise ? [
                'mode' => $this->exercise->mode,
                'cards' => $this->exercise->relationLoaded('flashcards')
                    ? $this->exercise->flashcards->map(fn ($f) => [
                        'id' => $f->id,
                        'front_text' => $f->front_text,
                        'back_text' => $f->back_text,
                        'mnemonic' => $f->mnemonic,
                        'audio_asset_id' => $f->audio_asset_id,
                        'audio' => $f->relationLoaded('audioAsset') && $f->audioAsset
                            ? url('storage/'.$f->audioAsset->url)
                            : null,
                    ])->values()
                    : [],
            ] : null),
            'game' => $this->whenLoaded('game', fn () => $this->game ? [
                'game_type' => $this->game->game_type,
                'config' => $this->game->config,
            ] : null),
            default => null,
        };
    }
}

<?php

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;

class StoreLessonComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route guard: can:content.lessons.manage
    }

    public function rules(): array
    {
        return [
            // base (the ordered sequence row). speaking + assignment (learner
            // recording + review) are deferred to v2 and cannot be created.
            'type' => ['required', 'in:video,quiz,exercise,game'],
            'position' => ['nullable', 'integer', 'min:1'],
            'title' => ['nullable', 'string', 'max:255'],
            'is_required' => ['boolean'],
            'xp_value' => ['nullable', 'integer', 'min:0'],
            'settings' => ['nullable', 'array'],

            // type=video
            'video' => ['required_if:type,video', 'array'],
            'video.title' => ['required_if:type,video', 'string', 'max:255'],
            'video.presenter_name' => ['nullable', 'string', 'max:255'],
            'video.duration_seconds' => ['nullable', 'integer', 'min:1'],
            'video.default_quality' => ['nullable', 'in:240p,360p,720p'],
            'video.status' => ['nullable', 'in:uploading,processing,ready,failed'],
            'video.language_id' => ['nullable', 'integer', 'exists:languages,id'],
            'video.source_asset_id' => ['nullable', 'integer', 'exists:media_assets,id'],

            // type=quiz
            'quiz' => ['required_if:type,quiz', 'array'],
            'quiz.title' => ['nullable', 'string', 'max:255'],
            'quiz.pass_threshold' => ['nullable', 'numeric', 'between:0,1'],
            'quiz.shuffle_questions' => ['boolean'],
            'quiz.max_attempts' => ['nullable', 'integer', 'min:1'],
            'quiz.hearts_enabled' => ['boolean'],
            'quiz.questions' => ['nullable', 'array'],
            'quiz.questions.*.type' => ['required_with:quiz.questions', 'string', 'max:50'],
            'quiz.questions.*.prompt' => ['required_with:quiz.questions', 'string'],
            'quiz.questions.*.explanation' => ['nullable', 'string'],
            'quiz.questions.*.target_text' => ['nullable', 'string'],
            'quiz.questions.*.prompt_audio_asset_id' => ['nullable', 'integer', 'exists:media_assets,id'],
            'quiz.questions.*.prompt_image_asset_id' => ['nullable', 'integer', 'exists:media_assets,id'],
            'quiz.questions.*.points' => ['nullable', 'integer', 'min:1'],
            'quiz.questions.*.options' => ['nullable', 'array'],
            'quiz.questions.*.options.*.label' => ['required_with:quiz.questions.*.options', 'string'],
            'quiz.questions.*.options.*.is_correct' => ['boolean'],
            'quiz.questions.*.options.*.match_target' => ['nullable', 'string'],

            // type=exercise (flashcard deck)
            'exercise' => ['required_if:type,exercise', 'array'],
            'exercise.mode' => ['nullable', 'string', 'max:50'],
            'exercise.cards' => ['nullable', 'array'],
            'exercise.cards.*.front_text' => ['required_with:exercise.cards', 'string'],
            'exercise.cards.*.back_text' => ['required_with:exercise.cards', 'string'],
            'exercise.cards.*.mnemonic' => ['nullable', 'string'],
            'exercise.cards.*.audio_asset_id' => ['nullable', 'integer', 'exists:media_assets,id'],
            'exercise.cards.*.image_asset_id' => ['nullable', 'integer', 'exists:media_assets,id'],

            // type=game
            'game' => ['required_if:type,game', 'array'],
            'game.game_type' => ['required_if:type,game', 'in:memory,match,tone_pop,word_builder'],
            'game.config' => ['nullable', 'array'],
            'game.config.pairs' => ['nullable', 'array'],
            'game.config.pairs.*.a' => ['required_with:game.config.pairs', 'string'],
            'game.config.pairs.*.b' => ['required_with:game.config.pairs', 'string'],
        ];
    }
}

<?php

namespace App\Services\Learning;

use App\Models\Question;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class PlacementQuestionBank
{
    /** Use published course questions so creators can manage placement content in the existing quiz editor. */
    public function forLanguage(int $languageId): Collection
    {
        $questions = Question::with(['options', 'promptAudioAsset'])
            ->whereIn('type', ['mcq_single', 'listen_and_respond'])
            ->whereHas('quiz.lessonComponent.lesson', fn ($query) => $query->whereNotNull('published_at')
                ->whereHas('courseLevel.course', fn ($course) => $course->where('language_id', $languageId)->where('is_published', true)))
            ->whereHas('options', fn ($query) => $query->where('is_correct', true))
            ->orderBy('id')->get()
            ->filter(fn (Question $question) => $question->options->count() >= 2);

        $audio = $questions->filter(fn (Question $question) => $question->promptAudioAsset !== null
            && Storage::disk('public')->exists($question->promptAudioAsset->url))->take(2);
        $text = $questions->filter(fn (Question $question) => $question->prompt_audio_asset_id === null)->take(2);

        return $audio->count() === 2 && $text->count() === 2
            ? $audio->concat($text)->values() : collect();
    }
}

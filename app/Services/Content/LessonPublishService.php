<?php

namespace App\Services\Content;

use App\Models\Lesson;
use Illuminate\Support\Str;

/**
 * Enforces the lesson publish rule (Content Model §6 / Business Rule 1):
 * course lessons need a video and quiz; proverb and trivia lessons need at
 * least one text, video, quiz or flashcard format. Every video must be
 * `ready`, and every quiz must have ≥1 question with a valid correct-answer
 * configuration.
 *
 * The speaking-challenge requirement is deferred to v2 — speaking needs learner
 * recording + review, which ships later. Caption-presence and end-of-level
 * assessment checks are likewise deferred (noted, not silently skipped).
 */
class LessonPublishService
{
    /**
     * @return array<int, string> human-readable failures; empty = publishable.
     */
    public function failures(Lesson $lesson): array
    {
        $lesson->loadMissing(['components.video.captions', 'components.video.renditions', 'components.quiz.questions.options']);
        $components = $lesson->components;
        $failures = [];

        $requiredTypes = in_array($lesson->kind, ['proverb', 'trivia'], true) ? [] : ['video', 'quiz'];
        if (in_array($lesson->kind, ['proverb', 'trivia'], true)
            && blank($lesson->summary)
            && $components->whereIn('type', ['video', 'quiz', 'exercise'])->isEmpty()) {
            $failures[] = 'Add text, video, quiz, or flashcards before publishing this set.';
        }
        foreach ($requiredTypes as $required) {
            if ($components->where('type', $required)->isEmpty()) {
                $failures[] = "Lesson needs at least one {$required} component.";
            }
        }

        foreach ($components->where('type', 'video') as $component) {
            if (! $component->video) {
                $failures[] = "Video component #{$component->id} has no video record.";
            } elseif ($component->video->status !== 'ready') {
                $failures[] = "Video component #{$component->id} is not ready (status: {$component->video->status}).";
            } elseif (data_get($component->settings, 'require_watch', false)
                && $component->video->source_type === 'youtube') {
                $failures[] = "Video component #{$component->id} must be uploaded to verify full playback before continuing.";
            }
            if ($component->video?->is_cultural) {
                $video = $component->video;
                if (! data_get($component->settings, 'require_watch', false)) {
                    $failures[] = "Cultural video #{$component->id} must require full playback.";
                }
                if ($video->duration_seconds < 60 || $video->duration_seconds > 180) {
                    $failures[] = "Cultural video #{$component->id} must be 60–180 seconds long.";
                }
                if ($video->captions->pluck('language_code')->unique()->count() < 2) {
                    $failures[] = "Cultural video #{$component->id} needs captions in two languages.";
                }
                if ($video->source_type !== 'upload'
                    || $video->renditions->where('ready', true)->pluck('quality')->intersect(['240p', '360p', '720p'])->count() < 3) {
                    $failures[] = "Cultural video #{$component->id} needs ready 240p, 360p and 720p versions.";
                }
            }
        }

        foreach ($components->where('type', 'quiz') as $component) {
            $quiz = $component->quiz;
            if (! $quiz || $quiz->questions->isEmpty()) {
                $failures[] = "Quiz component #{$component->id} has no questions.";

                continue;
            }
            // Number questions the way the builder shows them (by position, 1-based)
            // instead of leaking the database id, so authors can find the culprit.
            foreach ($quiz->questions->sortBy('position')->values() as $index => $question) {
                if (! $this->questionHasValidAnswer($question)) {
                    $number = $index + 1;
                    $snippet = Str::limit(trim((string) $question->prompt), 50);
                    $label = $snippet === '' ? "Question {$number}" : "Question {$number} (\"{$snippet}\")";
                    $failures[] = "{$label} has no correct answer set.";
                }
            }
        }

        return $failures;
    }

    private function questionHasValidAnswer($question): bool
    {
        // Correctness is defined differently per type: match_pairs by the pair
        // targets, word_bank by the authored order, free-text/audio (e.g.
        // type_what_you_hear) by target_text, and the rest by a correct option.
        return match ($question->type) {
            'match_pairs' => $question->options->count() >= 2
                && $question->options->every(fn ($o) => filled($o->label) && filled($o->match_target)),
            'word_bank' => $question->options->count() >= 2,
            default => $question->options->isNotEmpty()
                ? $question->options->where('is_correct', true)->isNotEmpty()
                : filled($question->target_text),
        };
    }
}

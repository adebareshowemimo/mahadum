<?php

namespace App\Services\Content;

use App\Models\Lesson;

/**
 * Enforces the lesson publish rule (Content Model §6 / Business Rule 1):
 * a lesson cannot publish without ≥1 video + ≥1 quiz, every video must be
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
        $lesson->loadMissing(['components.video', 'components.quiz.questions.options']);
        $components = $lesson->components;
        $failures = [];

        foreach (['video', 'quiz'] as $required) {
            if ($components->where('type', $required)->isEmpty()) {
                $failures[] = "Lesson needs at least one {$required} component.";
            }
        }

        foreach ($components->where('type', 'video') as $component) {
            if (! $component->video) {
                $failures[] = "Video component #{$component->id} has no video record.";
            } elseif ($component->video->status !== 'ready') {
                $failures[] = "Video component #{$component->id} is not ready (status: {$component->video->status}).";
            }
        }

        foreach ($components->where('type', 'quiz') as $component) {
            $quiz = $component->quiz;
            if (! $quiz || $quiz->questions->isEmpty()) {
                $failures[] = "Quiz component #{$component->id} has no questions.";

                continue;
            }
            foreach ($quiz->questions as $question) {
                if (! $this->questionHasValidAnswer($question)) {
                    $failures[] = "Question #{$question->id} has no valid correct-answer configuration.";
                }
            }
        }

        return $failures;
    }

    private function questionHasValidAnswer($question): bool
    {
        // Option-based questions need ≥1 correct option; free-text/audio types
        // (e.g. type_what_you_hear, pronounce) are graded against target_text.
        if ($question->options->isNotEmpty()) {
            return $question->options->where('is_correct', true)->isNotEmpty();
        }

        return filled($question->target_text);
    }
}

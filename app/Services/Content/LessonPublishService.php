<?php

namespace App\Services\Content;

use App\Models\Lesson;
use Illuminate\Support\Str;

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

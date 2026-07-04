<?php

namespace App\Services\Learning;

use App\Models\Question;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Server-side grading. The client never receives is_correct at play time;
 * it submits an answer and the server returns the verdict + correct answer.
 *
 * Grading branches on the question type so that the *shape* of "correct" can
 * differ per type: a set of options (mcq_multi), an ordered list (word_bank),
 * per-item pairings (match_pairs), fuzzy text (type_what_you_hear), or a single
 * option (everything else that carries options).
 */
class AnswerGrader
{
    /**
     * @param  array<string,mixed>  $answer  e.g. {option_id}, {option_ids:[]}, {text}, {pairs:[{option_id,match_target}]}
     * @return array{is_correct:bool, correct_answer:array<string,mixed>, explanation:?string}
     */
    public function grade(Question $question, array $answer): array
    {
        $options = $question->relationLoaded('options') ? $question->options : $question->options()->get();

        return match ($question->type) {
            'mcq_multi' => $this->gradeMultiSelect($question, $options, $answer),
            'word_bank' => $this->gradeOrdered($question, $options, $answer),
            'match_pairs' => $this->gradeMatch($question, $options, $answer),
            'type_what_you_hear' => $this->gradeText($question, $answer),
            default => $this->gradeSingleOrText($question, $options, $answer),
        };
    }

    /** Single-select when options exist; otherwise fall back to free-text. */
    private function gradeSingleOrText(Question $question, Collection $options, array $answer): array
    {
        if ($options->isEmpty()) {
            return $this->gradeText($question, $answer);
        }

        $correctIds = $this->correctIds($options);
        $given = (int) ($answer['option_id'] ?? 0);

        return $this->optionResult($question, $correctIds->contains($given), $correctIds);
    }

    /** Every correct option and no others (order-independent). */
    private function gradeMultiSelect(Question $question, Collection $options, array $answer): array
    {
        $correctIds = $this->correctIds($options);
        $given = collect($answer['option_ids'] ?? [])->map(fn ($i) => (int) $i)->unique()->sort()->values();
        $isCorrect = $correctIds->isNotEmpty() && $given->all() === $correctIds->all();

        return $this->optionResult($question, $isCorrect, $correctIds);
    }

    /** The tiles arranged in exactly the authored order (position). */
    private function gradeOrdered(Question $question, Collection $options, array $answer): array
    {
        $correctOrder = $options->sortBy('position')->pluck('id')->map(fn ($i) => (int) $i)->values();
        $given = collect($answer['option_ids'] ?? [])->map(fn ($i) => (int) $i)->values();
        $isCorrect = $correctOrder->isNotEmpty() && $given->all() === $correctOrder->all();

        return [
            'is_correct' => $isCorrect,
            'correct_answer' => ['option_ids' => $correctOrder->all()],
            'explanation' => $question->explanation,
        ];
    }

    /** Each left option matched to its own match_target (accent-tolerant). */
    private function gradeMatch(Question $question, Collection $options, array $answer): array
    {
        /** @var Collection<int,string> $truth */
        $truth = $options->mapWithKeys(fn ($o) => [(int) $o->id => $this->normalize((string) $o->match_target)]);
        $pairs = collect($answer['pairs'] ?? []);

        $isCorrect = $truth->isNotEmpty() && $pairs->count() === $truth->count();
        if ($isCorrect) {
            foreach ($pairs as $pair) {
                $oid = (int) ($pair['option_id'] ?? 0);
                $chosen = $this->normalize((string) ($pair['match_target'] ?? ''));
                if (! $truth->has($oid) || $truth->get($oid) !== $chosen) {
                    $isCorrect = false;
                    break;
                }
            }
        }

        $correctPairs = $options
            ->map(fn ($o) => ['option_id' => (int) $o->id, 'match_target' => (string) $o->match_target])
            ->values();

        return [
            'is_correct' => $isCorrect,
            'correct_answer' => ['pairs' => $correctPairs->all()],
            'explanation' => $question->explanation,
        ];
    }

    /** Normalised/fuzzy comparison against target_text (accent-tolerant). */
    private function gradeText(Question $question, array $answer): array
    {
        $target = (string) ($question->target_text ?? '');
        $given = (string) ($answer['text'] ?? '');
        $isCorrect = $target !== '' && $this->normalize($given) === $this->normalize($target);

        return [
            'is_correct' => $isCorrect,
            'correct_answer' => ['text' => $target],
            'explanation' => $question->explanation,
        ];
    }

    /** @return Collection<int,int> sorted correct option ids */
    private function correctIds(Collection $options): Collection
    {
        return $options->where('is_correct', true)
            ->pluck('id')->map(fn ($i) => (int) $i)->sort()->values();
    }

    /**
     * @param  Collection<int,int>  $correctIds
     * @return array{is_correct:bool, correct_answer:array<string,mixed>, explanation:?string}
     */
    private function optionResult(Question $question, bool $isCorrect, Collection $correctIds): array
    {
        return [
            'is_correct' => $isCorrect,
            'correct_answer' => ['option_ids' => $correctIds->all()],
            'explanation' => $question->explanation,
        ];
    }

    /**
     * Lower-case, trim, and fold diacritics/tone marks so a near-miss on accents
     * isn't punished harshly (Content Model §4.5 — tolerate accents pending a
     * native-speaker tone rubric).
     */
    private function normalize(string $value): string
    {
        $lowered = trim(mb_strtolower($value));
        $folded = Str::ascii($lowered);

        return $folded !== '' ? $folded : $lowered;
    }
}

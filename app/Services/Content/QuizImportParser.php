<?php

namespace App\Services\Content;

use InvalidArgumentException;

/**
 * Maps a spreadsheet grid into the same question shape the quiz builder produces
 * (AuthorQuestionInput), validating each row independently so one bad row is
 * reported — not fatal. No DB writes: the caller reviews the result and saves
 * through the normal component-create path.
 *
 * Columns (header row, any order): type, prompt, options, correct, explanation, points,
 * prompt_audio_asset_id (alias: audio_asset_id).
 *  - `options`  pipe-separated. For match_pairs each is `left=right`; for word_bank in order.
 *  - `correct`  option text or 1-based index (pipe-separated for multi); the answer for type_what_you_hear.
 *  - `prompt_audio_asset_id`  a Media library asset id (mainly for listen_and_respond); validated on save.
 *
 * @phpstan-type Grid array<int, array<int, string>>
 */
class QuizImportParser
{
    private const TYPES = [
        'mcq_single', 'mcq_multi', 'true_false', 'fill_blank', 'listen_and_respond',
        'complete_the_chat', 'word_bank', 'match_pairs', 'type_what_you_hear',
    ];

    /** Option types that allow exactly one correct answer. */
    private const SINGLE_TYPES = [
        'mcq_single', 'true_false', 'fill_blank', 'listen_and_respond', 'complete_the_chat',
    ];

    private const MAX_ROWS = 500;

    /**
     * @param  Grid  $rows
     * @return array{questions: array<int, array<string, mixed>>, errors: array<int, array{row:int, error:string}>}
     */
    public function parse(array $rows): array
    {
        if (count($rows) === 0) {
            return ['questions' => [], 'errors' => [['row' => 0, 'error' => 'The file is empty.']]];
        }

        $header = array_map(fn ($h) => strtolower(trim($h)), $rows[0]);
        $col = fn (string $name) => array_search($name, $header, true);

        if ($col('prompt') === false) {
            return ['questions' => [], 'errors' => [['row' => 1,
                'error' => 'Missing a "prompt" column. Expected header: type, prompt, options, correct, explanation, points.']]];
        }

        $get = fn (array $row, string $name): string => ($i = $col($name)) === false ? '' : trim((string) ($row[$i] ?? ''));

        $questions = [];
        $errors = [];

        foreach (array_slice($rows, 1) as $n => $row) {
            if (count($questions) >= self::MAX_ROWS) {
                $errors[] = ['row' => $n + 2, 'error' => 'Import is capped at '.self::MAX_ROWS.' questions; the rest were skipped.'];
                break;
            }

            $rowNo = $n + 2; // 1-based, accounting for the header row
            $prompt = $get($row, 'prompt');
            if ($prompt === '') {
                $errors[] = ['row' => $rowNo, 'error' => 'Missing prompt.'];

                continue;
            }

            $type = strtolower(str_replace([' ', '-'], '_', $get($row, 'type'))) ?: 'mcq_single';
            if (! in_array($type, self::TYPES, true)) {
                $errors[] = ['row' => $rowNo, 'error' => "Unknown question type '{$type}'."];

                continue;
            }

            try {
                $question = $this->build(
                    $type,
                    $prompt,
                    $get($row, 'options'),
                    $get($row, 'correct'),
                    $get($row, 'explanation') ?: null,
                    max(1, (int) ($get($row, 'points') ?: 1)),
                );

                $audio = $get($row, 'prompt_audio_asset_id') ?: $get($row, 'audio_asset_id');
                if ($audio !== '') {
                    if (! ctype_digit($audio)) {
                        throw new InvalidArgumentException('"prompt_audio_asset_id" must be a Media library asset number.');
                    }
                    $question['prompt_audio_asset_id'] = (int) $audio;
                }

                $questions[] = $question;
            } catch (InvalidArgumentException $e) {
                $errors[] = ['row' => $rowNo, 'error' => $e->getMessage()];
            }
        }

        return ['questions' => $questions, 'errors' => $errors];
    }

    /** @return array<string, mixed> */
    private function build(string $type, string $prompt, string $optionsRaw, string $correctRaw, ?string $explanation, int $points): array
    {
        $question = ['type' => $type, 'prompt' => $prompt, 'points' => $points];
        if ($explanation !== null) {
            $question['explanation'] = $explanation;
        }

        if ($type === 'type_what_you_hear') {
            if ($correctRaw === '') {
                throw new InvalidArgumentException('Type-what-you-hear needs the answer in the "correct" column.');
            }
            $question['target_text'] = $correctRaw;

            return $question;
        }

        $options = array_values(array_filter(array_map('trim', explode('|', $optionsRaw)), fn ($o) => $o !== ''));

        if ($type === 'match_pairs') {
            $question['options'] = $this->matchPairs($options);

            return $question;
        }

        if ($type === 'word_bank') {
            if (count($options) < 2) {
                throw new InvalidArgumentException('Word-bank needs at least two words in "options" (in the correct order).');
            }
            $question['options'] = array_map(fn ($o) => ['label' => $o], $options);

            return $question;
        }

        // Remaining types are option-with-correct.
        if ($type === 'true_false' && count($options) === 0) {
            $options = ['True', 'False'];
        }
        if (count($options) < 2) {
            throw new InvalidArgumentException('This question needs at least two "options" (pipe-separated).');
        }
        if ($correctRaw === '') {
            throw new InvalidArgumentException('Mark the correct answer in the "correct" column (option text or number).');
        }

        $question['options'] = $this->optionsWithCorrect($type, $options, $correctRaw);

        return $question;
    }

    /**
     * @param  array<int, string>  $options
     * @return array<int, array{label:string, match_target:string}>
     */
    private function matchPairs(array $options): array
    {
        $pairs = [];
        foreach ($options as $option) {
            $parts = explode('=', $option, 2);
            if (count($parts) < 2 || trim($parts[0]) === '' || trim($parts[1]) === '') {
                throw new InvalidArgumentException("Match pair '{$option}' must be written as 'left=right'.");
            }
            $pairs[] = ['label' => trim($parts[0]), 'match_target' => trim($parts[1])];
        }
        if (count($pairs) < 2) {
            throw new InvalidArgumentException('Match questions need at least two pairs.');
        }

        return $pairs;
    }

    /**
     * @param  array<int, string>  $options
     * @return array<int, array{label:string, is_correct:bool}>
     */
    private function optionsWithCorrect(string $type, array $options, string $correctRaw): array
    {
        $correctList = array_values(array_filter(array_map('trim', explode('|', $correctRaw)), fn ($c) => $c !== ''));

        $built = [];
        $correctCount = 0;
        foreach ($options as $i => $label) {
            $isCorrect = $this->isCorrect($label, $i + 1, $correctList);
            $correctCount += $isCorrect ? 1 : 0;
            $built[] = ['label' => $label, 'is_correct' => $isCorrect];
        }

        if ($correctCount === 0) {
            throw new InvalidArgumentException('None of the options matched the "correct" value.');
        }
        if (in_array($type, self::SINGLE_TYPES, true) && $correctCount > 1) {
            throw new InvalidArgumentException('This question type allows only one correct answer.');
        }

        return $built;
    }

    /** @param  array<int, string>  $correctList */
    private function isCorrect(string $label, int $index1, array $correctList): bool
    {
        foreach ($correctList as $c) {
            if (is_numeric($c) && (int) $c === $index1) {
                return true;
            }
            if (mb_strtolower($c) === mb_strtolower($label)) {
                return true;
            }
        }

        return false;
    }
}

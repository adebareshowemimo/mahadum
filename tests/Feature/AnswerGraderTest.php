<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Language;
use App\Models\Question;
use App\Services\Learning\AnswerGrader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnswerGraderTest extends TestCase
{
    use RefreshDatabase;

    private function grader(): AnswerGrader
    {
        return app(AnswerGrader::class);
    }

    /**
     * @param  array<int, array{label:string, is_correct?:bool, match_target?:string}>  $options
     */
    private function question(string $type, array $options = [], ?string $targetText = null): Question
    {
        $language = Language::firstOrCreate(['code' => 'ig'], ['name' => 'Igbo', 'script' => 'latin', 'is_active' => true]);
        $course = Course::create(['language_id' => $language->id, 'title' => 'C', 'status' => 'draft']);
        $level = $course->levels()->create(['title' => 'L', 'position' => 1]);
        $lesson = $level->lessons()->create(['title' => 'A', 'position' => 1]);
        $component = $lesson->components()->create(['type' => 'quiz', 'position' => 1]);
        $quiz = $component->quiz()->create(['pass_threshold' => 0.5]);

        $question = $quiz->questions()->create([
            'type' => $type,
            'prompt' => 'P',
            'target_text' => $targetText,
            'position' => 1,
        ]);

        foreach ($options as $i => $opt) {
            $question->options()->create([
                'label' => $opt['label'],
                'is_correct' => $opt['is_correct'] ?? false,
                'match_target' => $opt['match_target'] ?? null,
                'position' => $i + 1,
            ]);
        }

        return $question->fresh(['options']);
    }

    public function test_mcq_single_grades_the_chosen_option(): void
    {
        $q = $this->question('mcq_single', [
            ['label' => 'Right', 'is_correct' => true],
            ['label' => 'Wrong'],
        ]);
        $right = $q->options->firstWhere('is_correct', true);
        $wrong = $q->options->firstWhere('is_correct', false);

        $this->assertTrue($this->grader()->grade($q, ['option_id' => $right->id])['is_correct']);
        $this->assertFalse($this->grader()->grade($q, ['option_id' => $wrong->id])['is_correct']);
    }

    public function test_mcq_multi_requires_the_exact_set(): void
    {
        $q = $this->question('mcq_multi', [
            ['label' => 'A', 'is_correct' => true],
            ['label' => 'B', 'is_correct' => true],
            ['label' => 'C'],
        ]);
        $ids = $q->options->where('is_correct', true)->pluck('id')->all();
        $one = [$q->options->firstWhere('is_correct', true)->id];

        $this->assertTrue($this->grader()->grade($q, ['option_ids' => array_reverse($ids)])['is_correct']);
        $this->assertFalse($this->grader()->grade($q, ['option_ids' => $one])['is_correct']);
        $this->assertFalse($this->grader()->grade($q, ['option_ids' => $q->options->pluck('id')->all()])['is_correct']);
    }

    public function test_word_bank_is_order_sensitive(): void
    {
        $q = $this->question('word_bank', [
            ['label' => 'Ụtụtụ'],
            ['label' => 'ọma'],
        ]);
        $ordered = $q->options->sortBy('position')->pluck('id')->all();

        $this->assertTrue($this->grader()->grade($q, ['option_ids' => $ordered])['is_correct']);
        $this->assertFalse($this->grader()->grade($q, ['option_ids' => array_reverse($ordered)])['is_correct']);
    }

    public function test_match_pairs_checks_each_pairing(): void
    {
        $q = $this->question('match_pairs', [
            ['label' => 'Mama', 'match_target' => 'Mother'],
            ['label' => 'Nna', 'match_target' => 'Father'],
        ]);
        $mama = $q->options->firstWhere('label', 'Mama');
        $nna = $q->options->firstWhere('label', 'Nna');

        $right = $this->grader()->grade($q, ['pairs' => [
            ['option_id' => $mama->id, 'match_target' => 'Mother'],
            ['option_id' => $nna->id, 'match_target' => 'Father'],
        ]]);
        $swapped = $this->grader()->grade($q, ['pairs' => [
            ['option_id' => $mama->id, 'match_target' => 'Father'],
            ['option_id' => $nna->id, 'match_target' => 'Mother'],
        ]]);

        $this->assertTrue($right['is_correct']);
        $this->assertFalse($swapped['is_correct']);
    }

    public function test_type_what_you_hear_tolerates_case_and_accents(): void
    {
        $q = $this->question('type_what_you_hear', targetText: 'Ụtụtụ ọma');

        $this->assertTrue($this->grader()->grade($q, ['text' => '  ututu oma '])['is_correct']);
        $this->assertTrue($this->grader()->grade($q, ['text' => 'Ụtụtụ ọma'])['is_correct']);
        $this->assertFalse($this->grader()->grade($q, ['text' => 'ka chi fo'])['is_correct']);
    }
}

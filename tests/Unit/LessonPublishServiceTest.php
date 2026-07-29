<?php

namespace Tests\Unit;

use App\Models\Question;
use App\Models\QuestionOption;
use App\Services\Content\LessonPublishService;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * The publish check grades correctness differently per question type. These
 * cover the regression where match_pairs/word_bank (which don't use is_correct)
 * were wrongly rejected as "no correct answer set".
 */
class LessonPublishServiceTest extends TestCase
{
    private function hasValidAnswer(Question $question): bool
    {
        $method = new ReflectionMethod(LessonPublishService::class, 'questionHasValidAnswer');

        return $method->invoke(new LessonPublishService, $question);
    }

    /** @param  array<int, array<string, mixed>>  $options */
    private function question(string $type, array $options = [], ?string $targetText = null): Question
    {
        $question = new Question;
        $question->forceFill(['type' => $type, 'target_text' => $targetText]);
        $question->setRelation('options', new Collection(array_map(
            fn (array $attrs) => (new QuestionOption)->forceFill($attrs),
            $options,
        )));

        return $question;
    }

    public function test_match_pairs_is_valid_when_every_pair_has_a_target(): void
    {
        $question = $this->question('match_pairs', [
            ['label' => 'Mama', 'match_target' => 'Mother'],
            ['label' => 'Nna', 'match_target' => 'Father'],
        ]);

        $this->assertTrue($this->hasValidAnswer($question));
    }

    public function test_match_pairs_is_invalid_when_a_target_is_missing(): void
    {
        $question = $this->question('match_pairs', [
            ['label' => 'Mama', 'match_target' => 'Mother'],
            ['label' => 'Nna', 'match_target' => null],
        ]);

        $this->assertFalse($this->hasValidAnswer($question));
    }

    public function test_word_bank_is_valid_from_its_order_without_is_correct(): void
    {
        $question = $this->question('word_bank', [
            ['label' => 'Ututu', 'is_correct' => false],
            ['label' => 'oma', 'is_correct' => false],
        ]);

        $this->assertTrue($this->hasValidAnswer($question));
    }

    public function test_word_bank_needs_at_least_two_words(): void
    {
        $question = $this->question('word_bank', [['label' => 'Ututu', 'is_correct' => false]]);

        $this->assertFalse($this->hasValidAnswer($question));
    }

    public function test_choice_question_still_needs_a_correct_option(): void
    {
        $withCorrect = $this->question('mcq_single', [
            ['label' => 'Abuja', 'is_correct' => true],
            ['label' => 'Lagos', 'is_correct' => false],
        ]);
        $withoutCorrect = $this->question('mcq_single', [
            ['label' => 'Abuja', 'is_correct' => false],
            ['label' => 'Lagos', 'is_correct' => false],
        ]);

        $this->assertTrue($this->hasValidAnswer($withCorrect));
        $this->assertFalse($this->hasValidAnswer($withoutCorrect));
    }

    public function test_type_what_you_hear_is_graded_on_target_text(): void
    {
        $this->assertTrue($this->hasValidAnswer($this->question('type_what_you_hear', [], 'Ututu oma')));
        $this->assertFalse($this->hasValidAnswer($this->question('type_what_you_hear', [], null)));
    }
}

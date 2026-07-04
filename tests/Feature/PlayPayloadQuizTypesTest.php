<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class PlayPayloadQuizTypesTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_play_payload_exposes_match_pool_and_hides_pairings(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $lesson = $this->publishedLesson();

        $quiz = $lesson->components->firstWhere('type', 'quiz')->quiz;
        $question = $quiz->questions()->create(['type' => 'match_pairs', 'prompt' => 'Match', 'position' => 2]);
        $question->options()->create(['label' => 'Mama', 'match_target' => 'Mother', 'position' => 1]);
        $question->options()->create(['label' => 'Nna', 'match_target' => 'Father', 'position' => 2]);

        $res = $this->getJson("/api/v1/lessons/{$lesson->id}/play?learner_id={$learner->id}")->assertOk();

        $questions = collect($res->json('data.components'))->firstWhere('type', 'quiz')['quiz']['questions'];
        $match = collect($questions)->firstWhere('type', 'match_pairs');

        // The right-side pool is present, but the option→target pairing is not leaked.
        $this->assertEqualsCanonicalizing(['Mother', 'Father'], $match['match_pool']);
        foreach ($match['options'] as $option) {
            $this->assertArrayNotHasKey('is_correct', $option);
            $this->assertArrayNotHasKey('match_target', $option);
        }
    }
}

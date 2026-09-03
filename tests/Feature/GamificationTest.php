<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Family;
use App\Models\FamilyHeroAward;
use App\Models\Heart;
use App\Models\LearnerProfile;
use App\Models\XpLedger;
use App\Services\Gamification\LearningLevelService;
use Database\Seeders\BadgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class GamificationTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_completing_a_lesson_bumps_streak_and_awards_badges(): void
    {
        $this->seedRbac();
        $this->seed(BadgeSeeder::class);

        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $lesson = $this->publishedLesson();
        $courseId = Course::first()->id;

        $quizC = $lesson->components->firstWhere('type', 'quiz');
        $videoC = $lesson->components->firstWhere('type', 'video');
        $speakC = $lesson->components->firstWhere('type', 'speaking');
        $question = $quizC->quiz->questions->first();
        $correct = $question->options->firstWhere('is_correct', true);

        $this->postJson('/api/v1/enrollments', ['learner_id' => $learner->id, 'course_id' => $courseId])->assertCreated();
        $this->postJson("/api/v1/components/{$quizC->id}/answer", [
            'learner_id' => $learner->id, 'question_id' => $question->id, 'answer' => ['option_id' => $correct->id],
        ])->assertOk();
        $this->postJson("/api/v1/lessons/{$lesson->id}/progress", [
            'learner_id' => $learner->id, 'component_id' => $videoC->id, 'completed' => true,
        ])->assertOk();
        $this->postJson('/api/v1/speaking-submissions', [
            'learner_id' => $learner->id, 'component_id' => $speakC->id,
        ])->assertCreated();

        $complete = $this->postJson("/api/v1/lessons/{$lesson->id}/complete", ['learner_id' => $learner->id])->assertOk();
        $complete->assertJsonPath('data.streak.count', 1);

        $codes = collect($complete->json('data.badges_unlocked'))->pluck('code');
        $this->assertTrue($codes->contains('first_lesson'));
        $this->assertTrue($codes->contains('sharp_shooter'));

        $this->getJson("/api/v1/learners/{$learner->id}/streak")->assertOk()->assertJsonPath('data.count', 1);
        $this->getJson("/api/v1/learners/{$learner->id}/badges")->assertOk()
            ->assertJsonFragment(['code' => 'first_lesson']);
    }

    public function test_hearts_never_block_and_refill(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);

        $this->getJson("/api/v1/hearts?learner_id={$learner->id}")->assertOk()->assertJsonPath('data.current', 5);
        $this->postJson('/api/v1/hearts/refill', ['learner_id' => $learner->id, 'method' => 'coins'])
            ->assertOk()->assertJsonPath('data.current', 5);
    }

    public function test_zero_hearts_keeps_learning_open_but_pauses_competitive_xp_for_twelve_hours(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $lesson = $this->publishedLesson();
        $quiz = $lesson->components->firstWhere('type', 'quiz');
        $question = $quiz->quiz->questions->first();
        $wrong = $question->options->firstWhere('is_correct', false);
        $correct = $question->options->firstWhere('is_correct', true);

        foreach (range(1, 5) as $attempt) {
            $response = $this->postJson("/api/v1/components/{$quiz->id}/answer", [
                'learner_id' => $learner->id,
                'question_id' => $question->id,
                'answer' => ['option_id' => $wrong->id],
            ])->assertOk();
        }

        $response->assertJsonPath('data.hearts_remaining', 0)
            ->assertJsonPath('data.practice_mode', true);

        $this->postJson("/api/v1/components/{$quiz->id}/answer", [
            'learner_id' => $learner->id,
            'question_id' => $question->id,
            'answer' => ['option_id' => $correct->id],
        ])->assertOk()
            ->assertJsonPath('data.correct', true)
            ->assertJsonPath('data.xp_awarded', 0)
            ->assertJsonPath('data.practice_mode', true);

        $this->assertDatabaseMissing('xp_ledger', ['learner_profile_id' => $learner->id, 'source' => 'quiz']);
        $heart = Heart::where('learner_profile_id', $learner->id)->firstOrFail();
        $this->assertTrue($heart->competitive_paused_until->between(now()->addHours(11), now()->addHours(13)));

        $heart->update(['competitive_paused_until' => now()->subMinute(), 'refills_at' => now()->subMinute()]);
        $this->getJson("/api/v1/hearts?learner_id={$learner->id}")
            ->assertOk()
            ->assertJsonPath('data.current', 5)
            ->assertJsonPath('data.practice_mode', false);

        $this->postJson("/api/v1/components/{$quiz->id}/answer", [
            'learner_id' => $learner->id,
            'question_id' => $question->id,
            'answer' => ['option_id' => $correct->id],
        ])->assertOk()->assertJsonPath('data.xp_awarded', 2);
    }

    public function test_lifetime_xp_uses_the_approved_level_thresholds(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        XpLedger::create(['learner_profile_id' => $learner->id, 'amount' => 1500, 'source' => 'test']);

        $level = app(LearningLevelService::class)->forLearner($learner->fresh());
        $this->assertSame(['number' => 3, 'name' => 'Gold', 'lifetime_xp' => 1500, 'next_level_xp' => 4000], $level);
        $this->assertSame(3, $learner->fresh()->current_level);
    }

    public function test_family_hero_awards_all_daily_xp_ties_and_is_idempotent(): void
    {
        $this->seedRbac();
        $parent = $this->userWithRole('parent');
        $family = Family::create(['owner_user_id' => $parent->id, 'name' => 'Heroes', 'timezone' => 'Africa/Lagos']);
        $one = LearnerProfile::create(['family_id' => $family->id, 'display_name' => 'One', 'current_level' => 0]);
        $two = LearnerProfile::create(['family_id' => $family->id, 'display_name' => 'Two', 'current_level' => 0]);
        $earnedAt = now('Africa/Lagos')->subDay()->setTime(12, 0)->utc();
        XpLedger::create(['learner_profile_id' => $one->id, 'amount' => 40, 'source' => 'lesson', 'created_at' => $earnedAt]);
        XpLedger::create(['learner_profile_id' => $two->id, 'amount' => 40, 'source' => 'quiz', 'created_at' => $earnedAt]);
        $date = now('Africa/Lagos')->subDay()->toDateString();

        $this->artisan("gamification:award-family-heroes --date={$date}")->assertSuccessful();
        $this->artisan("gamification:award-family-heroes --date={$date}")->assertSuccessful();

        $this->assertSame(2, FamilyHeroAward::where('family_id', $family->id)->count());
        $this->assertDatabaseHas('learner_badges', ['learner_profile_id' => $one->id]);
        $this->assertDatabaseHas('learner_badges', ['learner_profile_id' => $two->id]);
    }
}

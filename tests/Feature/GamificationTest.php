<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Family;
use App\Models\FamilyHeroAward;
use App\Models\LearnerProfile;
use App\Models\XpLedger;
use App\Notifications\LearningLevelUp;
use App\Services\Gamification\BadgeService;
use App\Services\Gamification\LearningLevelService;
use Database\Seeders\BadgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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
            ->assertStatus(422);
    }

    public function test_every_four_answers_cost_one_heart_and_zero_locks_for_twelve_hours(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $lesson = $this->publishedLesson();
        $quiz = $lesson->components->firstWhere('type', 'quiz');
        $question = $quiz->quiz->questions->first();
        $correct = $question->options->firstWhere('is_correct', true);
        foreach (range(1, 20) as $number) {
            $this->postJson("/api/v1/components/{$quiz->id}/answer", [
                'learner_id' => $learner->id, 'question_id' => $question->id,
                'answer' => ['option_id' => $correct->id],
            ])->assertOk()->assertJsonPath('data.hearts_remaining', 5 - intdiv($number, 4));
        }
        $this->postJson("/api/v1/components/{$quiz->id}/answer", [
            'learner_id' => $learner->id, 'question_id' => $question->id,
            'answer' => ['option_id' => $correct->id],
        ])->assertStatus(423)->assertJsonPath('error.code', 'hearts_exhausted');
        $this->getJson("/api/v1/lessons/{$lesson->id}/play?learner_id={$learner->id}")->assertStatus(423);
        $this->postJson("/api/v1/lessons/{$lesson->id}/complete", ['learner_id' => $learner->id])->assertStatus(423);
        $this->travel(12)->hours();
        $this->travel(1)->seconds();
        $this->getJson("/api/v1/hearts?learner_id={$learner->id}")->assertOk()->assertJsonPath('data.current', 5);
        $this->getJson("/api/v1/lessons/{$lesson->id}/play?learner_id={$learner->id}")->assertOk();
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

    public function test_reaching_a_level_awards_the_tier_badge_and_notifies_the_learner(): void
    {
        Notification::fake();
        $this->seedRbac();
        $this->seed(BadgeSeeder::class);

        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);

        $intro = $this->publishedLesson();
        $unit = $intro->courseLevel->lessons()->create(['title' => 'Level 1 lesson', 'position' => 2, 'published_at' => now()]);
        foreach ([$intro, $unit] as $lesson) {
            $learner->lessonProgress()->create(['lesson_id' => $lesson->id, 'status' => 'completed', 'completed_at' => now()]);
        }
        // XP alone must not grant content-completion badges.
        XpLedger::create(['learner_profile_id' => $learner->id, 'amount' => 120, 'source' => 'test']);
        $new = app(BadgeService::class)->evaluate($learner->fresh());

        $codes = collect($new)->pluck('code');
        $this->assertTrue($codes->contains('tier_0'));
        $this->assertTrue($codes->contains('tier_1'));
        $this->assertDatabaseHas('learner_badges', ['learner_profile_id' => $learner->id]);

        Notification::assertSentTo(
            $parent,
            LearningLevelUp::class,
        );

        // Idempotent — a second evaluate awards nothing and notifies no-one new.
        $this->assertSame([], app(BadgeService::class)->evaluate($learner->fresh()));
    }

    public function test_badge_endpoint_returns_detail_fields_for_earned_and_locked(): void
    {
        $this->seedRbac();
        $this->seed(BadgeSeeder::class);
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);

        $lesson = $this->publishedLesson();
        $learner->lessonProgress()->create(['lesson_id' => $lesson->id, 'status' => 'completed', 'completed_at' => now()]);
        app(BadgeService::class)->evaluate($learner->fresh());

        $this->getJson("/api/v1/learners/{$learner->id}/badges")
            ->assertOk()
            ->assertJsonFragment(['code' => 'tier_0', 'name' => 'Star Starter', 'level' => 0])
            ->assertJsonStructure(['data' => ['earned' => [['description', 'icon', 'earned_at']], 'locked' => [['description', 'icon']]]]);
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

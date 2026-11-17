<?php

namespace Tests\Feature;

use App\Models\LearnerBadge;
use App\Models\LessonProgress;
use App\Models\Streak;
use Database\Seeders\BadgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class ReconcileLearningProgressTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_dry_run_and_idempotent_repair_of_fully_finished_lesson(): void
    {
        $this->seedRbac();
        $this->seed(BadgeSeeder::class);
        $parent = $this->userWithRole('parent');
        $learner = $this->parentWithChild($parent);
        $lesson = $this->publishedLesson();
        $progress = LessonProgress::create(['learner_profile_id' => $learner->id, 'lesson_id' => $lesson->id,
            'status' => 'in_progress', 'started_at' => now()->subDay()]);
        foreach ($lesson->components as $component) {
            $progress->componentProgress()->create(['lesson_component_id' => $component->id, 'status' => 'complete', 'score' => 1]);
        }

        $this->artisan('learning:reconcile-progress')->expectsOutputToContain('lessons=1, first_steps=0, missing_streaks=0')->assertSuccessful();
        $this->assertSame('in_progress', $progress->fresh()->status);
        $this->artisan('learning:reconcile-progress --apply')->expectsOutputToContain('lessons=1, first_steps=1, missing_streaks=1')->assertSuccessful();
        $this->assertSame('completed', $progress->fresh()->status);
        $this->assertSame(1, LearnerBadge::where('learner_profile_id', $learner->id)->count());
        $this->assertSame(1, Streak::where('learner_profile_id', $learner->id)->count());
        $this->artisan('learning:reconcile-progress --apply')->expectsOutputToContain('lessons=0, first_steps=0, missing_streaks=0')->assertSuccessful();
    }
}

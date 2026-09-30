<?php

namespace Tests\Feature;

use App\Models\CourseLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class LevelFreeAccessTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_managers_can_create_multiple_free_levels_and_access_changes_are_audited(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('content_owner'));
        $lesson = $this->publishedLesson();
        $course = $lesson->courseLevel->course;
        $url = "/api/v1/courses/{$course->id}/levels";
        $this->postJson($url, ['title' => 'Paid'])->assertCreated()->assertJsonPath('data.is_free', false);
        foreach (['Bonus A', 'Bonus B'] as $title) {
            $this->postJson($url, ['title' => $title, 'is_free' => true])->assertCreated()->assertJsonPath('data.is_free', true);
        }
        $level = $lesson->courseLevel;
        $this->patchJson("/api/v1/levels/{$level->id}", ['is_free' => false])->assertOk()->assertJsonPath('data.is_free', false);
        $this->assertDatabaseHas('audit_logs', ['action' => 'level.access.updated', 'subject_id' => $level->id]);
        $this->patchJson("/api/v1/levels/{$level->id}", ['is_free' => 'invalid'])->assertUnprocessable();
    }

    public function test_free_access_survives_reordering_and_paid_levels_cannot_be_opened_via_legacy_flags(): void
    {
        $this->seedRbac();
        $lesson = $this->publishedLesson();
        $level = $lesson->courseLevel;
        $course = $level->course;
        $other = $course->levels()->create(['title' => 'Other', 'position' => 1]);
        $this->actingAsUser($this->userWithRole('content_owner'));
        $this->postJson("/api/v1/courses/{$course->id}/levels/reorder", ['order' => [$other->id, $level->id]])->assertOk();
        $this->assertTrue($level->fresh()->is_free);
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $play = "/api/v1/lessons/{$lesson->id}/play?learner_id={$learner->id}";
        $this->getJson($play)->assertOk();
        $this->patchJson("/api/v1/levels/{$level->id}", ['is_free' => false])->assertForbidden();
        $level->update(['is_free' => false]);
        // Old lesson preview flags and names cannot override a paid level.
        $this->assertTrue($lesson->is_free_preview);
        $this->getJson($play)->assertForbidden();
        $this->actingAsUser($this->userWithRole('content_owner'));
        $this->patchJson("/api/v1/lessons/{$lesson->id}", ['is_free_preview' => true])->assertUnprocessable();
    }

    public function test_migration_preserves_introductory_levels_without_unlocking_ordinary_levels(): void
    {
        $lesson = $this->publishedLesson();
        $course = $lesson->courseLevel->course;
        $named = $course->levels()->create(['title' => 'Level 0: Introduction', 'position' => 5]);
        $paid = $course->levels()->create(['title' => 'Level 10: Advanced', 'position' => 6]);
        Schema::table('course_levels', fn ($table) => $table->dropColumn('is_free'));
        $migration = require database_path('migrations/2026_09_30_000001_add_free_access_to_course_levels.php');
        $migration->up();
        $this->assertTrue(CourseLevel::find($lesson->course_level_id)->is_free);
        $this->assertTrue($named->fresh()->is_free);
        $this->assertFalse($paid->fresh()->is_free);
    }
}

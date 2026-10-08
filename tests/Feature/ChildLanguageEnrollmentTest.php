<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Language;
use App\Models\LearnerProfile;
use App\Services\Learning\PathBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class ChildLanguageEnrollmentTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_add_child_builds_only_published_selected_language_paths_without_unlocking_paid_content(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $this->parentWithChild($parent);
        $lesson = $this->publishedLesson();
        $course = $lesson->courseLevel->course;
        $paidLevel = $course->levels()->create(['title' => 'L1', 'position' => 1, 'is_free' => false]);
        $paidLesson = $paidLevel->lessons()->create(['title' => 'Paid', 'position' => 1, 'published_at' => now()]);
        Course::create(['language_id' => $course->language_id, 'title' => 'Draft', 'is_published' => false]);
        $other = Language::create(['code' => 'yo', 'name' => 'Yoruba', 'is_active' => true]);
        Course::create(['language_id' => $other->id, 'title' => 'Other language', 'is_published' => true]);
        $id = $this->postJson('/api/v1/family/children', ['display_name' => 'Igbo learner', 'target_language_id' => $course->language_id, 'consent' => true])
            ->assertCreated()->json('data.id');
        $this->assertSame($course->language_id, LearnerProfile::findOrFail($id)->target_language_id);
        $enrollment = Enrollment::where('learner_profile_id', $id)->sole();
        $this->assertSame($course->id, $enrollment->course_id);
        app(PathBuilder::class)->build($enrollment);
        $this->assertSame(2, $enrollment->pathNodes()->count());
        $this->getJson("/api/v1/learners/$id/path")->assertOk()->assertJsonPath('data.units.0.nodes.0.lesson_id', $lesson->id)
            ->assertJsonPath('data.units.1.nodes.0.lesson_id', $paidLesson->id)->assertJsonPath('data.units.1.nodes.0.state', 'locked');
    }
}

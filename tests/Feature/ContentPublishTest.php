<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Language;
use App\Models\LearnerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class ContentPublishTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_publish_requires_video_and_quiz(): void
    {
        // v1 rule: a lesson publishes with ≥1 video + ≥1 quiz. Speaking (which
        // needs learner recording + review) is deferred to v2 and not required.
        $this->seedRbac();
        $owner = $this->actingAsUser($this->userWithRole('content_owner'));
        $lang = Language::create(['code' => 'ig', 'name' => 'Igbo', 'script' => 'latin', 'is_active' => true]);

        $course = $this->postJson('/api/v1/courses', ['language_id' => $lang->id, 'title' => 'C'])
            ->assertCreated()->json('data.id');
        $level = $this->postJson("/api/v1/courses/$course/levels", ['title' => 'L1'])->assertCreated()->json('data.id');
        $lesson = $this->postJson("/api/v1/levels/$level/lessons", ['title' => 'Lesson'])->assertCreated()->json('data.id');

        // No components yet → publish fails with itemized reasons.
        $this->postJson("/api/v1/lessons/$lesson/publish")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'publish_checks_failed');

        $this->postJson("/api/v1/lessons/$lesson/components", [
            'type' => 'video', 'video' => ['title' => 'V', 'status' => 'ready'],
        ])->assertCreated();

        // Video alone is not enough — a quiz is still required.
        $this->postJson("/api/v1/lessons/$lesson/publish")->assertStatus(422);

        $this->postJson("/api/v1/lessons/$lesson/components", [
            'type' => 'quiz', 'quiz' => ['questions' => [
                ['type' => 'mcq_single', 'prompt' => 'Q', 'options' => [
                    ['label' => 'A', 'is_correct' => true], ['label' => 'B', 'is_correct' => false],
                ]],
            ]],
        ])->assertCreated();

        // Video + quiz is publishable without a speaking step.
        $this->postJson("/api/v1/lessons/$lesson/publish")->assertOk()->assertJsonPath('data.is_published', true);

        $this->assertDatabaseHas('lessons', ['id' => $lesson]);
        $this->assertNotNull($this->getJson("/api/v1/lessons/$lesson")->json('data.published_at'));
    }

    public function test_play_payload_strips_correct_answers(): void
    {
        $this->seedRbac();
        $owner = $this->actingAsUser($this->userWithRole('content_owner'));

        // Build a lesson with a quiz directly through the trait-free path:
        $lang = Language::create(['code' => 'yo', 'name' => 'Yoruba', 'script' => 'latin', 'is_active' => true]);
        $course = $this->postJson('/api/v1/courses', ['language_id' => $lang->id, 'title' => 'C'])->json('data.id');
        $level = $this->postJson("/api/v1/courses/$course/levels", ['title' => 'L1'])->json('data.id');
        $lesson = $this->postJson("/api/v1/levels/$level/lessons", ['title' => 'L'])->json('data.id');
        $this->postJson("/api/v1/lessons/$lesson/components", [
            'type' => 'quiz', 'quiz' => ['questions' => [
                ['type' => 'mcq_single', 'prompt' => 'Q', 'options' => [['label' => 'A', 'is_correct' => true]]],
            ]],
        ])->assertCreated();

        $play = $this->getJson("/api/v1/lessons/$lesson/play")->assertOk()->json('data');
        $options = $play['components'][0]['quiz']['questions'][0]['options'];

        $this->assertArrayNotHasKey('is_correct', $options[0]);
    }

    public function test_student_catalog_returns_all_published_courses_and_hides_drafts(): void
    {
        $this->seedRbac();
        $language = Language::create(['code' => 'en', 'name' => 'English', 'script' => 'latin', 'is_active' => true]);

        foreach (range(1, 25) as $number) {
            Course::create([
                'language_id' => $language->id,
                'title' => "Published {$number}",
                'status' => 'published',
                'is_published' => true,
            ]);
        }
        Course::create([
            'language_id' => $language->id,
            'title' => 'Draft course',
            'status' => 'draft',
            'is_published' => false,
        ]);

        $this->actingAsUser($this->userWithRole('student'));
        $this->getJson('/api/v1/courses?per_page=100')
            ->assertOk()
            ->assertJsonCount(25, 'data');
    }

    public function test_learner_catalog_marks_real_enrollment_state_and_rejects_another_profile(): void
    {
        $this->seedRbac();
        $language = Language::create(['code' => 'yo', 'name' => 'Yoruba', 'script' => 'latin', 'is_active' => true]);
        $enrolledCourse = Course::create([
            'language_id' => $language->id, 'title' => 'Already started', 'status' => 'published', 'is_published' => true,
        ]);
        $availableCourse = Course::create([
            'language_id' => $language->id, 'title' => 'Available', 'status' => 'published', 'is_published' => true,
        ]);

        $student = $this->actingAsUser($this->userWithRole('student'));
        $learner = LearnerProfile::create(['user_id' => $student->id, 'display_name' => 'Learner']);
        Enrollment::create([
            'learner_profile_id' => $learner->id,
            'course_id' => $enrolledCourse->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/courses?learner_id={$learner->id}&per_page=100")
            ->assertOk();

        $courses = collect($response->json('data'))->keyBy('id');
        $this->assertTrue($courses[$enrolledCourse->id]['is_enrolled']);
        $this->assertFalse($courses[$availableCourse->id]['is_enrolled']);

        $otherLearner = LearnerProfile::create(['display_name' => 'Not mine']);
        $this->getJson("/api/v1/courses?learner_id={$otherLearner->id}")->assertForbidden();
    }

    public function test_publishing_a_course_publishes_every_draft_lesson_in_it(): void
    {
        $this->seedRbac();
        $lesson = $this->publishedLesson();
        $course = $lesson->courseLevel->course;

        // The author has written a full, valid lesson but never pressed publish
        // on it, and the course itself is still a draft.
        $lesson->update(['published_at' => null]);
        $course->update(['is_published' => false, 'status' => 'draft']);

        $this->actingAsUser($this->userWithRole('content_owner'));
        $this->postJson("/api/v1/courses/{$course->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.is_published', true)
            ->assertJsonCount(1, 'meta.lessons_published')
            ->assertJsonCount(0, 'meta.lessons_incomplete');

        $this->assertNotNull($lesson->fresh()->published_at);
        $this->assertFalse((bool) $lesson->fresh()->is_locked_by_default);
    }

    public function test_course_publish_ships_incomplete_lessons_and_names_them(): void
    {
        $this->seedRbac();
        $good = $this->publishedLesson();
        $course = $good->courseLevel->course;
        $good->update(['published_at' => null]);
        $course->update(['is_published' => false, 'status' => 'draft']);

        // An empty lesson — no video, no quiz. Publishing at course level is the
        // author's call, so it ships anyway, but is reported back to them.
        $thin = $good->courseLevel->lessons()->create(['title' => 'Unfinished draft', 'position' => 2]);

        $this->actingAsUser($this->userWithRole('content_owner'));
        $this->postJson("/api/v1/courses/{$course->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.is_published', true)
            ->assertJsonCount(2, 'meta.lessons_published')
            ->assertJsonCount(1, 'meta.lessons_incomplete')
            ->assertJsonPath('meta.lessons_incomplete.0.lesson_id', $thin->id)
            ->assertJsonPath('meta.lessons_incomplete.0.title', 'Unfinished draft');

        $this->assertNotNull($good->fresh()->published_at);
        $this->assertNotNull($thin->fresh()->published_at);
    }

    public function test_lesson_level_publish_still_enforces_the_readiness_checks(): void
    {
        // Course-level publish is unconditional, but the per-lesson button is
        // where an author asks "is this lesson ready?" — that must still answer.
        $this->seedRbac();
        $lesson = $this->publishedLesson();
        $lesson->update(['published_at' => null]);
        $lesson->components()->delete();

        $this->actingAsUser($this->userWithRole('content_owner'));
        $this->postJson("/api/v1/lessons/{$lesson->id}/publish")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'publish_checks_failed');

        $this->assertNull($lesson->fresh()->published_at);
    }
}

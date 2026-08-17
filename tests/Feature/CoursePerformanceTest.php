<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\LessonProgress;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class CoursePerformanceTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_content_owner_sees_engagement_for_their_own_course(): void
    {
        $this->seedRbac();
        $owner = $this->userWithRole('content_owner');
        $lesson = $this->publishedLesson();
        $lesson->courseLevel->course->update(['owner_user_id' => $owner->id]);

        $parent = $this->userWithRole('parent');
        $learner = $this->parentWithChild($parent);
        Enrollment::create(['learner_profile_id' => $learner->id, 'course_id' => $lesson->courseLevel->course_id, 'status' => 'active']);
        LessonProgress::create(['lesson_id' => $lesson->id, 'learner_profile_id' => $learner->id, 'status' => 'completed']);

        $quizC = $lesson->components->firstWhere('type', 'quiz');
        $question = $quizC->quiz->questions->first();
        $correct = $question->options->firstWhere('is_correct', true);
        $this->actingAsUser($parent);
        $this->postJson("/api/v1/components/{$quizC->id}/answer", [
            'learner_id' => $learner->id, 'question_id' => $question->id, 'answer' => ['option_id' => $correct->id],
        ])->assertOk();

        $this->actingAsUser($owner);
        $data = $this->getJson('/api/v1/courses-performance')->assertOk()->json('data');

        $this->assertCount(1, $data);
        $this->assertSame($lesson->courseLevel->course_id, $data[0]['id']);
        $this->assertSame(1, $data[0]['enrollments']);
        $this->assertSame(1, $data[0]['lesson_completions']);
        $this->assertEquals(1, $data[0]['quiz_accuracy']);
    }

    public function test_subscription_revenue_is_split_evenly_across_a_subscribers_courses(): void
    {
        $this->seedRbac();
        $owner = $this->userWithRole('content_owner');

        // Two courses, both owned by this content owner, both enrolled by the
        // same child — so the parent's one subscription must be halved, not
        // counted whole against each course.
        $lessonA = $this->publishedLesson();
        $lessonB = $this->publishedLesson();
        $courseA = $lessonA->courseLevel->course;
        $courseB = $lessonB->courseLevel->course;
        $courseA->update(['owner_user_id' => $owner->id]);
        $courseB->update(['owner_user_id' => $owner->id]);

        $parent = $this->userWithRole('parent');
        $learner = $this->parentWithChild($parent);
        Enrollment::create(['learner_profile_id' => $learner->id, 'course_id' => $courseA->id, 'status' => 'active']);
        Enrollment::create(['learner_profile_id' => $learner->id, 'course_id' => $courseB->id, 'status' => 'active']);

        $plan = Plan::create(['code' => 'fam', 'name' => 'Family', 'price_minor' => 600000, 'currency' => 'NGN', 'interval' => 'month']);
        $subscription = new Subscription(['plan_id' => $plan->id, 'method' => 'card', 'status' => 'active', 'started_at' => now()]);
        $subscription->subscriber()->associate($parent);
        $subscription->save();

        $this->actingAsUser($owner);
        $data = collect($this->getJson('/api/v1/courses-performance')->assertOk()->json('data'))->keyBy('id');

        foreach ([$courseA->id, $courseB->id] as $courseId) {
            $this->assertSame(300000, $data[$courseId]['attributed_revenue_minor']);
            $this->assertSame(1, $data[$courseId]['subscribers']);
            $this->assertSame(1, $data[$courseId]['subscriptions_by_status']['active']);
            $this->assertSame(0, $data[$courseId]['pending_revenue_minor']);
            $this->assertSame(0, $data[$courseId]['referred_subscribers']);
        }
    }

    public function test_pending_subscriptions_are_reported_as_pending_not_earned(): void
    {
        $this->seedRbac();
        $owner = $this->userWithRole('content_owner');
        $lesson = $this->publishedLesson();
        $course = $lesson->courseLevel->course;
        $course->update(['owner_user_id' => $owner->id]);

        $parent = $this->userWithRole('parent');
        $learner = $this->parentWithChild($parent);
        Enrollment::create(['learner_profile_id' => $learner->id, 'course_id' => $course->id, 'status' => 'active']);

        $plan = Plan::create(['code' => 'fam', 'name' => 'Family', 'price_minor' => 500000, 'currency' => 'NGN', 'interval' => 'month']);
        $subscription = new Subscription(['plan_id' => $plan->id, 'method' => 'card', 'status' => 'pending']);
        $subscription->subscriber()->associate($parent);
        $subscription->save();

        $this->actingAsUser($owner);
        $row = $this->getJson('/api/v1/courses-performance')->assertOk()->json('data.0');

        $this->assertSame(0, $row['attributed_revenue_minor']);
        $this->assertSame(500000, $row['pending_revenue_minor']);
        $this->assertSame(1, $row['subscriptions_by_status']['pending']);
    }

    public function test_content_owner_does_not_see_another_owners_course(): void
    {
        $this->seedRbac();
        $this->publishedLesson(); // owned by nobody (owner_user_id null)

        $this->actingAsUser($this->userWithRole('content_owner'));
        $data = $this->getJson('/api/v1/courses-performance')->assertOk()->json('data');

        $this->assertCount(0, $data);
    }

    public function test_super_admin_sees_every_course(): void
    {
        $this->seedRbac();
        $this->publishedLesson();

        $this->actingAsUser($this->userWithRole('super_admin'));
        $data = $this->getJson('/api/v1/courses-performance')->assertOk()->json('data');

        $this->assertCount(1, $data);
    }

    public function test_requires_the_lesson_analytics_permission(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('parent'));

        $this->getJson('/api/v1/courses-performance')->assertStatus(403);
    }
}

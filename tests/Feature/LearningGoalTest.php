<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\LearnerProfile;
use App\Services\Learning\PathBuilder;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class LearningGoalTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_admin_configures_goal_wording_and_disabled_options_disappear(): void
    {
        $this->getJson('/api/v1/config')->assertOk()
            ->assertJsonPath('data.learning_goals.0.key', 'grandparents')
            ->assertJsonPath('data.learning_goals.0.label', 'Grandparents');

        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('super_admin'));
        $this->patchJson('/api/v1/admin/settings', ['values' => [
            'learning_goal.question' => 'What brings you here?',
            'learning_goal.grandparents.label' => 'Speak with elders',
            'learning_goal.grandparents.message' => 'Keep building family conversations.',
            'learning_goal.travel.enabled' => false,
        ]])->assertOk();
        $goals = $this->getJson('/api/v1/config')->assertOk()->json('data.learning_goals');
        $this->getJson('/api/v1/config')->assertJsonPath('data.learning_goal_question', 'What brings you here?');
        $this->assertSame('Speak with elders', $goals[0]['label']);
        $this->assertNotContains('travel', array_column($goals, 'key'));
        $this->patchJson('/api/v1/admin/settings', ['values' => ['learning_goal.grandparents.label' => '  ']])->assertUnprocessable();
        $this->patchJson('/api/v1/admin/settings', ['values' => ['learning_goal.grandparents.keywords' => ', ,']])->assertUnprocessable();
        $this->patchJson('/api/v1/admin/settings', ['values' => [
            'learning_goal.grandparents.enabled' => false,
            'learning_goal.school.enabled' => false,
            'learning_goal.business.enabled' => false,
        ]])->assertUnprocessable();
    }

    public function test_parent_sets_child_goal_and_path_uses_it_for_progress_and_relevant_lessons(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $child = $this->parentWithChild($parent);
        $lesson = $this->publishedLesson();
        $lesson->update(['title' => 'Travel to the market']);
        $course = $lesson->courseLevel->course;
        $enrollment = Enrollment::create(['learner_profile_id' => $child->id, 'course_id' => $course->id, 'status' => 'active', 'started_at' => now()]);
        app(PathBuilder::class)->build($enrollment);

        $this->putJson("/api/v1/learners/{$child->id}/learning-goal", ['learning_goal' => 'travel'])->assertOk();
        $this->assertSame('travel', LearnerProfile::findOrFail($child->id)->learning_goal);
        $this->getJson('/api/v1/me')->assertOk();
        $this->getJson("/api/v1/learners/{$child->id}/path")->assertOk()
            ->assertJsonPath('data.learning_goal.label', 'Travel')
            ->assertJsonPath('data.completed_lessons', 0)
            ->assertJsonPath('data.suggested_lessons.0.lesson_id', $lesson->id);
        app(Settings::class)->set(['learning_goal.travel.keywords' => 'airport']);
        $this->getJson("/api/v1/learners/{$child->id}/path")->assertOk()->assertJsonCount(0, 'data.suggested_lessons');
        app(Settings::class)->set(['learning_goal.travel.keywords' => 'market']);
        $this->getJson("/api/v1/learners/{$child->id}/path")->assertOk()->assertJsonPath('data.suggested_lessons.0.lesson_id', $lesson->id);
        $this->putJson("/api/v1/learners/{$child->id}/learning-goal", ['learning_goal' => 'unknown'])->assertUnprocessable();

        $this->actingAsUser($this->userWithRole('parent'));
        $this->putJson("/api/v1/learners/{$child->id}/learning-goal", ['learning_goal' => 'school'])->assertForbidden();
    }

    public function test_new_child_can_start_with_a_learning_goal(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $this->parentWithChild($parent);
        $id = $this->postJson('/api/v1/family/children', [
            'display_name' => 'Goal learner', 'learning_goal' => 'school', 'consent' => true,
        ])->assertCreated()->json('data.id');
        $this->assertSame('school', LearnerProfile::findOrFail($id)->learning_goal);
    }

    public function test_disabled_goal_cannot_be_chosen_and_child_session_cannot_edit_it(): void
    {
        $this->seedRbac();
        $parent = $this->userWithRole('parent');
        $child = $this->parentWithChild($parent);
        app(Settings::class)->set(['learning_goal.business.enabled' => false]);
        $this->withToken($parent->createToken('goal-child-mode')->plainTextToken);
        $this->putJson("/api/v1/learners/{$child->id}/learning-goal", ['learning_goal' => 'business'])->assertUnprocessable();
        $this->putJson("/api/v1/family/children/{$child->id}/pin", ['pin' => '1234'])->assertOk();
        $this->postJson("/api/v1/profiles/{$child->id}/switch", ['pin' => '1234'])->assertOk();
        $this->putJson("/api/v1/learners/{$child->id}/learning-goal", ['learning_goal' => 'travel'])->assertForbidden();
    }
}

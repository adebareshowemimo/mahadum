<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Language;
use App\Models\LessonComponent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_xp_settings_have_product_defaults_and_are_visible_to_admin(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('super_admin'));

        $response = $this->getJson('/api/v1/admin/settings')->assertOk();
        $settings = collect($response->json('data.groups'))->pluck('settings')->flatten(1)->keyBy('key');

        $this->assertSame(1, $settings['learning.quiz_completion_xp']['value']);
        $this->assertSame(5, $settings['learning.video_completion_xp']['value']);
    }

    public function test_admin_xp_changes_update_existing_components_and_new_component_defaults(): void
    {
        $this->seedRbac();
        $language = Language::create(['code' => 'yo', 'name' => 'Yoruba', 'script' => 'latin', 'is_active' => true]);
        $course = Course::create(['language_id' => $language->id, 'title' => 'Course']);
        $level = $course->levels()->create(['title' => 'Level', 'position' => 1]);
        $lesson = $level->lessons()->create(['title' => 'Lesson', 'position' => 1]);
        $lesson->components()->create(['type' => 'quiz', 'position' => 1, 'xp_value' => 10]);
        $lesson->components()->create(['type' => 'video', 'position' => 2, 'xp_value' => 20]);

        $this->actingAsUser($this->userWithRole('super_admin'));
        $this->patchJson('/api/v1/admin/settings', ['values' => [
            'learning.quiz_completion_xp' => 3,
            'learning.video_completion_xp' => 7,
        ]])->assertOk();

        $this->assertDatabaseHas('lesson_components', ['type' => 'quiz', 'xp_value' => 3]);
        $this->assertDatabaseHas('lesson_components', ['type' => 'video', 'xp_value' => 7]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'system.settings_updated']);

        $this->actingAsUser($this->userWithRole('content_owner'));
        $quiz = $this->postJson("/api/v1/lessons/{$lesson->id}/components", [
            'type' => 'quiz',
            'quiz' => ['questions' => []],
        ])->assertCreated()->json('data');

        $this->assertSame(3, $quiz['xp_value']);
        $this->assertSame(2, LessonComponent::where('type', 'quiz')->count());
    }

    public function test_non_admin_cannot_change_xp_settings(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('content_owner'));

        $this->patchJson('/api/v1/admin/settings', ['values' => [
            'learning.quiz_completion_xp' => 9,
        ]])->assertForbidden();
    }
}

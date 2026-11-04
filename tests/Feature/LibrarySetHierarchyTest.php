<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class LibrarySetHierarchyTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_author_builds_set_levels_and_lessons_while_learner_progress_stays_outside_courses(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('content_owner'));
        $language = Language::create(['code' => 'ig', 'name' => 'Igbo', 'script' => 'latin', 'is_active' => true]);
        $setId = $this->postJson('/api/v1/library-sets', [
            'kind' => 'proverb', 'language_id' => $language->id, 'title' => 'Igbo wisdom',
        ])->assertCreated()->json('data.id');
        $levelId = $this->postJson("/api/v1/library-sets/{$setId}/levels", [
            'title' => 'Everyday sayings', 'is_free' => true,
        ])->assertCreated()->json('data.id');
        $first = $this->postJson("/api/v1/library-set-levels/{$levelId}/lessons", [
            'title' => 'A first saying', 'summary' => 'A short saying and meaning.',
        ])->assertCreated()->json('data.id');
        $second = $this->postJson("/api/v1/library-set-levels/{$levelId}/lessons", [
            'title' => 'Another saying', 'summary' => 'A second saying and meaning.',
        ])->assertCreated()->json('data.id');
        $this->assertNull(Lesson::findOrFail($first)->course_level_id);
        $this->assertSame($levelId, Lesson::findOrFail($first)->library_set_level_id);
        $this->postJson("/api/v1/library-sets/{$setId}/publish")->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonCount(2, 'data.levels.0.lessons');

        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $child = $this->parentWithChild($parent);
        $this->getJson("/api/v1/library-sets?kind=proverb&learner_id={$child->id}")
            ->assertOk()->assertJsonPath('data.0.title', 'Igbo wisdom');
        $this->getJson("/api/v1/library-sets/{$setId}?learner_id={$child->id}")
            ->assertOk()->assertJsonPath('data.levels.0.lessons.0.id', $first);
        $this->getJson("/api/v1/lessons/{$first}/play?learner_id={$child->id}")->assertOk();
        $this->postJson("/api/v1/lessons/{$first}/complete", ['learner_id' => $child->id])->assertOk();
        $this->getJson("/api/v1/library-sets/{$setId}?learner_id={$child->id}")
            ->assertOk()->assertJsonPath('data.completed_count', 1)
            ->assertJsonPath('data.levels.0.lessons.0.completed', true)
            ->assertJsonPath('data.levels.0.lessons.1.id', $second);
    }

    public function test_draft_or_paid_set_lessons_are_not_publicly_open(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('content_owner'));
        $language = Language::create(['code' => 'yo', 'name' => 'Yoruba', 'script' => 'latin', 'is_active' => true]);
        $setId = $this->postJson('/api/v1/library-sets', [
            'kind' => 'trivia', 'language_id' => $language->id, 'title' => 'Yoruba trivia',
        ])->assertCreated()->json('data.id');
        $levelId = $this->postJson("/api/v1/library-sets/{$setId}/levels", [
            'title' => 'Advanced', 'is_free' => false,
        ])->assertCreated()->json('data.id');
        $lessonId = $this->postJson("/api/v1/library-set-levels/{$levelId}/lessons", [
            'title' => 'A quiz', 'summary' => 'Read and answer.',
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/library-sets/{$setId}/publish")->assertOk();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $child = $this->parentWithChild($parent);
        $this->getJson("/api/v1/library-sets/{$setId}?learner_id={$child->id}")
            ->assertOk()->assertJsonPath('data.levels.0.lessons.0.access_reason', 'This level requires a paid plan. Upgrade to unlock its lessons.')
            ->assertJsonPath('data.levels.0.lessons.0.summary', null);
        $this->getJson("/api/v1/lessons/{$lessonId}/play?learner_id={$child->id}")->assertForbidden();
        $this->actingAsUser($this->userWithRole('content_owner'));
        $this->postJson("/api/v1/library-sets/{$setId}/unpublish")->assertOk();
        $this->actingAsUser($parent);
        $this->getJson("/api/v1/library-sets/{$setId}?learner_id={$child->id}")->assertNotFound();
        $this->getJson("/api/v1/lessons/{$lessonId}/play?learner_id={$child->id}")->assertNotFound();
    }

    public function test_author_can_reorder_set_levels_and_lessons_without_linking_a_course(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('content_owner'));
        $language = Language::create(['code' => 'ha', 'name' => 'Hausa', 'script' => 'latin', 'is_active' => true]);
        $setId = $this->postJson('/api/v1/library-sets', [
            'kind' => 'trivia', 'language_id' => $language->id, 'title' => 'Hausa knowledge',
        ])->assertCreated()->json('data.id');
        $firstLevel = $this->postJson("/api/v1/library-sets/{$setId}/levels", ['title' => 'First'])->assertCreated()->json('data.id');
        $secondLevel = $this->postJson("/api/v1/library-sets/{$setId}/levels", ['title' => 'Second'])->assertCreated()->json('data.id');
        $firstLesson = $this->postJson("/api/v1/library-set-levels/{$firstLevel}/lessons", ['title' => 'A'])->assertCreated()->json('data.id');
        $secondLesson = $this->postJson("/api/v1/library-set-levels/{$firstLevel}/lessons", ['title' => 'B'])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/library-set-levels/{$firstLevel}/lessons/reorder", ['order' => [$secondLesson, $firstLesson]])
            ->assertOk()->assertJsonPath('data.lessons.0.id', $secondLesson);
        $this->postJson("/api/v1/library-sets/{$setId}/levels/reorder", ['order' => [$secondLevel, $firstLevel]])
            ->assertOk()->assertJsonPath('data.levels.0.id', $secondLevel);
        $this->assertSame(2, Lesson::findOrFail($firstLesson)->standalone_level);
        $this->assertNull(Lesson::findOrFail($firstLesson)->course_level_id);
    }
}

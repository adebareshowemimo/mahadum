<?php

namespace Tests\Feature;

use App\Models\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityComponentsTest extends TestCase
{
    use RefreshDatabase;

    /** Author an exercise deck + a memory game, then read them back from the play payload. */
    public function test_exercise_and_game_components_round_trip_through_play(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('content_owner'));

        $lang = Language::create(['code' => 'ig', 'name' => 'Igbo', 'script' => 'latin', 'is_active' => true]);
        $course = $this->postJson('/api/v1/courses', ['language_id' => $lang->id, 'title' => 'C'])->json('data.id');
        $level = $this->postJson("/api/v1/courses/$course/levels", ['title' => 'L1'])->json('data.id');
        $lesson = $this->postJson("/api/v1/levels/$level/lessons", ['title' => 'L'])->json('data.id');

        $this->postJson("/api/v1/lessons/$lesson/components", [
            'type' => 'exercise',
            'exercise' => ['mode' => 'flashcards', 'cards' => [
                ['front_text' => 'Nna', 'back_text' => 'Father', 'mnemonic' => 'papa'],
                ['front_text' => 'Nne', 'back_text' => 'Mother'],
            ]],
        ])->assertCreated();

        $this->postJson("/api/v1/lessons/$lesson/components", [
            'type' => 'game',
            'game' => ['game_type' => 'memory', 'config' => ['pairs' => [
                ['a' => 'Nna', 'b' => 'Father'],
                ['a' => 'Nne', 'b' => 'Mother'],
            ]]],
        ])->assertCreated();

        $this->assertDatabaseHas('flashcards', ['front_text' => 'Nna', 'back_text' => 'Father']);
        $this->assertDatabaseHas('games', ['game_type' => 'memory']);

        $components = collect($this->getJson("/api/v1/lessons/$lesson/play")->assertOk()->json('data.components'));

        $exercise = $components->firstWhere('type', 'exercise')['exercise'];
        $this->assertCount(2, $exercise['cards']);
        $this->assertSame('Nna', $exercise['cards'][0]['front']);
        $this->assertSame('Father', $exercise['cards'][0]['back']);

        $game = $components->firstWhere('type', 'game')['game'];
        $this->assertSame('memory', $game['game_type']);
        $this->assertCount(2, $game['pairs']);
    }

    public function test_editing_an_exercise_replaces_its_cards(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('content_owner'));

        $lang = Language::create(['code' => 'yo', 'name' => 'Yoruba', 'script' => 'latin', 'is_active' => true]);
        $course = $this->postJson('/api/v1/courses', ['language_id' => $lang->id, 'title' => 'C'])->json('data.id');
        $level = $this->postJson("/api/v1/courses/$course/levels", ['title' => 'L1'])->json('data.id');
        $lesson = $this->postJson("/api/v1/levels/$level/lessons", ['title' => 'L'])->json('data.id');

        $componentId = $this->postJson("/api/v1/lessons/$lesson/components", [
            'type' => 'exercise',
            'exercise' => ['cards' => [['front_text' => 'Old', 'back_text' => 'Card']]],
        ])->json('data.id');

        $this->putJson("/api/v1/components/$componentId", [
            'type' => 'exercise',
            'exercise' => ['cards' => [['front_text' => 'New', 'back_text' => 'Card']]],
        ])->assertOk();

        $this->assertDatabaseMissing('flashcards', ['front_text' => 'Old']);
        $this->assertDatabaseHas('flashcards', ['front_text' => 'New']);
    }
}

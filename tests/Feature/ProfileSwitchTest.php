<?php

namespace Tests\Feature;

use App\Models\LearnerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class ProfileSwitchTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_parent_can_switch_into_an_unprotected_child_with_no_pin(): void
    {
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $child = $this->parentWithChild($parent);

        $this->postJson("/api/v1/profiles/{$child->id}/switch")
            ->assertOk()
            ->assertJsonPath('data.active_learner_id', $child->id);
    }

    public function test_entering_a_pin_protected_child_requires_its_own_pin(): void
    {
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $child = $this->parentWithChild($parent);
        $this->putJson("/api/v1/family/children/{$child->id}/pin", ['pin' => '1234'])->assertOk();

        $this->postJson("/api/v1/profiles/{$child->id}/switch")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'invalid_pin');

        $this->postJson("/api/v1/profiles/{$child->id}/switch", ['pin' => '1234'])
            ->assertOk()
            ->assertJsonPath('data.active_learner_id', $child->id);
    }

    public function test_switching_between_siblings_requires_the_targets_own_pin(): void
    {
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $childA = $this->parentWithChild($parent);
        $childB = LearnerProfile::create(['family_id' => $childA->family_id, 'display_name' => 'Sibling', 'current_level' => 1]);
        $this->putJson("/api/v1/family/children/{$childB->id}/pin", ['pin' => '4321'])->assertOk();

        $this->postJson("/api/v1/profiles/{$childB->id}/switch", ['from_learner_id' => $childA->id])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'invalid_pin');

        $this->postJson("/api/v1/profiles/{$childB->id}/switch", ['from_learner_id' => $childA->id, 'pin' => '4321'])
            ->assertOk()
            ->assertJsonPath('data.active_learner_id', $childB->id);
    }

    public function test_switching_between_siblings_blocks_a_sibling_with_no_pin_of_their_own(): void
    {
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $childA = $this->parentWithChild($parent);
        $childB = LearnerProfile::create(['family_id' => $childA->family_id, 'display_name' => 'Sibling', 'current_level' => 1]);

        $this->postJson("/api/v1/profiles/{$childB->id}/switch", ['from_learner_id' => $childA->id])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'pin_not_set');
    }
}

<?php

namespace Tests\Feature;

use App\Models\LearnerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class FamilyPinProtectionTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_family_overview_lists_the_parent_and_pin_protection_per_child(): void
    {
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $child = $this->parentWithChild($parent);

        $this->getJson('/api/v1/family')
            ->assertOk()
            ->assertJsonPath('data.parent.id', $parent->id)
            ->assertJsonPath('data.learners.0.id', $child->id)
            ->assertJsonPath('data.learners.0.pin_protected', false);
    }

    public function test_parent_can_set_a_unique_pin_for_one_child_without_affecting_others(): void
    {
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $childA = $this->parentWithChild($parent);
        $childB = LearnerProfile::create(['family_id' => $childA->family_id, 'display_name' => 'Sibling', 'current_level' => 1]);

        $this->putJson("/api/v1/family/children/{$childA->id}/pin", ['pin' => '1234'])
            ->assertOk()
            ->assertJsonPath('data.pin_protected', true);

        $this->assertNotNull($childA->fresh()->parental_pin);
        $this->assertNull($childB->fresh()->parental_pin);

        // Give the sibling a different PIN — they don't have to match.
        $this->putJson("/api/v1/family/children/{$childB->id}/pin", ['pin' => '9999'])
            ->assertOk()
            ->assertJsonPath('data.pin_protected', true);

        $this->assertNotEquals($childA->fresh()->parental_pin, $childB->fresh()->parental_pin);
    }

    public function test_parent_can_remove_a_childs_pin(): void
    {
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $child = $this->parentWithChild($parent);

        $this->putJson("/api/v1/family/children/{$child->id}/pin", ['pin' => '1234'])->assertOk();
        $this->assertNotNull($child->fresh()->parental_pin);

        $this->putJson("/api/v1/family/children/{$child->id}/pin", ['pin' => null])
            ->assertOk()
            ->assertJsonPath('data.pin_protected', false);

        $this->assertNull($child->fresh()->parental_pin);
    }

    public function test_cannot_set_a_pin_for_another_familys_child(): void
    {
        $ownerA = $this->userWithRole('parent');
        $child = $this->parentWithChild($ownerA);

        $ownerB = $this->userWithRole('parent');
        $this->parentWithChild($ownerB);
        $this->actingAsUser($ownerB);

        $this->putJson("/api/v1/family/children/{$child->id}/pin", ['pin' => '1234'])
            ->assertForbidden();
    }
}

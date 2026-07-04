<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParentalConsentTest extends TestCase
{
    use RefreshDatabase;

    private function parentWithFamily(): User
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        Family::create(['owner_user_id' => $parent->id, 'name' => 'Fam']);

        return $parent;
    }

    public function test_adding_a_young_child_records_coppa_consent(): void
    {
        $parent = $this->parentWithFamily();

        $learnerId = $this->postJson('/api/v1/family/children', [
            'display_name' => 'Ada', 'date_of_birth' => now()->subYears(8)->toDateString(), 'consent' => true,
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('parental_consents', [
            'guardian_user_id' => $parent->id,
            'learner_profile_id' => $learnerId,
            'type' => 'coppa_parental',
            'policy_version' => config('compliance.policy_version'),
        ]);
    }

    public function test_adding_a_child_without_consent_is_rejected(): void
    {
        $this->parentWithFamily();

        $this->postJson('/api/v1/family/children', [
            'display_name' => 'Ada', 'date_of_birth' => now()->subYears(8)->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('consent');

        $this->assertDatabaseCount('parental_consents', 0);
        $this->assertDatabaseCount('learner_profiles', 0);
    }

    public function test_teenager_gets_general_data_processing_consent(): void
    {
        $this->parentWithFamily();

        $this->postJson('/api/v1/family/children', [
            'display_name' => 'Teen', 'date_of_birth' => now()->subYears(15)->toDateString(), 'consent' => '1',
        ])->assertCreated();

        $this->assertDatabaseHas('parental_consents', ['type' => 'data_processing']);
    }
}

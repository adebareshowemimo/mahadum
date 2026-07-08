<?php

namespace Tests\Feature;

use App\Models\AdImpression;
use App\Models\Family;
use App\Models\LearnerProfile;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdNetworkTest extends TestCase
{
    use RefreshDatabase;

    private function child(User $parent, ?string $dob = null): LearnerProfile
    {
        $family = Family::create(['owner_user_id' => $parent->id, 'name' => 'Fam']);

        return LearnerProfile::create([
            'family_id' => $family->id, 'display_name' => 'Kid', 'current_level' => 1, 'date_of_birth' => $dob,
        ]);
    }

    public function test_unknown_date_of_birth_is_blocked_by_the_coppa_filter(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->child($parent); // no DOB

        $this->postJson('/api/v1/ads/request', ['learner_id' => $learner->id, 'placement' => 'rewarded_heart'])
            ->assertOk()
            ->assertJsonPath('data.eligible', false)
            ->assertJsonPath('data.reason', 'coppa');

        $this->assertDatabaseHas('ad_impressions', [
            'learner_profile_id' => $learner->id, 'coppa_passed' => false, 'ad_ref' => null,
        ]);
    }

    public function test_adult_learner_gets_an_ad_and_can_redeem_a_hearts_refill(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->child($parent, now()->subYears(20)->toDateString());

        $requested = $this->postJson('/api/v1/ads/request', ['learner_id' => $learner->id, 'placement' => 'rewarded_heart'])
            ->assertOk()
            ->assertJsonPath('data.eligible', true);
        $impressionId = $requested->json('data.impression_id');

        $this->postJson("/api/v1/ads/{$impressionId}/complete")
            ->assertOk()
            ->assertJsonPath('data.shown', true);

        $this->postJson('/api/v1/hearts/refill', [
            'learner_id' => $learner->id, 'method' => 'ad', 'ad_impression_id' => $impressionId,
        ])->assertOk()->assertJsonPath('data.current', 5);

        $this->assertNotNull(AdImpression::find($impressionId)->consumed_at);
    }

    public function test_refill_without_a_shown_ad_impression_is_rejected(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->child($parent, now()->subYears(20)->toDateString());

        $this->postJson('/api/v1/hearts/refill', ['learner_id' => $learner->id, 'method' => 'ad'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ad_impression_id');
    }

    public function test_a_consumed_ad_impression_cannot_be_redeemed_twice(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->child($parent, now()->subYears(20)->toDateString());

        $impressionId = $this->postJson('/api/v1/ads/request', ['learner_id' => $learner->id, 'placement' => 'rewarded_heart'])
            ->json('data.impression_id');
        $this->postJson("/api/v1/ads/{$impressionId}/complete")->assertOk();

        $this->postJson('/api/v1/hearts/refill', [
            'learner_id' => $learner->id, 'method' => 'ad', 'ad_impression_id' => $impressionId,
        ])->assertOk();

        $this->postJson('/api/v1/hearts/refill', [
            'learner_id' => $learner->id, 'method' => 'ad', 'ad_impression_id' => $impressionId,
        ])->assertStatus(422);
    }

    public function test_a_post_lesson_impression_cannot_be_redeemed_for_a_hearts_refill(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->child($parent, now()->subYears(20)->toDateString());

        $impressionId = $this->postJson('/api/v1/ads/request', ['learner_id' => $learner->id, 'placement' => 'post_lesson'])
            ->json('data.impression_id');
        $this->postJson("/api/v1/ads/{$impressionId}/complete")->assertOk();

        $this->postJson('/api/v1/hearts/refill', [
            'learner_id' => $learner->id, 'method' => 'ad', 'ad_impression_id' => $impressionId,
        ])->assertStatus(422);
    }

    /**
     * Same-tenant staff (teacher/supervisor/school_admin) can view a learner's
     * progress via `learning.progress.view`, but must never be able to trigger
     * or redeem an ad reward for a learner they aren't the parent of — that
     * would let staff fabricate "watched ad" compliance records and grant
     * hearts refills without the parent/learner's involvement.
     */
    public function test_same_tenant_staff_cannot_request_or_redeem_ads_for_a_learner_they_do_not_own(): void
    {
        $this->seedRbac();
        $org = Organization::create(['name' => 'Greenfield', 'type' => 'school', 'slug' => 'greenfield', 'status' => 'active']);
        $teacher = $this->userWithRole('teacher');
        $org->members()->attach($teacher->id, ['role' => 'teacher', 'status' => 'active']);

        $learner = LearnerProfile::create([
            'organization_id' => $org->id, 'display_name' => 'Student', 'date_of_birth' => now()->subYears(20)->toDateString(),
        ]);

        $this->actingAsUser($teacher);
        $this->postJson('/api/v1/ads/request', ['learner_id' => $learner->id, 'placement' => 'rewarded_heart'])
            ->assertStatus(403);

        // Even a pre-existing, already-verified impression can't be redeemed by staff.
        $impression = AdImpression::create([
            'learner_profile_id' => $learner->id, 'placement' => 'rewarded_heart',
            'coppa_passed' => true, 'ad_ref' => 'x', 'shown_at' => now(),
        ]);
        $this->postJson("/api/v1/ads/{$impression->id}/complete")->assertStatus(403);
        $this->postJson('/api/v1/hearts/refill', [
            'learner_id' => $learner->id, 'method' => 'ad', 'ad_impression_id' => $impression->id,
        ])->assertStatus(403);
    }
}

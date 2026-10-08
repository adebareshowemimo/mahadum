<?php

namespace Tests\Feature;

use App\Models\LearnerProfile;
use App\Models\Organization;
use App\Services\ConsumerAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndividualConsumerAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_adult_individual_can_manage_own_consumer_pages_without_family_or_admin_access(): void
    {
        $this->seedRbac();
        $user = $this->userWithRole('student', ['date_of_birth' => now()->subYears(25)])->fresh();
        LearnerProfile::create(['user_id' => $user->id, 'display_name' => 'Adult learner']);
        $this->actingAsUser($user);
        foreach (['/subscriptions', '/referral-code', '/referrals/summary', '/payouts'] as $path) {
            $this->getJson('/api/v1'.$path)->assertOk();
        }
        foreach (ConsumerAccess::ABILITIES as $ability) {
            $this->assertTrue($user->can($ability), $ability);
        }
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.user.account_type', 'single')
            ->assertJsonCount(5, 'data.user.capabilities');
        foreach (['/family', '/wallet', '/reviews/pending', '/admin/users'] as $path) {
            $this->getJson('/api/v1'.$path)->assertForbidden();
        }
        $this->assertFalse($user->can('payouts.approve'));
        $this->assertFalse($user->can('family.wallet.fund'));
    }

    public function test_minors_unknown_age_and_school_learners_do_not_gain_adult_capabilities(): void
    {
        $this->seedRbac();
        $school = Organization::create(['name' => 'School', 'slug' => 'consumer-school', 'type' => 'school', 'status' => 'active']);
        foreach ([now()->subYears(12), null, now()->subYears(25)] as $index => $birth) {
            $user = $this->userWithRole('student', ['date_of_birth' => $birth])->fresh();
            LearnerProfile::create(['user_id' => $user->id, 'display_name' => 'Learner', 'age_band' => 'adult', 'organization_id' => $index === 2 ? $school->id : null]);
            $this->actingAsUser($user);
            foreach (ConsumerAccess::ABILITIES as $ability) {
                $this->assertFalse($user->can($ability), $ability);
            }
            $this->getJson('/api/v1/subscriptions')->assertForbidden();
            $this->getJson('/api/v1/referrals/summary')->assertForbidden();
        }
    }
}

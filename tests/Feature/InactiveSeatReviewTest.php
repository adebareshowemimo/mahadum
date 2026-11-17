<?php

namespace Tests\Feature;

use App\Models\ClassEnrollment;
use App\Models\LearnerProfile;
use App\Models\Organization;
use App\Models\SchoolClass;
use App\Services\Learning\XapiRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InactiveSeatReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_can_review_and_release_only_inactive_learner_seats(): void
    {
        $this->seedRbac();
        $school = Organization::create(['name' => 'School', 'slug' => 'seat-review', 'type' => 'school', 'status' => 'active']);
        $admin = $this->userWithRole('school_admin');
        $school->members()->attach($admin->id, ['role' => 'school_admin', 'status' => 'active']);
        $allocation = $school->seatAllocations()->create(['total_purchased' => 2, 'active_filled' => 2]);
        $inactive = LearnerProfile::create(['organization_id' => $school->id, 'display_name' => 'Inactive']);
        $class = SchoolClass::create(['organization_id' => $school->id, 'name' => 'Class']);
        ClassEnrollment::create(['school_class_id' => $class->id, 'learner_profile_id' => $inactive->id]);
        $active = LearnerProfile::create(['organization_id' => $school->id, 'display_name' => 'Active']);
        $inactive->update(['created_at' => now()->subDays(40)]);
        DB::table('xapi_statements')->insert(['learner_profile_id' => $inactive->id, 'verb' => XapiRecorder::VERB_REGISTERED,
            'created_at' => now(), 'updated_at' => now()]);
        DB::table('xapi_statements')->insert(['learner_profile_id' => $active->id, 'verb' => 'completed',
            'created_at' => now(), 'updated_at' => now()]);
        $this->actingAsUser($admin);

        $this->getJson("/api/v1/schools/{$school->id}/seats/inactive")
            ->assertOk()->assertJsonPath('data.inactive_days', 30)
            ->assertJsonCount(1, 'data.learners')->assertJsonPath('data.learners.0.learner_id', $inactive->id)
            ->assertJsonPath('data.learners.0.last_activity_at', null);
        $this->postJson("/api/v1/schools/{$school->id}/seats/inactive/{$active->id}/release")
            ->assertStatus(422);
        $this->postJson("/api/v1/schools/{$school->id}/seats/inactive/{$inactive->id}/release")
            ->assertOk()->assertJsonPath('data.status', 'released');
        $this->assertNull($inactive->fresh()->organization_id);
        $this->assertDatabaseMissing('class_enrollments', ['school_class_id' => $class->id, 'learner_profile_id' => $inactive->id]);
        $this->assertSame(1, $allocation->fresh()->active_filled);
        $this->postJson("/api/v1/schools/{$school->id}/seats/inactive/{$inactive->id}/release")
            ->assertNotFound();
    }
}

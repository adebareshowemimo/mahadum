<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\LearnerProfile;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolJoinLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_individual_joins_with_a_seat_and_old_or_foreign_links_cannot_be_used(): void
    {
        $this->seedRbac();
        $school = Organization::create(['name' => 'School', 'slug' => 'join-school', 'type' => 'school', 'status' => 'active']);
        $other = Organization::create(['name' => 'Other', 'slug' => 'other-school', 'type' => 'school', 'status' => 'active']);
        $school->seatAllocations()->create(['total_purchased' => 1, 'active_filled' => 0, 'expires_at' => now()->addMonth()]);
        $admin = $this->userWithRole('school_admin');
        $admin->update(['status' => 'active']);
        $school->members()->attach($admin->id, ['role' => 'school_admin', 'status' => 'active']);
        $student = $this->userWithRole('student');
        $student->update(['signup_account_type' => 'individual', 'status' => 'active']);
        $learner = LearnerProfile::create(['user_id' => $student->id, 'display_name' => 'Amara']);

        $this->actingAsUser($admin);
        $this->getJson("/api/v1/schools/{$other->id}/join-link")->assertForbidden();
        $first = $this->postJson("/api/v1/schools/{$school->id}/join-link")->assertOk()->json('data.url');
        $firstToken = basename($first);
        $this->getJson("/api/v1/school-join/{$firstToken}")->assertOk()->assertJsonPath('data.school', 'School');
        $second = $this->postJson("/api/v1/schools/{$school->id}/join-link")->assertOk()->json('data.url');
        $token = basename($second);
        $this->getJson("/api/v1/school-join/{$firstToken}")->assertNotFound();

        $this->actingAsUser($student);
        $this->postJson("/api/v1/school-join/{$token}")->assertOk()->assertJsonPath('data.learner_id', $learner->id);
        $this->postJson("/api/v1/school-join/{$token}")->assertOk();
        $this->assertSame($school->id, $learner->fresh()->organization_id);
        $this->assertSame(1, $school->seatAllocations()->first()->active_filled);
        $this->assertSame('individual', $student->fresh()->signup_account_type);
        $secondStudent = $this->userWithRole('student');
        $secondStudent->update(['status' => 'active']);
        LearnerProfile::create(['user_id' => $secondStudent->id, 'display_name' => 'Bello']);
        $this->actingAsUser($secondStudent);
        $this->postJson("/api/v1/school-join/{$token}")->assertStatus(422);

        $this->actingAsUser($admin);
        $this->deleteJson("/api/v1/schools/{$school->id}/join-link")->assertOk();
        $this->getJson("/api/v1/school-join/{$token}")->assertStatus(410);
        $this->actingAsUser($student);
        $this->postJson("/api/v1/school-join/{$token}")->assertStatus(410);
    }

    public function test_parent_chooses_only_their_child_and_no_account_type_changes(): void
    {
        $this->seedRbac();
        $school = Organization::create(['name' => 'School', 'slug' => 'family-school', 'type' => 'school', 'status' => 'active']);
        $school->seatAllocations()->create(['total_purchased' => 1, 'active_filled' => 0, 'expires_at' => now()->addMonth()]);
        $admin = $this->userWithRole('school_admin');
        $admin->update(['status' => 'active']);
        $school->members()->attach($admin->id, ['role' => 'school_admin', 'status' => 'active']);
        $parent = $this->userWithRole('parent');
        $parent->update(['signup_account_type' => 'family', 'status' => 'active']);
        $family = Family::create(['owner_user_id' => $parent->id, 'name' => 'Family']);
        $child = LearnerProfile::create(['family_id' => $family->id, 'display_name' => 'Child']);
        $stranger = LearnerProfile::create(['display_name' => 'Stranger']);
        $this->actingAsUser($admin);
        $token = basename($this->postJson("/api/v1/schools/{$school->id}/join-link")->json('data.url'));
        $unlinkedParent = $this->userWithRole('parent');
        $unlinkedParent->update(['status' => 'active']);
        $this->actingAsUser($unlinkedParent);
        $this->postJson("/api/v1/school-join/{$token}", ['learner_id' => $stranger->id])->assertForbidden();
        $this->actingAsUser($parent);
        $this->postJson("/api/v1/school-join/{$token}", ['learner_id' => $stranger->id])->assertNotFound();
        $this->postJson("/api/v1/school-join/{$token}", ['learner_id' => $child->id])->assertOk();
        $this->assertSame($school->id, $child->fresh()->organization_id);
        $this->assertSame('family', $parent->fresh()->signup_account_type);
        $this->assertDatabaseMissing('organization_user', ['organization_id' => $school->id, 'user_id' => $parent->id]);
    }
}

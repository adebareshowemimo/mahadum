<?php

namespace Tests\Feature;

use App\Console\Commands\AccrueTeacherCompensation;
use App\Models\ClassEnrollment;
use App\Models\LearnerProfile;
use App\Models\Organization;
use App\Models\SchoolClass;
use App\Models\SeatAllocation;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherCompensationTest extends TestCase
{
    use RefreshDatabase;

    private function orgWithActiveSeats(): Organization
    {
        $org = Organization::create(['name' => 'Greenfield', 'type' => 'school', 'slug' => 'greenfield', 'status' => 'active']);
        SeatAllocation::create(['organization_id' => $org->id, 'total_purchased' => 10, 'active_filled' => 2]);

        return $org;
    }

    private function classWithLearners(Organization $org, User $teacher, int $count): SchoolClass
    {
        $class = SchoolClass::create(['organization_id' => $org->id, 'name' => 'JSS1', 'teacher_user_id' => $teacher->id]);

        for ($i = 0; $i < $count; $i++) {
            $learner = LearnerProfile::create(['organization_id' => $org->id, 'display_name' => "Student $i"]);
            ClassEnrollment::create(['school_class_id' => $class->id, 'learner_profile_id' => $learner->id]);
        }

        return $class;
    }

    public function test_accrual_command_credits_teachers_with_actively_seated_classes(): void
    {
        $this->seedRbac();
        app(Settings::class)->set(['teacher_compensation.rate_per_student_minor' => 20_000]); // ₦200
        $org = $this->orgWithActiveSeats();
        $teacher = $this->userWithRole('teacher');
        $this->classWithLearners($org, $teacher, 3);

        $this->artisan(AccrueTeacherCompensation::class)->assertExitCode(0);

        $this->assertDatabaseHas('teacher_compensation_entries', [
            'teacher_user_id' => $teacher->id,
            'organization_id' => $org->id,
            'paying_student_count' => 3,
            'rate_minor' => 20_000,
            'amount_minor' => 60_000,
        ]);
    }

    public function test_accrual_skips_classes_in_orgs_without_active_seats(): void
    {
        $this->seedRbac();
        app(Settings::class)->set(['teacher_compensation.rate_per_student_minor' => 20_000]);
        $org = Organization::create(['name' => 'No Seats', 'type' => 'school', 'slug' => 'no-seats', 'status' => 'active']);
        $teacher = $this->userWithRole('teacher');
        $this->classWithLearners($org, $teacher, 5);

        $this->artisan(AccrueTeacherCompensation::class)->assertExitCode(0);

        $this->assertDatabaseMissing('teacher_compensation_entries', ['teacher_user_id' => $teacher->id]);
    }

    public function test_accrual_is_noop_when_rate_is_zero(): void
    {
        $this->seedRbac();
        $org = $this->orgWithActiveSeats();
        $teacher = $this->userWithRole('teacher');
        $this->classWithLearners($org, $teacher, 3);

        $this->artisan(AccrueTeacherCompensation::class)->assertExitCode(0);

        $this->assertDatabaseMissing('teacher_compensation_entries', ['teacher_user_id' => $teacher->id]);
    }

    public function test_teacher_sees_summary_and_requests_a_payout_within_balance(): void
    {
        $this->seedRbac();
        app(Settings::class)->set([
            'teacher_compensation.rate_per_student_minor' => 20_000,
            'referral.payout_floor_minor' => 100_000,
        ]);
        $org = $this->orgWithActiveSeats();
        $teacher = $this->userWithRole('teacher');
        $this->classWithLearners($org, $teacher, 5); // ₦1,000 accrual expected
        $this->artisan(AccrueTeacherCompensation::class);
        $this->actingAsUser($teacher);

        $this->getJson('/api/v1/teacher-compensation/summary')
            ->assertOk()
            ->assertJsonPath('data.available_minor', 100_000)
            ->assertJsonPath('data.accrued_total_minor', 100_000);

        $this->postJson('/api/v1/teacher-compensation/payouts/request', ['amount_minor' => 100_000], ['Idempotency-Key' => 'tc-1'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'requested');

        $this->assertDatabaseHas('payouts', [
            'beneficiary_type' => User::class,
            'beneficiary_id' => $teacher->id,
            'source' => 'teaching',
            'method' => 'bank',
            'amount_minor' => 100_000,
        ]);
    }

    public function test_payout_request_rejected_when_it_exceeds_available_balance(): void
    {
        $this->seedRbac();
        app(Settings::class)->set([
            'teacher_compensation.rate_per_student_minor' => 20_000,
            'referral.payout_floor_minor' => 100_000,
        ]);
        $org = $this->orgWithActiveSeats();
        $teacher = $this->userWithRole('teacher');
        $this->classWithLearners($org, $teacher, 2); // ₦400 accrued — below the floor/request
        $this->artisan(AccrueTeacherCompensation::class);
        $this->actingAsUser($teacher);

        $this->postJson('/api/v1/teacher-compensation/payouts/request', ['amount_minor' => 100_000], ['Idempotency-Key' => 'tc-2'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'insufficient_balance');
    }
}

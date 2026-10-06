<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\LearnerProfile;
use App\Models\Organization;
use App\Models\SchoolClass;
use App\Models\SeatAllocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class RosterImportTest extends TestCase
{
    use RefreshDatabase;

    private function school(string $slug = 'roster-school'): Organization
    {
        return Organization::create(['name' => 'Roster School', 'type' => 'school', 'slug' => $slug, 'status' => 'active']);
    }

    private function admin(Organization $school): User
    {
        $admin = $this->userWithRole('school_admin');
        $school->members()->attach($admin->id, ['role' => 'school_admin', 'status' => 'active']);
        $this->actingAsUser($admin);

        return $admin;
    }

    private function importCsv(Organization $school, string $csv, array $extra = [])
    {
        return $this->post("/api/v1/schools/{$school->id}/students/import", [
            ...$extra, 'file' => UploadedFile::fake()->createWithContent('roster.csv', $csv),
        ], ['Accept' => 'application/json']);
    }

    public function test_email_reuses_existing_school_learner_and_reimport_preserves_identity_roster_and_seats(): void
    {
        $this->seedRbac();
        $school = $this->school();
        $this->admin($school);
        $student = $this->userWithRole('student', ['email' => 'learner@example.test']);
        $school->members()->attach($student->id, ['role' => 'student', 'status' => 'active']);
        $family = Family::create(['owner_user_id' => User::factory()->create()->id, 'name' => 'Existing family']);
        $learner = LearnerProfile::create([
            'organization_id' => $school->id, 'user_id' => $student->id,
            'family_id' => $family->id, 'display_name' => 'Original learner', 'age_band' => 'A2',
        ]);
        $allocation = SeatAllocation::create(['organization_id' => $school->id, 'total_purchased' => 10, 'active_filled' => 1]);
        $class = SchoolClass::create(['organization_id' => $school->id, 'name' => 'Existing class']);
        $csv = "Firstname,Lastname,Email,Level\nCSV,Replacement, LEARNER@example.test ,B1\n";

        for ($i = 0; $i < 2; $i++) {
            $this->importCsv($school, $csv, ['class_id' => $class->id])->assertCreated()
                ->assertJsonPath('data.created', 0)->assertJsonPath('data.matched', 1)->assertJsonPath('data.errors', []);
        }

        $this->assertDatabaseCount('learner_profiles', 1);
        $this->assertDatabaseCount('class_enrollments', 1);
        $this->assertDatabaseHas('class_enrollments', ['school_class_id' => $class->id, 'learner_profile_id' => $learner->id]);
        $this->assertDatabaseHas('learner_profiles', [
            'id' => $learner->id, 'display_name' => 'Original learner', 'age_band' => 'A2',
            'family_id' => $family->id, 'user_id' => $student->id, 'organization_id' => $school->id,
        ]);
        $this->assertSame(1, $allocation->fresh()->active_filled);
        $this->assertTrue($student->fresh()->hasRole('student'));
    }

    public function test_bad_csv_rows_report_csv_row_numbers_and_do_not_create_profiles_or_fill_seats(): void
    {
        $this->seedRbac();
        $school = $this->school();
        $this->admin($school);
        $allocation = SeatAllocation::create(['organization_id' => $school->id, 'total_purchased' => 10, 'active_filled' => 0]);
        $csv = "Firstname,Lastname,Email,Level\nValid,Anonymous,,A1\nInvalid,Email,not-an-email,A1\n,,,A1\n";
        $csv .= str_repeat('x', 256).",Name,,A1\nLong,Level,,".str_repeat('x', 101)."\n";

        $result = $this->importCsv($school, $csv)->assertCreated()
            ->assertJsonPath('data.created', 1)->assertJsonPath('data.matched', 0)->assertJsonCount(4, 'data.errors');
        $this->assertSame([3, 4, 5, 6], array_column($result->json('data.errors'), 'row'));
        $this->assertDatabaseCount('learner_profiles', 1);
        $this->assertDatabaseHas('learner_profiles', ['display_name' => 'Valid Anonymous', 'user_id' => null]);
        $this->assertSame(1, $allocation->fresh()->active_filled);
    }

    public function test_invalid_class_rejects_row_before_creating_a_learner_or_filling_a_seat(): void
    {
        $this->seedRbac();
        $school = $this->school();
        $other = $this->school('other-school');
        $this->admin($school);
        $class = SchoolClass::create(['organization_id' => $other->id, 'name' => 'Foreign class']);
        $allocation = SeatAllocation::create(['organization_id' => $school->id, 'total_purchased' => 10, 'active_filled' => 0]);

        $this->importCsv($school, "Firstname,Lastname,Email,Level\nRejected,Learner,,A1\n", ['class_id' => $class->id])
            ->assertCreated()->assertJsonPath('data.created', 0)->assertJsonPath('data.matched', 0)
            ->assertJsonPath('data.errors.0.row', 2);
        $this->assertDatabaseCount('learner_profiles', 0);
        $this->assertDatabaseCount('class_enrollments', 0);
        $this->assertSame(0, $allocation->fresh()->active_filled);
    }

    public function test_unknown_foreign_and_ambiguous_emails_are_not_guessed_or_relinked(): void
    {
        $this->seedRbac();
        $school = $this->school();
        $other = $this->school('other-school');
        $this->admin($school);
        $foreignUser = User::factory()->create(['email' => 'foreign@example.test']);
        $foreign = LearnerProfile::create(['organization_id' => $other->id, 'user_id' => $foreignUser->id, 'display_name' => 'Foreign learner']);
        $ambiguous = User::factory()->create(['email' => 'ambiguous@example.test']);
        foreach (['First profile', 'Second profile'] as $name) {
            LearnerProfile::create(['organization_id' => $school->id, 'user_id' => $ambiguous->id, 'display_name' => $name]);
        }
        $usersBefore = User::count();
        $csv = "Firstname,Lastname,Email,Level\nUnknown,One,unknown@example.test,A1\nForeign,One,foreign@example.test,A1\nAmbiguous,One,ambiguous@example.test,A1\n";

        $result = $this->importCsv($school, $csv)->assertCreated()
            ->assertJsonPath('data.created', 0)->assertJsonPath('data.matched', 0)->assertJsonCount(3, 'data.errors');
        $this->assertSame($result->json('data.errors.0.error'), $result->json('data.errors.1.error'));
        $this->assertDatabaseCount('learner_profiles', 3);
        $this->assertSame($other->id, $foreign->fresh()->organization_id);
        $this->assertSame($usersBefore, User::count());
        $this->assertFalse($foreignUser->fresh()->hasRole('student'));
    }

    public function test_legacy_three_column_and_display_name_csv_formats_remain_supported(): void
    {
        $this->seedRbac();
        $school = $this->school();
        $this->admin($school);
        foreach (["Firstname,Lastname,Level\nLegacy,One,A1\n", "display_name,level\nLegacy Two,A2\n", "Legacy,Three,A1\n"] as $csv) {
            $this->importCsv($school, $csv)->assertCreated()->assertJsonPath('data.created', 1)->assertJsonPath('data.errors', []);
        }
        $this->assertDatabaseCount('learner_profiles', 3);
    }

    public function test_bom_and_reordered_csv_headers_match_the_existing_account(): void
    {
        $this->seedRbac();
        $school = $this->school();
        $this->admin($school);
        $student = User::factory()->create(['email' => 'existing@example.test']);
        LearnerProfile::create(['organization_id' => $school->id, 'user_id' => $student->id, 'display_name' => 'Existing learner']);
        $csv = "\xEF\xBB\xBFLevel, EMAIL ,Lastname,Firstname\nA1,EXISTING@example.test,Name,CSV\n";

        $this->importCsv($school, $csv)->assertCreated()
            ->assertJsonPath('data.created', 0)->assertJsonPath('data.matched', 1)->assertJsonPath('data.errors', []);
        $this->assertDatabaseCount('learner_profiles', 1);
    }

    public function test_headerless_four_column_csv_can_match_an_existing_school_account(): void
    {
        $this->seedRbac();
        $school = $this->school();
        $this->admin($school);
        $student = User::factory()->create(['email' => 'existing@example.test']);
        LearnerProfile::create(['organization_id' => $school->id, 'user_id' => $student->id, 'display_name' => 'Existing learner']);

        $this->importCsv($school, "CSV,Name,existing@example.test,A1\n")->assertCreated()
            ->assertJsonPath('data.created', 0)->assertJsonPath('data.matched', 1)->assertJsonPath('data.errors', []);
        $this->assertDatabaseCount('learner_profiles', 1);
    }

    public function test_inline_email_matching_reuses_the_existing_school_profile(): void
    {
        $this->seedRbac();
        $school = $this->school();
        $this->admin($school);
        $student = User::factory()->create(['email' => 'existing@example.test']);
        $learner = LearnerProfile::create(['organization_id' => $school->id, 'user_id' => $student->id, 'display_name' => 'Existing learner']);

        $this->postJson("/api/v1/schools/{$school->id}/students/import", [
            'students' => [['display_name' => 'CSV name', 'email' => 'EXISTING@example.test']],
        ])->assertCreated()->assertJsonPath('data.created', 0)->assertJsonPath('data.matched', 1);
        $this->assertDatabaseCount('learner_profiles', 1);
        $this->assertSame('Existing learner', $learner->fresh()->display_name);
    }

    public function test_import_permissions_and_school_scope_remain_enforced(): void
    {
        $this->seedRbac();
        $school = $this->school();
        $other = $this->school('other-school');
        $csv = "Firstname,Lastname,Email,Level\nBlocked,Learner,,A1\n";
        $this->importCsv($school, $csv)->assertUnauthorized();
        $this->admin($school);
        $this->importCsv($other, $csv)->assertForbidden();
        $teacher = $this->userWithRole('teacher');
        $school->members()->attach($teacher->id, ['role' => 'teacher', 'status' => 'active']);
        $this->actingAsUser($teacher);
        $this->importCsv($school, $csv)->assertForbidden();
        $this->assertDatabaseCount('learner_profiles', 0);
    }
}

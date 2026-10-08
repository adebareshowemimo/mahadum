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
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class RosterImportTest extends TestCase
{
    use MakesContent, RefreshDatabase;

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

    public function test_blank_email_replay_preserves_distinct_same_name_rows_and_seats(): void
    {
        $this->seedRbac();
        $school = $this->school();
        $this->admin($school);
        $allocation = SeatAllocation::create(['organization_id' => $school->id, 'total_purchased' => 10, 'active_filled' => 0]);
        $csv = "Firstname,Lastname,Email,Level\nSame,Name,,L0\nSame,Name,,L0\nOther,Learner,,L1\n";

        $this->importCsv($school, $csv)->assertCreated()->assertJsonPath('data.created', 3);
        $ids = LearnerProfile::orderBy('id')->pluck('id')->all();
        // Row order, line endings and header case do not change import identity.
        $reordered = "FIRSTNAME,LASTNAME,EMAIL,LEVEL\r\nOther,Learner,,L1\r\nSame,Name,,L0\r\nSame,Name,,L0\r\n";
        $this->importCsv($school, $reordered)->assertCreated()
            ->assertJsonPath('data.created', 0)->assertJsonPath('data.matched', 3)->assertJsonPath('data.errors', []);

        $this->assertSame($ids, LearnerProfile::orderBy('id')->pluck('id')->all());
        $this->assertSame(3, $allocation->fresh()->active_filled);
    }

    public function test_replay_can_assign_a_class_without_new_seats_and_keeps_other_schools_separate(): void
    {
        $this->seedRbac();
        $school = $this->school();
        $other = $this->school('other-school');
        $this->admin($school);
        $allocation = SeatAllocation::create(['organization_id' => $school->id, 'total_purchased' => 10, 'active_filled' => 0]);
        $class = SchoolClass::create(['organization_id' => $school->id, 'name' => 'Class']);
        $csv = "Firstname,Lastname,Email,Level\nAmara,Okafor,,L0\n";
        $this->importCsv($school, $csv)->assertCreated()->assertJsonPath('data.created', 1);
        $this->importCsv($school, $csv, ['class_id' => $class->id])->assertCreated()
            ->assertJsonPath('data.created', 0)->assertJsonPath('data.matched', 1);
        $this->assertDatabaseCount('class_enrollments', 1);
        $this->assertSame(1, $allocation->fresh()->active_filled);
        $this->admin($other);
        $this->importCsv($other, $csv)->assertCreated()->assertJsonPath('data.created', 1);
        $this->assertDatabaseCount('learner_profiles', 2);
    }

    public function test_replay_does_not_recreate_a_deleted_or_moved_learner(): void
    {
        $this->seedRbac();
        $school = $this->school();
        $this->admin($school);
        $csv = "Firstname,Lastname,Email,Level\nAmara,Okafor,,L0\n";
        $this->importCsv($school, $csv)->assertCreated();
        LearnerProfile::firstOrFail()->delete();
        $this->importCsv($school, $csv)->assertCreated()->assertJsonPath('data.created', 0)->assertJsonCount(1, 'data.errors');
        $this->assertDatabaseCount('learner_profiles', 1);
    }

    public function test_changed_roster_cannot_guess_identity_from_names_but_student_ids_allow_distinct_namesakes(): void
    {
        $this->seedRbac();
        $school = $this->school();
        $this->admin($school);
        LearnerProfile::create(['organization_id' => $school->id, 'display_name' => 'Same Name']);

        $this->importCsv($school, "Firstname,Lastname,Email,Level\nSame,Name,,L0\n")
            ->assertCreated()->assertJsonPath('data.created', 0)->assertJsonCount(1, 'data.errors');
        $csv = "StudentId,Firstname,Lastname,Email,Level\nS-001,Same,Name,,L0\nS-002,Same,Name,,L0\n";
        $this->importCsv($school, $csv)->assertCreated()->assertJsonPath('data.created', 2);
        $this->importCsv($school, "StudentId,Firstname,Lastname,Email,Level\nS-002,Same,Name,,L0\n")
            ->assertCreated()->assertJsonPath('data.created', 0)->assertJsonPath('data.matched', 1);
        $this->assertDatabaseCount('learner_profiles', 3);
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
            'family_id' => $family->id, 'display_name' => 'Original learner', 'age_band' => 'L1',
        ]);
        $allocation = SeatAllocation::create(['organization_id' => $school->id, 'total_purchased' => 10, 'active_filled' => 1]);
        $class = SchoolClass::create(['organization_id' => $school->id, 'name' => 'Existing class']);
        $csv = "Firstname,Lastname,Email,Level\nCSV,Replacement, LEARNER@example.test ,L2\n";

        for ($i = 0; $i < 2; $i++) {
            $this->importCsv($school, $csv, ['class_id' => $class->id])->assertCreated()
                ->assertJsonPath('data.created', 0)->assertJsonPath('data.matched', 1)->assertJsonPath('data.errors', []);
        }

        $this->assertDatabaseCount('learner_profiles', 1);
        $this->assertDatabaseCount('class_enrollments', 1);
        $this->assertDatabaseHas('class_enrollments', ['school_class_id' => $class->id, 'learner_profile_id' => $learner->id]);
        $this->assertDatabaseHas('learner_profiles', [
            'id' => $learner->id, 'display_name' => 'Original learner', 'age_band' => 'L1',
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
        $csv = "Firstname,Lastname,Email,Level\nValid,Anonymous,,L0\nInvalid,Email,not-an-email,L0\n,,,L0\n";
        $csv .= str_repeat('x', 256).",Name,,L0\nLong,Level,,".str_repeat('x', 101)."\n";

        $result = $this->importCsv($school, $csv)->assertCreated()
            ->assertJsonPath('data.created', 0)->assertJsonPath('data.matched', 0)->assertJsonCount(4, 'data.errors');
        $this->assertSame([3, 4, 5, 6], array_column($result->json('data.errors'), 'row'));
        $this->assertDatabaseCount('learner_profiles', 0);
        $this->assertDatabaseCount('school_roster_identities', 0);
        $this->assertSame(0, $allocation->fresh()->active_filled);
    }

    public function test_missing_first_or_last_name_rejects_the_whole_file_without_writes(): void
    {
        $this->seedRbac();
        $school = $this->school();
        $this->admin($school);
        $allocation = SeatAllocation::create(['organization_id' => $school->id, 'total_purchased' => 10, 'active_filled' => 0]);
        $class = SchoolClass::create(['organization_id' => $school->id, 'name' => 'Class']);
        $csv = "Firstname,Lastname,Email,Level\nValid,Learner,,L0\nOnlyFirst,,,L0\n,OnlyLast,,L0\n";
        $result = $this->importCsv($school, $csv, ['class_id' => $class->id])->assertCreated()
            ->assertJsonPath('data.created', 0)->assertJsonCount(2, 'data.errors');
        $this->assertSame([3, 4], array_column($result->json('data.errors'), 'row'));
        $this->assertStringContainsString('First name and last name are required', $result->json('data.errors.0.error'));
        $this->assertDatabaseCount('learner_profiles', 0);
        $this->assertDatabaseCount('class_enrollments', 0);
        $this->assertDatabaseCount('school_roster_identities', 0);
        $this->assertSame(0, $allocation->fresh()->active_filled);
    }

    public function test_invalid_roster_levels_do_not_create_profiles_or_seats(): void
    {
        $this->seedRbac();
        $school = $this->school();
        $this->admin($school);
        $result = $this->importCsv($school, "Firstname,Lastname,Email,Level\nValid,Learner,,L0\nInvalid,Learner,,A1\n")
            ->assertCreated()->assertJsonPath('data.created', 0)->assertJsonPath('data.errors.0.row', 3);
        $this->assertStringContainsString('L0', $result->json('data.errors.0.error'));
        $this->assertDatabaseCount('learner_profiles', 0);
    }

    public function test_roster_level_maps_to_each_class_course_without_touching_age_or_unlocking_progress(): void
    {
        $this->seedRbac();
        $school = $this->school();
        $this->admin($school);
        $class = SchoolClass::create(['organization_id' => $school->id, 'name' => 'Class']);
        $levels = [];
        foreach (range(1, 2) as $unused) {
            $lesson = $this->publishedLesson();
            $course = $lesson->courseLevel->course;
            $level = $course->levels()->create(['title' => 'L2', 'position' => 2, 'is_free' => false]);
            $level->lessons()->create(['title' => 'Paid lesson', 'position' => 1, 'published_at' => now()]);
            $class->courseAssignments()->create(['course_id' => $course->id]);
            $levels[$course->id] = $level->id;
        }
        $this->importCsv($school, "Firstname,Lastname,Email,Level\nNew,Learner,,L2\n", ['class_id' => $class->id])
            ->assertCreated()->assertJsonPath('data.created', 1)->assertJsonPath('data.errors', []);
        $learner = LearnerProfile::firstOrFail();
        $this->assertNull($learner->age_band);
        $this->assertSame(2, $learner->roster_level_position);
        foreach ($levels as $courseId => $levelId) {
            $enrollment = $learner->enrollments()->where('course_id', $courseId)->firstOrFail();
            $this->assertEquals($levelId, $enrollment->assigned_course_level_id);
            $this->assertSame('locked', $enrollment->pathNodes()->whereHas('lesson', fn ($q) => $q->where('course_level_id', $levelId))->firstOrFail()->state);
        }
        $this->assertDatabaseCount('lesson_progress', 0);
    }

    public function test_dashboard_distinguishes_school_profiles_from_distinct_class_members(): void
    {
        $this->seedRbac();
        $school = $this->school();
        $this->admin($school);
        $learner = LearnerProfile::create(['organization_id' => $school->id, 'display_name' => 'In two classes']);
        foreach (range(1, 2) as $i) {
            $class = SchoolClass::create(['organization_id' => $school->id, 'name' => 'Class '.$i]);
            $class->enrollments()->create(['learner_profile_id' => $learner->id]);
        }
        LearnerProfile::create(['organization_id' => $school->id, 'display_name' => 'Unassigned']);
        $foreign = $this->school('foreign');
        LearnerProfile::create(['organization_id' => $foreign->id, 'display_name' => 'Foreign learner']);
        $this->getJson("/api/v1/schools/{$school->id}/dashboard")->assertOk()
            ->assertJsonPath('data.students', 2)->assertJsonPath('data.student_counts', ['total' => 2, 'in_classes' => 1, 'unassigned' => 1]);
    }

    public function test_invalid_class_rejects_row_before_creating_a_learner_or_filling_a_seat(): void
    {
        $this->seedRbac();
        $school = $this->school();
        $other = $this->school('other-school');
        $this->admin($school);
        $class = SchoolClass::create(['organization_id' => $other->id, 'name' => 'Foreign class']);
        $allocation = SeatAllocation::create(['organization_id' => $school->id, 'total_purchased' => 10, 'active_filled' => 0]);

        $this->importCsv($school, "Firstname,Lastname,Email,Level\nRejected,Learner,,L0\n", ['class_id' => $class->id])
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
        $csv = "Firstname,Lastname,Email,Level\nUnknown,One,unknown@example.test,L0\nForeign,One,foreign@example.test,L0\nAmbiguous,One,ambiguous@example.test,L0\n";

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
        foreach (["Firstname,Lastname,Level\nLegacy,One,L0\n", "display_name,level\nLegacy Two,L1\n", "Legacy,Three,L0\n"] as $csv) {
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
        $csv = "\xEF\xBB\xBFLevel, EMAIL ,Lastname,Firstname\nL0,EXISTING@example.test,Name,CSV\n";

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

        $this->importCsv($school, "CSV,Name,existing@example.test,L0\n")->assertCreated()
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
        $csv = "Firstname,Lastname,Email,Level\nBlocked,Learner,,L0\n";
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

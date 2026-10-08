<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\TeacherInvitation;
use App\Notifications\TeacherInvited;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TeacherCsvImportTest extends TestCase
{
    use RefreshDatabase;

    private function school(): Organization
    {
        Notification::fake();
        $this->seedRbac();
        $school = Organization::create(['name' => 'CSV School', 'slug' => 'teacher-csv', 'type' => 'school', 'status' => 'active']);
        $admin = $this->userWithRole('school_admin')->fresh();
        $school->members()->attach($admin->id, ['role' => 'school_admin', 'status' => 'active']);
        $this->actingAsUser($admin);

        return $school;
    }

    private function upload(Organization $school, string $csv, bool $preview = false)
    {
        return $this->post("/api/v1/schools/{$school->id}/teachers/import", ['file' => UploadedFile::fake()->createWithContent('teachers.csv', $csv), 'preview' => $preview ? '1' : '0'], ['Accept' => 'application/json']);
    }

    public function test_preview_and_replay_do_not_issue_duplicate_invitations_or_grant_memberships(): void
    {
        $school = $this->school();
        $csv = "Firstname,Lastname,Email,Phone\r\nAda,Okafor,ADA@example.com,08031234567\r\nObi,Eze,obi@example.com,+442079460958\r\n";
        $this->upload($school, $csv, true)->assertOk()->assertJsonPath('data.rows.0.phone', '+2348031234567')->assertJsonPath('data.rows.0.action', 'invite');
        $this->assertDatabaseCount('teacher_invitations', 0);
        Notification::assertNothingSent();
        $this->upload($school, $csv)->assertOk()->assertJsonPath('data.created', 2)->assertJsonPath('data.skipped', 0);
        $hashes = TeacherInvitation::orderBy('id')->pluck('token_hash')->all();
        $this->upload($school, "email,phone,lastname,firstname\nobi@example.com,+442079460958,Eze,Obi\nada@example.com,+2348031234567,Okafor,Ada\n")
            ->assertOk()->assertJsonPath('data.created', 0)->assertJsonPath('data.skipped', 2);
        $this->assertSame($hashes, TeacherInvitation::orderBy('id')->pluck('token_hash')->all());
        $this->assertDatabaseCount('organization_user', 1);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('teacher_invitations', ['email' => 'ada@example.com', 'invited_phone' => '+2348031234567']);
        Notification::assertSentOnDemandTimes(TeacherInvited::class, 2);
    }

    public function test_whole_file_validation_preserves_physical_rows_and_writes_nothing(): void
    {
        $school = $this->school();
        $csv = "Firstname,Lastname,Email,Phone\n\"Ada\nMary\",Okafor,ada@example.com,+2348031234567\nObi,,not-email,123\n";
        $result = $this->upload($school, $csv)->assertOk()->assertJsonPath('data.created', 0);
        $this->assertSame([4], array_values(array_unique(array_column($result->json('data.errors'), 'row'))));
        $this->assertDatabaseCount('teacher_invitations', 0);
        Notification::assertNothingSent();
        $this->upload($school, "Firstname,Lastname,Email,Phone\nAda,Okafor,ada@example.com,+2348031234567\nObi,Eze,ADA@example.com,+2348031234568\n")
            ->assertOk()->assertJsonPath('data.errors.0.row', 3);
        $this->assertDatabaseCount('teacher_invitations', 0);
        $this->upload($school, "Name,Email\nAda,ada@example.com\n")->assertUnprocessable();
    }

    public function test_imported_parent_accepts_once_keeps_family_and_appears_in_teacher_picker(): void
    {
        $school = $this->school();
        $admin = auth()->user();
        $parent = $this->userWithRole('parent', ['email' => 'parent-csv@example.com', 'phone' => '+2348099991111'])->fresh();
        $family = Family::create(['owner_user_id' => $parent->id, 'name' => 'Existing family']);
        $csv = "Firstname,Lastname,Email,Phone\nTest,Parent,parent-csv@example.com,+2348031234567\n";
        $this->upload($school, $csv)->assertOk()->assertJsonPath('data.created', 1);
        $notification = Notification::sent(new AnonymousNotifiable, TeacherInvited::class)->sole();
        $token = basename($notification->toMail((object) [])->actionUrl);
        $this->actingAsUser($parent);
        $this->postJson("/api/v1/teacher-invitations/{$token}/accept")->assertOk();
        $this->postJson("/api/v1/teacher-invitations/{$token}/accept")->assertOk();
        $this->assertTrue($parent->fresh()->hasAllRoles(['parent', 'teacher']));
        $this->assertSame($family->id, $parent->ownedFamilies()->sole()->id);
        $this->assertSame('+2348099991111', $parent->fresh()->phone);
        $this->assertSame(1, OrganizationUser::where('organization_id', $school->id)->where('user_id', $parent->id)->count());
        $this->actingAsUser($admin);
        $this->upload($school, $csv)->assertOk()->assertJsonPath('data.created', 0)->assertJsonPath('data.skipped', 1);
        $this->getJson("/api/v1/schools/{$school->id}/teacher-directory")->assertOk()->assertJsonPath('data.teachers.0.id', $parent->id);
        $this->getJson("/api/v1/schools/{$school->id}/teachers")->assertOk()->assertJsonFragment(['id' => $parent->id]);
    }

    public function test_conflicting_membership_aborts_every_row_and_foreign_admin_and_teacher_cannot_import(): void
    {
        $school = $this->school();
        $conflict = $this->userWithRole('teacher', ['email' => 'conflict@example.com']);
        $school->members()->attach($conflict->id, ['role' => 'teacher', 'status' => 'suspended']);
        $csv = "Firstname,Lastname,Email,Phone\nAda,Okafor,ada@example.com,+2348031234567\nTest,Conflict,conflict@example.com,+2348031234568\n";
        $this->upload($school, $csv)->assertOk()->assertJsonPath('data.errors.0.row', 3)->assertJsonPath('data.created', 0);
        $this->assertDatabaseCount('teacher_invitations', 0);
        $this->actingAsUser($this->userWithRole('school_admin')->fresh());
        $this->upload($school, $csv)->assertForbidden();
        $this->actingAsUser($conflict->fresh());
        $this->upload($school, $csv)->assertForbidden();
        Notification::assertNothingSent();
    }
}

<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SchoolAndAdminTest extends TestCase
{
    use RefreshDatabase;

    private function org(string $slug = 'greenfield', string $status = 'pending'): Organization
    {
        return Organization::create(['name' => 'Greenfield', 'type' => 'school', 'slug' => $slug, 'status' => $status]);
    }

    public function test_school_admin_creates_class_buys_seats_sees_dashboard(): void
    {
        $this->seedRbac();
        $org = $this->org();
        $admin = $this->userWithRole('school_admin');
        $org->members()->attach($admin->id, ['role' => 'school_admin', 'status' => 'active']);
        $this->actingAsUser($admin);

        $this->postJson('/api/v1/classes', ['name' => 'JSS1', 'level' => 'JSS1'])->assertCreated();

        // 100 students → 100–249 band: ₦100,000 registration + 100 × ₦6,000 = ₦700,000
        // pre-tax, +7.5% VAT = ₦752,500.
        $this->postJson("/api/v1/schools/{$org->id}/seats/purchase", ['quantity' => 100, 'term_label' => 'T1'])
            ->assertCreated()
            ->assertJsonPath('data.band', '100–249 students')
            ->assertJsonPath('data.registration_minor', 10_000_000)
            ->assertJsonPath('data.amount_minor', 75_250_000);

        $this->getJson("/api/v1/schools/{$org->id}/dashboard")->assertOk()
            ->assertJsonPath('data.classes', 1)
            ->assertJsonPath('data.seats.purchased', 100);
    }

    public function test_roster_csv_uses_firstname_lastname_level_columns(): void
    {
        $this->seedRbac();
        $org = $this->org('roster-school', 'active');
        $admin = $this->userWithRole('school_admin');
        $org->members()->attach($admin->id, ['role' => 'school_admin', 'status' => 'active']);
        $this->actingAsUser($admin);

        $file = UploadedFile::fake()->createWithContent(
            'roster.csv',
            "Firstname,Lastname,Level\nAmara,Okafor,A1\nBello,Musa,A2\n",
        );

        $this->post('/api/v1/schools/'.$org->id.'/students/import', ['file' => $file], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.created', 2);

        $this->assertDatabaseHas('learner_profiles', ['organization_id' => $org->id, 'display_name' => 'Amara Okafor']);
        $this->assertDatabaseHas('learner_profiles', ['organization_id' => $org->id, 'display_name' => 'Bello Musa']);
    }

    public function test_school_admin_can_only_assign_active_teachers_from_the_same_school(): void
    {
        $this->seedRbac();
        $org = $this->org('teacher-school', 'active');
        $foreign = $this->org('foreign-teacher-school', 'active');
        $admin = $this->userWithRole('school_admin');
        $teacher = $this->userWithRole('teacher');
        $foreignTeacher = $this->userWithRole('teacher');
        $org->members()->attach($admin->id, ['role' => 'school_admin', 'status' => 'active']);
        $org->members()->attach($teacher->id, ['role' => 'teacher', 'status' => 'active']);
        $foreign->members()->attach($foreignTeacher->id, ['role' => 'teacher', 'status' => 'active']);
        $this->actingAsUser($admin);

        $this->getJson("/api/v1/schools/{$org->id}/teachers")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $teacher->id);

        $classId = $this->postJson('/api/v1/classes', ['name' => 'JSS1', 'teacher_user_id' => $teacher->id])
            ->assertCreated()
            ->json('data.id');
        $this->postJson('/api/v1/classes', ['name' => 'JSS2', 'teacher_user_id' => $foreignTeacher->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('teacher_user_id');

        $this->actingAsUser($teacher);
        $this->postJson("/api/v1/classes/{$classId}/assignments", ['title' => 'Spellings'])
            ->assertCreated();
    }

    public function test_school_admin_blocked_from_foreign_org(): void
    {
        $this->seedRbac();
        $home = $this->org('home');
        $foreign = $this->org('foreign');
        $admin = $this->userWithRole('school_admin');
        $home->members()->attach($admin->id, ['role' => 'school_admin', 'status' => 'active']);
        $this->actingAsUser($admin);

        $this->getJson("/api/v1/schools/{$foreign->id}/dashboard")->assertStatus(403);
    }

    public function test_super_admin_activates_org_with_audit_log(): void
    {
        $this->seedRbac();
        $org = $this->org();
        $this->actingAsUser($this->userWithRole('super_admin'));

        $this->postJson("/api/v1/admin/organizations/{$org->id}/activate")
            ->assertOk()->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('organizations', ['id' => $org->id, 'status' => 'active']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'organization.activated', 'subject_id' => $org->id]);
    }

    public function test_org_detail_returns_classes_referrals_and_audit(): void
    {
        $this->seedRbac();
        $org = $this->org();
        $this->actingAsUser($this->userWithRole('super_admin'));

        $this->getJson("/api/v1/admin/organizations/{$org->id}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'name', 'members', 'invoices', 'classes', 'referrals', 'audit']]);
    }

    public function test_super_admin_invites_school_admin(): void
    {
        $this->seedRbac();
        $org = $this->org('active-school', 'active');
        $this->actingAsUser($this->userWithRole('super_admin'));

        $this->postJson("/api/v1/admin/organizations/{$org->id}/invite-admin", [
            'first_name' => 'Ngozi',
            'last_name' => 'Eze',
            'email' => 'ngozi@greenfield.test',
        ])->assertCreated()->assertJsonPath('data.email', 'ngozi@greenfield.test');

        $admin = User::where('email', 'ngozi@greenfield.test')->firstOrFail();
        $this->assertTrue($admin->hasRole('school_admin'));
        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $org->id,
            'user_id' => $admin->id,
            'role' => 'school_admin',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'organization.admin_invited', 'subject_id' => $org->id]);
    }

    public function test_invite_admin_rejects_existing_email(): void
    {
        $this->seedRbac();
        $org = $this->org('active-school', 'active');
        $existing = User::factory()->create(['email' => 'taken@greenfield.test']);
        $this->actingAsUser($this->userWithRole('super_admin'));

        $this->postJson("/api/v1/admin/organizations/{$org->id}/invite-admin", [
            'first_name' => 'Ada',
            'last_name' => 'Obi',
            'email' => $existing->email,
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_super_admin_metrics_and_promo_and_payout_approve(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('super_admin'));

        $this->getJson('/api/v1/admin/metrics')->assertOk()->assertJsonStructure(['data' => ['users', 'organizations']]);

        $this->postJson('/api/v1/admin/promo-codes', ['code' => 'WELCOME10', 'discount_type' => 'percent', 'value' => 10])
            ->assertCreated()->assertJsonPath('data.code', 'WELCOME10');

        $ben = User::factory()->create();
        $payout = new Payout(['amount_minor' => 800000, 'method' => 'bank', 'status' => 'requested', 'requested_at' => now()]);
        $payout->beneficiary()->associate($ben);
        $payout->save();

        $this->postJson("/api/v1/admin/payouts/{$payout->id}/approve")
            ->assertOk()->assertJsonPath('data.status', 'approved');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Referral;
use App\Services\Referral\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_account_role_can_read_only_its_own_referral_activity(): void
    {
        $this->seedRbac();
        $service = app(ReferralService::class);
        $referred = $this->userWithRole('parent', ['email' => 'referred@example.test', 'phone' => '+2348012345678', 'last_login_at' => now()]);
        foreach (['super_admin', 'content_owner', 'teacher', 'supervisor', 'school_admin', 'parent', 'student'] as $role) {
            $owner = $this->userWithRole($role);
            $code = $service->codeFor($owner);
            Referral::create(['referral_code_id' => $code->id, 'referred_user_id' => $referred->id,
                'status' => 'qualified', 'signed_up_at' => now(), 'activated_at' => now()]);
            $this->actingAsUser($owner);
            $this->getJson('/api/v1/referrals/activations?owner_id='.$referred->id)
                ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.code', $code->code)->assertJsonPath('data.0.status', 'active')
                ->assertJsonPath('data.0.activated_at', now()->toDateString())
                ->assertJsonPath('data.0.via_email', $referred->email)->assertJsonPath('data.0.via_phone', $referred->phone);
        }
    }

    public function test_reading_an_empty_profile_does_not_issue_a_code_or_grant_referral_management(): void
    {
        $this->getJson('/api/v1/referrals/activations')->assertUnauthorized();
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('content_owner'));
        $this->getJson('/api/v1/referrals/activations')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
        $this->assertDatabaseCount('referral_codes', 0);
        $this->getJson('/api/v1/referral-code')->assertForbidden();
        $this->postJson('/api/v1/referrals/invitations', ['channel' => 'email', 'contact' => 'friend@example.test'])->assertForbidden();
    }

    public function test_school_activity_includes_both_contacts_statuses_search_and_paging_without_personal_or_other_school_rows(): void
    {
        $this->seedRbac();
        $school = Organization::create(['name' => 'School A', 'type' => 'school', 'slug' => 'school-a', 'status' => 'active']);
        $otherSchool = Organization::create(['name' => 'School B', 'type' => 'school', 'slug' => 'school-b', 'status' => 'active']);
        $admin = $this->userWithRole('school_admin');
        $school->members()->attach($admin->id, ['role' => 'school_admin', 'status' => 'active']);
        $service = app(ReferralService::class);
        $code = $service->codeFor($school);
        $legacy = $code->replicate();
        $legacy->code = 'LEGACYSCHOOL';
        $legacy->save();
        $active = $this->userWithRole('parent', ['email' => 'active@example.test', 'phone' => '+2348011111111', 'last_login_at' => now()]);
        $inactive = $this->userWithRole('parent', ['email' => 'inactive@example.test', 'phone' => null, 'last_login_at' => now()->subDays(40)]);
        Referral::create(['referral_code_id' => $code->id, 'referred_user_id' => $active->id,
            'status' => 'qualified', 'signed_up_at' => now(), 'activated_at' => now()->subDay()]);
        Referral::create(['referral_code_id' => $legacy->id, 'referred_user_id' => $inactive->id,
            'status' => 'qualified', 'signed_up_at' => now()->subDay(), 'activated_at' => now()->subDays(2)]);
        Referral::create(['referral_code_id' => $code->id, 'referred_user_id' => $inactive->id,
            'status' => 'signed_up', 'signed_up_at' => now()->subDays(2), 'contact_channel' => 'phone', 'contact_value' => '+2348022222222']);
        foreach ([$service->codeFor($admin), $service->codeFor($otherSchool)] as $unrelated) {
            Referral::create(['referral_code_id' => $unrelated->id, 'referred_user_id' => $active->id,
                'status' => 'qualified', 'signed_up_at' => now(), 'activated_at' => now()]);
        }
        $this->actingAsUser($admin);
        $url = "/api/v1/schools/{$school->id}/referrals/activations";
        $this->getJson($url.'?per_page=1')->assertOk()->assertJsonPath('meta.total', 3)->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('data.0.status', 'active')->assertJsonPath('data.0.activated_at', now()->subDay()->toDateString())
            ->assertJsonPath('data.0.via_email', $active->email)->assertJsonPath('data.0.via_phone', $active->phone);
        $this->getJson($url.'?per_page=1&page=2')->assertOk()->assertJsonPath('data.0.sn', 2)
            ->assertJsonPath('data.0.status', 'inactive')->assertJsonPath('data.0.code', 'LEGACYSCHOOL')->assertJsonPath('data.0.via_phone', null);
        $this->getJson($url.'?search=2348022222222')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'pending')->assertJsonPath('data.0.activated_at', null)
            ->assertJsonPath('data.0.via_email', $inactive->email)->assertJsonPath('data.0.via_phone', '+2348022222222');
        $this->getJson($url.'?search=unmatched')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson("/api/v1/schools/{$otherSchool->id}/referrals/activations")->assertForbidden();
        $this->getJson("/api/v1/schools/{$otherSchool->id}/referrals/activations", ['X-Organization-Id' => $otherSchool->id])->assertForbidden();
        $this->getJson('/api/v1/referrals/activations')->assertOk()->assertJsonPath('meta.total', 1);
    }
}

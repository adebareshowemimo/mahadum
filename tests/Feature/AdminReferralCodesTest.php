<?php

namespace Tests\Feature;

use App\Models\Referral;
use App\Services\Referral\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReferralCodesTest extends TestCase
{
    use RefreshDatabase;

    public function test_per_code_dashboard_reports_activation_channel_and_active_split(): void
    {
        $this->seedRbac();
        $admin = $this->userWithRole('super_admin');
        $owner = $this->userWithRole('parent', ['email' => 'owner@test.local']);
        $code = app(ReferralService::class)->codeFor($owner);

        // Two activated via email (one active, one not), one activated via phone (active).
        $active1 = $this->userWithRole('parent', ['email' => 'a1@test.local']);
        $active1->update(['last_login_at' => now()]);
        $stale = $this->userWithRole('parent', ['email' => 's@test.local']);
        $stale->update(['last_login_at' => now()->subDays(90)]);
        $active2 = $this->userWithRole('parent', ['email' => 'a2@test.local']);
        $active2->update(['last_login_at' => now()->subDay()]);

        Referral::create(['referral_code_id' => $code->id, 'referred_user_id' => $active1->id, 'status' => 'qualified',
            'signed_up_at' => now(), 'activated_at' => now(), 'contact_channel' => 'email', 'contact_value' => 'a1@test.local']);
        Referral::create(['referral_code_id' => $code->id, 'referred_user_id' => $stale->id, 'status' => 'qualified',
            'signed_up_at' => now(), 'activated_at' => now(), 'contact_channel' => 'email', 'contact_value' => 's@test.local']);
        Referral::create(['referral_code_id' => $code->id, 'referred_user_id' => $active2->id, 'status' => 'qualified',
            'signed_up_at' => now(), 'activated_at' => now(), 'contact_channel' => 'phone', 'contact_value' => '234800']);
        // A pending (not activated) referral must not be counted.
        Referral::create(['referral_code_id' => $code->id, 'referred_user_id' => null, 'status' => 'pending', 'signed_up_at' => now()]);

        $this->actingAsUser($admin);
        $this->getJson('/api/v1/admin/referrals/codes')
            ->assertOk()
            ->assertJsonPath('data.0.count_activated', 3)
            ->assertJsonPath('data.0.via_email', 2)
            ->assertJsonPath('data.0.via_phone', 1)
            ->assertJsonPath('data.0.active_count', 2)
            ->assertJsonPath('data.0.inactive_count', 1);
    }
}

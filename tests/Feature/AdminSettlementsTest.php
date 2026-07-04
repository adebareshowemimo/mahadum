<?php

namespace Tests\Feature;

use App\Models\Commission;
use App\Models\Referral;
use App\Models\User;
use App\Services\Referral\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettlementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settlements_surface_clawback_pending_total(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('super_admin'));

        $referrer = $this->userWithRole('parent');
        $code = app(ReferralService::class)->codeFor($referrer);
        $referral = Referral::create(['referral_code_id' => $code->id, 'referred_user_id' => User::factory()->create()->id, 'status' => 'reversed', 'signed_up_at' => now()]);

        $commission = new Commission(['amount_minor' => 30000, 'status' => 'clawback_pending']);
        $commission->referral()->associate($referral);
        $commission->beneficiary()->associate($referrer);
        $commission->save();

        $this->getJson('/api/v1/admin/settlements')
            ->assertOk()
            ->assertJsonPath('data.clawback.pending_count', 1)
            ->assertJsonPath('data.clawback.pending_minor', 30000);
    }
}

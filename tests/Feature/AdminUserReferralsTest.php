<?php

namespace Tests\Feature;

use App\Models\Referral;
use App\Models\User;
use App\Services\Referral\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserReferralsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_a_users_outbound_and_inbound_referral_activity(): void
    {
        $this->seedRbac();

        $referrer = $this->userWithRole('parent', ['email' => 'ref@test.local']);
        $code = app(ReferralService::class)->codeFor($referrer)->code;

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Ref', 'last_name' => 'Erred', 'email' => 'referred@test.local', 'phone' => '+2348012349999',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!', 'device_name' => 'd',
            'referral_code' => $code,
        ], ['X-Device-Id' => 'devA'])->assertCreated();

        $referred = User::where('email', 'referred@test.local')->firstOrFail();

        $this->actingAsUser($this->userWithRole('super_admin'));

        // The referrer's view: one person referred, none activated yet.
        $this->getJson("/api/v1/admin/users/{$referrer->id}/referrals")
            ->assertOk()
            ->assertJsonPath('data.as_referrer.code', $code)
            ->assertJsonPath('data.as_referrer.total_referred', 1)
            ->assertJsonPath('data.as_referrer.total_qualified', 0)
            ->assertJsonPath('data.as_referrer.activations.0.email', 'referred@test.local')
            ->assertJsonPath('data.as_referrer.activations.0.status', 'pending')
            ->assertJsonPath('data.as_referred', null);

        // The referred user's view: shows where they came from.
        $this->getJson("/api/v1/admin/users/{$referred->id}/referrals")
            ->assertOk()
            ->assertJsonPath('data.as_referred.code', $code)
            ->assertJsonPath('data.as_referred.referrer_name', $referrer->name)
            ->assertJsonPath('data.as_referrer.total_referred', 0);

        $this->assertSame(1, Referral::where('referred_user_id', $referred->id)->count());
    }

    public function test_it_requires_the_users_view_permission(): void
    {
        $this->seedRbac();
        $target = $this->userWithRole('parent');
        $this->actingAsUser($this->userWithRole('parent'));

        $this->getJson("/api/v1/admin/users/{$target->id}/referrals")->assertStatus(403);
    }
}

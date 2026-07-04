<?php

namespace Tests\Feature;

use App\Models\Payout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayoutSodTest extends TestCase
{
    use RefreshDatabase;

    private function payoutFor(User $beneficiary, string $status = 'requested'): Payout
    {
        $payout = new Payout(['amount_minor' => 100000, 'method' => 'bank', 'status' => $status, 'requested_at' => now()]);
        $payout->beneficiary()->associate($beneficiary);
        $payout->save();

        return $payout;
    }

    public function test_super_admin_cannot_approve_their_own_payout(): void
    {
        $this->seedRbac();
        $admin = $this->actingAsUser($this->userWithRole('super_admin'));
        $payout = $this->payoutFor($admin);

        // The super_admin Gate::before bypass lets the route guard through, but
        // the controller's separation-of-duties check still blocks self-approval.
        $this->postJson("/api/v1/admin/payouts/{$payout->id}/approve")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'payout_self_approval');

        $this->assertDatabaseHas('payouts', ['id' => $payout->id, 'status' => 'requested', 'approved_by' => null]);
    }

    public function test_super_admin_can_approve_another_users_payout(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('super_admin'));
        $payout = $this->payoutFor(User::factory()->create());

        $this->postJson("/api/v1/admin/payouts/{$payout->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_already_approved_payout_cannot_be_reapproved(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('super_admin'));
        $payout = $this->payoutFor(User::factory()->create(), 'approved');

        $this->postJson("/api/v1/admin/payouts/{$payout->id}/approve")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'payout_not_pending');
    }
}

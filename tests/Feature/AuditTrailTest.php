<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_payout_approval_is_audited(): void
    {
        $this->seedRbac();
        $admin = $this->actingAsUser($this->userWithRole('super_admin'));

        $payout = new Payout(['amount_minor' => 800000, 'method' => 'bank', 'status' => 'requested', 'requested_at' => now()]);
        $payout->beneficiary()->associate(User::factory()->create());
        $payout->save();

        $this->postJson("/api/v1/admin/payouts/{$payout->id}/approve")->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'payout.approved',
            'subject_type' => Payout::class,
            'subject_id' => $payout->id,
        ]);
    }

    public function test_setting_family_pin_is_audited(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $family = Family::create(['owner_user_id' => $parent->id, 'name' => 'Fam']);

        $this->putJson('/api/v1/family/pin', ['pin' => '4321'])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $parent->id,
            'action' => 'family.pin_set',
            'subject_type' => Family::class,
            'subject_id' => $family->id,
        ]);
    }
}

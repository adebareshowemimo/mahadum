<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\LearnerProfile;
use App\Models\User;
use App\Services\Family\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ParentRoleFamilyTest extends TestCase
{
    use RefreshDatabase;

    public function test_granting_parent_to_a_school_account_provisions_one_family_and_all_parent_pages(): void
    {
        $this->seedRbac();
        $admin = $this->userWithRole('super_admin');
        $user = $this->userWithRole('school_admin');
        $this->actingAsUser($admin);

        $grant = fn () => $this->postJson("/api/v1/admin/users/{$user->id}/roles", ['role' => 'parent', 'action' => 'assign']);
        $grant()->assertOk();
        $grant()->assertOk();
        $family = $user->ownedFamilies()->sole();
        $this->assertSame(1, FamilyMember::where('family_id', $family->id)->where('user_id', $user->id)->where('is_account_owner', true)->count());

        $this->actingAsUser($user->fresh());
        $this->getJson('/api/v1/family')->assertOk()->assertJsonPath('data.id', $family->id);
        $this->getJson('/api/v1/wallet')->assertOk();
        $this->getJson('/api/v1/reviews/pending')->assertOk();
    }

    public function test_existing_parent_missing_a_family_can_be_repaired_by_idempotent_parent_grant(): void
    {
        $this->seedRbac();
        $user = $this->userWithRole('parent');
        $this->actingAsUser($user);
        $this->getJson('/api/v1/family')->assertNotFound();
        $this->getJson('/api/v1/wallet')->assertNotFound();
        $this->getJson('/api/v1/reviews/pending')->assertNotFound();

        $this->actingAsUser($this->userWithRole('super_admin'));
        $this->postJson("/api/v1/admin/users/{$user->id}/roles", ['role' => 'parent', 'action' => 'assign'])->assertOk();

        $this->actingAsUser($user->fresh());
        $this->getJson('/api/v1/family')->assertOk();
        $this->getJson('/api/v1/wallet')->assertOk();
        $this->getJson('/api/v1/reviews/pending')->assertOk();
    }

    public function test_parent_school_parent_changes_preserve_family_learners_and_wallet(): void
    {
        $this->seedRbac();
        $user = $this->userWithRole('parent');
        $family = Family::create(['owner_user_id' => $user->id, 'name' => 'Existing household']);
        $learner = LearnerProfile::create(['family_id' => $family->id, 'display_name' => 'Existing child']);
        $wallet = app(WalletService::class)->walletFor($family);
        $wallet->update(['coin_balance' => 37]);
        $this->actingAsUser($this->userWithRole('super_admin'));

        foreach ([['parent', 'revoke'], ['school_admin', 'assign'], ['school_admin', 'revoke'], ['parent', 'assign']] as [$role, $action]) {
            $this->postJson("/api/v1/admin/users/{$user->id}/roles", compact('role', 'action'))->assertOk();
        }

        $this->assertSame($family->id, $user->ownedFamilies()->sole()->id);
        $this->assertSame($family->id, $learner->fresh()->family_id);
        $this->assertSame(37, $wallet->fresh()->coin_balance);
        $this->actingAsUser($user->fresh());
        $this->getJson('/api/v1/family')->assertOk()->assertJsonPath('data.name', 'Existing household');
        $this->getJson('/api/v1/wallet')->assertOk();
        $this->getJson('/api/v1/reviews/pending')->assertOk();
    }

    public function test_admin_created_parent_has_a_family(): void
    {
        Notification::fake();
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('super_admin'));
        $response = $this->postJson('/api/v1/admin/users', [
            'first_name' => 'Test', 'last_name' => 'Parent', 'email' => 'parent-role@example.test', 'role' => 'parent',
        ])->assertCreated();
        $this->assertDatabaseHas('families', ['owner_user_id' => $response->json('data.id')]);
    }

    public function test_non_admin_cannot_grant_parent_or_create_a_household_for_another_user(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('parent'));
        $other = User::factory()->create();
        $this->postJson("/api/v1/admin/users/{$other->id}/roles", ['role' => 'parent', 'action' => 'assign'])->assertForbidden();
        $this->assertDatabaseMissing('families', ['owner_user_id' => $other->id]);
    }

    public function test_deleted_families_are_not_replaced_and_failed_grant_is_atomic(): void
    {
        $this->seedRbac();
        $user = User::factory()->create();
        $family = Family::create(['owner_user_id' => $user->id, 'name' => 'Deleted household']);
        $family->delete();
        $this->actingAsUser($this->userWithRole('super_admin'));
        $this->postJson("/api/v1/admin/users/{$user->id}/roles", ['role' => 'parent', 'action' => 'assign'])->assertUnprocessable();
        $this->assertFalse($user->fresh()->hasRole('parent'));
        $this->assertSame(1, Family::withTrashed()->where('owner_user_id', $user->id)->count());
    }
}

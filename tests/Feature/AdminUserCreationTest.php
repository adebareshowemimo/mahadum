<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminUserCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_creates_an_organization_user_with_a_set_password_invitation(): void
    {
        Notification::fake();
        $this->seedRbac();
        $admin = $this->actingAsUser($this->userWithRole('super_admin'));
        $organization = Organization::create([
            'name' => 'Greenfield',
            'type' => 'school',
            'slug' => 'greenfield',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/admin/users', [
            'first_name' => 'Tola',
            'last_name' => 'Teacher',
            'email' => 'tola@example.test',
            'role' => 'teacher',
            'organization_id' => $organization->id,
        ])->assertCreated()
            ->assertJsonPath('data.email', 'tola@example.test')
            ->assertJsonPath('meta.invitation_sent', true);

        $user = User::findOrFail($response->json('data.id'));
        $this->assertTrue($user->hasRole('teacher'));
        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => 'teacher',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'user.created',
            'subject_id' => $user->id,
        ]);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_creation_rejects_duplicate_email_and_unsafe_role_membership(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('super_admin'));
        $existing = User::factory()->create(['email' => 'used@example.test']);
        $organization = Organization::create([
            'name' => 'Greenfield',
            'type' => 'school',
            'slug' => 'greenfield',
            'status' => 'active',
        ]);

        $this->postJson('/api/v1/admin/users', [
            'first_name' => 'Unsafe',
            'last_name' => 'Admin',
            'email' => $existing->email,
            'role' => 'super_admin',
            'organization_id' => $organization->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'organization_id']);
    }

    public function test_non_super_admin_cannot_create_a_global_user(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('parent'));

        $this->postJson('/api/v1/admin/users', [
            'first_name' => 'Blocked',
            'last_name' => 'User',
            'email' => 'blocked@example.test',
            'role' => 'content_owner',
        ])->assertForbidden();
    }
}

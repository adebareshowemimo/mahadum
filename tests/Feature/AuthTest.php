<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user_with_first_and_last_name_and_family(): void
    {
        $this->seedRbac();

        $res = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Funmi', 'last_name' => 'Adeyemi',
            'email' => 'funmi@test.local', 'phone' => '+2348012345678', 'password' => 'Password123!',
            'password_confirmation' => 'Password123!', 'device_name' => 'iPhone', 'date_of_birth' => '1990-04-18',
        ]);

        $res->assertCreated()
            ->assertJsonPath('data.user.first_name', 'Funmi')
            ->assertJsonPath('data.user.name', 'Funmi Adeyemi')
            ->assertJsonPath('data.abilities', ['parent']);

        $this->assertDatabaseHas('users', ['email' => 'funmi@test.local', 'first_name' => 'Funmi', 'last_name' => 'Adeyemi', 'phone' => '+2348012345678']);
        $this->assertSame('1990-04-18', User::where('email', 'funmi@test.local')->firstOrFail()->date_of_birth?->toDateString());
        $this->assertDatabaseHas('families', ['name' => "Funmi's Family"]);
    }

    public function test_learner_registration_creates_a_direct_profile_exposed_by_me(): void
    {
        $this->seedRbac();

        $response = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Ada', 'last_name' => 'Okafor',
            'email' => 'ada.learner@test.local', 'phone' => '+2348012345688',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
            'device_name' => 'Laptop', 'account_type' => 'learner',
        ])->assertCreated()->assertJsonPath('data.abilities', ['student']);

        $this->assertDatabaseHas('learner_profiles', [
            'user_id' => $response->json('data.user.id'),
            'display_name' => 'Ada Okafor',
        ]);

        $token = $response->json('data.token');
        $this->withToken($token)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.learner_profiles.0.display_name', 'Ada Okafor')
            ->assertJsonPath('data.learner_profiles.0.coin_balance', 0);
    }

    public function test_educator_school_registration_creates_a_pending_school_workspace(): void
    {
        $this->seedRbac();

        $response = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Tola', 'last_name' => 'Adebayo',
            'email' => 'tola.school@test.local', 'phone' => '+2348012345690',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
            'device_name' => 'Laptop', 'account_type' => 'educator_school',
            'organization_name' => 'Tola Learning Centre',
        ])->assertCreated()->assertJsonPath('data.abilities', ['school_admin']);

        $userId = $response->json('data.user.id');
        $this->assertDatabaseHas('organizations', [
            'name' => 'Tola Learning Centre',
            'type' => 'school',
            'status' => 'pending',
            'contact_email' => 'tola.school@test.local',
        ]);
        $this->assertDatabaseHas('organization_user', [
            'user_id' => $userId,
            'role' => 'school_admin',
            'status' => 'active',
        ]);
        $this->assertDatabaseMissing('families', ['owner_user_id' => $userId]);
    }

    public function test_institution_registration_creates_a_pending_institution_workspace(): void
    {
        $this->seedRbac();

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Amina', 'last_name' => 'Bello',
            'email' => 'amina.institution@test.local', 'phone' => '+2348012345691',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
            'device_name' => 'Laptop', 'account_type' => 'institution',
            'organization_name' => 'Arewa Cultural Foundation',
        ])->assertCreated()->assertJsonPath('data.abilities', ['school_admin']);

        $this->assertDatabaseHas('organizations', [
            'name' => 'Arewa Cultural Foundation',
            'type' => 'institution',
            'status' => 'pending',
        ]);
    }

    public function test_organization_name_is_required_for_non_family_registration(): void
    {
        $this->seedRbac();

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Tola', 'last_name' => 'Adebayo',
            'email' => 'missing.org@test.local', 'phone' => '+2348012345692',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
            'device_name' => 'Laptop', 'account_type' => 'educator_school',
        ])->assertStatus(422)->assertJsonValidationErrors('organization_name');
    }

    public function test_register_requires_last_name(): void
    {
        $this->seedRbac();

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'X', 'email' => 'x@test.local', 'phone' => '+2348012345679', 'password' => 'Password123!',
            'password_confirmation' => 'Password123!', 'device_name' => 'd',
        ])->assertStatus(422)->assertJsonValidationErrors('last_name');
    }

    public function test_register_requires_phone(): void
    {
        $this->seedRbac();

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'X', 'last_name' => 'Y', 'email' => 'x2@test.local', 'password' => 'Password123!',
            'password_confirmation' => 'Password123!', 'device_name' => 'd',
        ])->assertStatus(422)->assertJsonValidationErrors('phone');
    }

    public function test_login_returns_token(): void
    {
        $this->seedRbac();
        $user = User::factory()->create(['email' => 'a@test.local', 'password' => 'secret123']);
        $user->assignRole('parent');

        $this->postJson('/api/v1/auth/login', [
            'login' => 'a@test.local', 'password' => 'secret123', 'device_name' => 'd',
        ])->assertOk()->assertJsonStructure(['data' => ['token', 'expires_at', 'user']]);
    }

    public function test_login_rejects_bad_password(): void
    {
        User::factory()->create(['email' => 'a@test.local', 'password' => 'secret123']);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'a@test.local', 'password' => 'wrong', 'device_name' => 'd',
        ])->assertStatus(401);
    }

    public function test_me_requires_auth(): void
    {
        $this->getJson('/api/v1/me')->assertStatus(401);
    }
}

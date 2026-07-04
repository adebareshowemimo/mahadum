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
            'email' => 'funmi@test.local', 'password' => 'Password123!',
            'password_confirmation' => 'Password123!', 'device_name' => 'iPhone',
        ]);

        $res->assertCreated()
            ->assertJsonPath('data.user.first_name', 'Funmi')
            ->assertJsonPath('data.user.name', 'Funmi Adeyemi')
            ->assertJsonPath('data.abilities', ['parent']);

        $this->assertDatabaseHas('users', ['email' => 'funmi@test.local', 'first_name' => 'Funmi', 'last_name' => 'Adeyemi']);
        $this->assertDatabaseHas('families', ['name' => "Funmi's Family"]);
    }

    public function test_register_requires_last_name(): void
    {
        $this->seedRbac();

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'X', 'email' => 'x@test.local', 'password' => 'Password123!',
            'password_confirmation' => 'Password123!', 'device_name' => 'd',
        ])->assertStatus(422)->assertJsonValidationErrors('last_name');
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

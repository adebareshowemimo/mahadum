<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    /** Give each intentional quiz submission a distinct transport identity. */
    protected function answerJson(string $uri, array $data, array $headers = []): TestResponse
    {
        return $this->postJson($uri, ['request_id' => Str::uuid()->toString(), ...$data], $headers);
    }

    /** Seed the 7 roles + granular permissions. */
    protected function seedRbac(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** Create a user holding the given role. */
    protected function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    /** Authenticate as the user for Sanctum-guarded routes. */
    protected function actingAsUser(User $user): User
    {
        Sanctum::actingAs($user);

        return $user;
    }
}

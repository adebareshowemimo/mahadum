<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
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

<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\LearnerProfile;
use Database\Seeders\DemoSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_creates_fixtures_and_is_idempotent(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(LanguageSeeder::class);

        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class); // re-run must not duplicate

        $this->assertDatabaseHas('users', ['email' => 'parent@demo.mahadum360']);
        $this->assertDatabaseHas('organizations', ['slug' => 'demo-academy', 'status' => 'active']);
        $this->assertDatabaseHas('courses', ['title' => 'Yoruba for Beginners', 'is_published' => true]);

        $this->assertSame(2, LearnerProfile::count());
        $this->assertSame(1, Course::count());
    }
}

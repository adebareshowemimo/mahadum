<?php

namespace Tests\Feature;

use App\Models\AdvertPlacement;
use Database\Seeders\AdvertPlacementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdvertPlacementSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_idempotently_seeds_active_sample_adverts_and_their_creatives(): void
    {
        Storage::fake('public');

        $this->seed(AdvertPlacementSeeder::class);
        $this->seed(AdvertPlacementSeeder::class);

        $this->assertDatabaseCount('advert_placements', 3);
        $this->assertDatabaseCount('media_assets', 3);
        $this->assertDatabaseHas('advert_placements', ['position' => 'leaderboard', 'is_active' => true]);
        $this->assertDatabaseHas('advert_placements', ['position' => 'inline', 'is_active' => true]);
        $this->assertDatabaseHas('advert_placements', ['position' => 'profile_data_topup', 'is_active' => true]);

        Storage::disk('public')->assertExists('adverts/demo-family-learning-leaderboard.svg');
        Storage::disk('public')->assertExists('adverts/demo-culture-lesson-inline.svg');
        Storage::disk('public')->assertExists('adverts/profile-data-topup.svg');

        $this->assertSame(3, AdvertPlacement::query()->whereNotNull('activated_at')->count());
    }
}

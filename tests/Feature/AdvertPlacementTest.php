<?php

namespace Tests\Feature;

use App\Models\AdvertPlacement;
use App\Models\MediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvertPlacementTest extends TestCase
{
    use RefreshDatabase;

    private function creative(): MediaAsset
    {
        return MediaAsset::create(['type' => 'image', 'url' => 'media/advert.png', 'original_name' => 'advert.png']);
    }

    public function test_super_admin_can_create_and_list_advert_placements(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('super_admin'));
        $asset = $this->creative();

        $this->postJson('/api/v1/admin/adverts', [
            'name' => 'August leaderboard',
            'position' => 'leaderboard',
            'size' => '970x250',
            'media_asset_id' => $asset->id,
            'target_url' => 'https://example.com/promo',
            'is_active' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'August leaderboard')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('advert_placements', ['name' => 'August leaderboard', 'position' => 'leaderboard']);

        $this->getJson('/api/v1/admin/adverts')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_non_privileged_role_cannot_manage_adverts(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('student'));
        $asset = $this->creative();

        $this->postJson('/api/v1/admin/adverts', [
            'name' => 'Sneaky advert',
            'position' => 'inline',
            'media_asset_id' => $asset->id,
            'target_url' => 'https://example.com',
        ])->assertStatus(403);
    }

    public function test_public_active_endpoint_returns_only_active_advert_for_position(): void
    {
        $this->seedRbac();
        $asset = $this->creative();

        AdvertPlacement::create([
            'name' => 'Inactive leaderboard',
            'position' => 'leaderboard',
            'media_asset_id' => $asset->id,
            'target_url' => 'https://example.com/a',
            'is_active' => false,
        ]);

        $activeInline = AdvertPlacement::create([
            'name' => 'Active inline',
            'position' => 'inline',
            'media_asset_id' => $asset->id,
            'target_url' => 'https://example.com/b',
            'is_active' => true,
            'activated_at' => now(),
        ]);

        // Genuinely unauthenticated — no actingAsUser call.
        $this->getJson('/api/v1/adverts/active?position=leaderboard')
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->getJson('/api/v1/adverts/active?position=inline')
            ->assertOk()
            ->assertJsonPath('data.id', $activeInline->id)
            ->assertJsonPath('data.target_url', 'https://example.com/b');
    }

    public function test_active_endpoint_respects_scheduling_window(): void
    {
        $this->seedRbac();
        $asset = $this->creative();

        AdvertPlacement::create([
            'name' => 'Not started yet',
            'position' => 'leaderboard',
            'media_asset_id' => $asset->id,
            'target_url' => 'https://example.com/future',
            'is_active' => true,
            'activated_at' => now(),
            'starts_at' => now()->addDay(),
        ]);

        AdvertPlacement::create([
            'name' => 'Already ended',
            'position' => 'leaderboard',
            'media_asset_id' => $asset->id,
            'target_url' => 'https://example.com/past',
            'is_active' => true,
            'activated_at' => now(),
            'ends_at' => now()->subDay(),
        ]);

        $this->getJson('/api/v1/adverts/active?position=leaderboard')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_toggle_sets_activated_at_and_selection_prefers_most_recently_activated(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('super_admin'));
        $asset = $this->creative();

        $older = AdvertPlacement::create([
            'name' => 'Older',
            'position' => 'leaderboard',
            'media_asset_id' => $asset->id,
            'target_url' => 'https://example.com/older',
            'is_active' => true,
            'activated_at' => now()->subDay(),
        ]);

        $newer = AdvertPlacement::create([
            'name' => 'Newer',
            'position' => 'leaderboard',
            'media_asset_id' => $asset->id,
            'target_url' => 'https://example.com/newer',
            'is_active' => false,
        ]);

        $this->postJson("/api/v1/admin/adverts/{$newer->id}/toggle")
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->assertNotNull($newer->fresh()->activated_at);
        $this->assertTrue($newer->fresh()->activated_at->greaterThan($older->activated_at));

        $this->getJson('/api/v1/adverts/active?position=leaderboard')
            ->assertOk()
            ->assertJsonPath('data.id', $newer->id);
    }
}

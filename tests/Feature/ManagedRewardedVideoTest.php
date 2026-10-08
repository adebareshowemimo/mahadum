<?php

namespace Tests\Feature;

use App\Models\AdImpression;
use App\Models\Family;
use App\Models\Heart;
use App\Models\LearnerProfile;
use App\Models\MediaAsset;
use App\Services\Billing\EntitlementResolver;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ManagedRewardedVideoTest extends TestCase
{
    use RefreshDatabase;

    private LearnerProfile $learner;

    private MediaAsset $asset;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        Storage::fake('public');
        Storage::disk('public')->put('media/reward.mp4', 'fixture');
        $this->asset = MediaAsset::create(['type' => 'video', 'url' => 'media/reward.mp4', 'duration_seconds' => 10]);
        app(Settings::class)->set(['ads.managed_video_enabled' => true, 'ads.managed_video_asset_id' => $this->asset->id]);
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $family = Family::create(['owner_user_id' => $parent->id, 'name' => 'Family']);
        $this->learner = LearnerProfile::create(['family_id' => $family->id, 'display_name' => 'Adult', 'date_of_birth' => now()->subYears(20)->toDateString()]);
        Heart::create(['learner_profile_id' => $this->learner->id, 'current' => 0, 'questions_since_loss' => 3, 'refills_at' => now()->addHours(12), 'competitive_paused_until' => now()->addHours(12)]);
    }

    private function requestVideo(): int
    {
        return $this->postJson('/api/v1/ads/request', ['learner_id' => $this->learner->id, 'placement' => 'rewarded_heart'])
            ->assertOk()->assertJsonPath('data.eligible', true)->assertJsonPath('data.video.duration_seconds', 10)->json('data.impression_id');
    }

    private function watch(int $id): void
    {
        for ($position = 2; $position <= 10; $position += 2) {
            $this->travel(2)->seconds();
            $this->postJson("/api/v1/ads/{$id}/progress", ['position_seconds' => $position])
                ->assertOk()->assertJsonPath('data.position_seconds', $position);
        }
    }

    private function refill(int $id)
    {
        return $this->postJson('/api/v1/hearts/refill', ['learner_id' => $this->learner->id, 'method' => 'ad', 'ad_impression_id' => $id]);
    }

    public function test_real_progress_completes_then_refills_all_hearts_exactly_once(): void
    {
        $id = $this->requestVideo();
        $this->postJson("/api/v1/ads/{$id}/complete")->assertOk()->assertJsonPath('data.shown', false);
        $this->refill($id)->assertUnprocessable();
        $this->watch($id);
        $this->postJson("/api/v1/ads/{$id}/complete")->assertOk()->assertJsonPath('data.shown', true);
        $shownAt = AdImpression::findOrFail($id)->shown_at;
        // Lost completion response can be retried without a second shown event.
        $this->postJson("/api/v1/ads/{$id}/complete")->assertOk()->assertJsonPath('data.shown', true);
        $this->assertTrue($shownAt->equalTo(AdImpression::findOrFail($id)->shown_at));
        $this->refill($id)->assertOk()->assertJsonPath('data.current', 5)->assertJsonPath('data.practice_mode', false);
        $this->assertDatabaseHas('hearts', ['learner_profile_id' => $this->learner->id, 'current' => 5, 'questions_since_loss' => 0, 'refills_at' => null, 'competitive_paused_until' => null]);
        Heart::where('learner_profile_id', $this->learner->id)->update(['current' => 1]);
        $this->refill($id)->assertUnprocessable();
        $this->assertDatabaseHas('hearts', ['learner_profile_id' => $this->learner->id, 'current' => 1]);
    }

    public function test_elapsed_countdown_seeks_fast_playback_and_replays_cannot_complete(): void
    {
        $id = $this->requestVideo();
        $this->postJson("/api/v1/ads/{$id}/progress", ['position_seconds' => 4])->assertUnprocessable();
        $this->travel(20)->seconds();
        $this->postJson("/api/v1/ads/{$id}/complete")->assertOk()->assertJsonPath('data.shown', false);
        $this->postJson("/api/v1/ads/{$id}/progress", ['position_seconds' => 10])->assertUnprocessable();
        $this->postJson("/api/v1/ads/{$id}/progress", ['position_seconds' => 2])->assertOk();
        $this->postJson("/api/v1/ads/{$id}/progress", ['position_seconds' => 2])->assertOk();
        $this->postJson("/api/v1/ads/{$id}/progress", ['position_seconds' => 1])->assertUnprocessable();
        $this->postJson("/api/v1/ads/{$id}/complete")->assertOk()->assertJsonPath('data.shown', false);
        $this->refill($id)->assertUnprocessable();
        $this->assertDatabaseHas('hearts', ['learner_profile_id' => $this->learner->id, 'current' => 0]);
    }

    public static function revokedInventory(): array
    {
        return array_map(fn ($reason) => [$reason], ['disabled', 'missing', 'changed', 'expired', 'minor']);
    }

    #[DataProvider('revokedInventory')]
    public function test_redemption_rechecks_session_content_and_consent(string $reason): void
    {
        $id = $this->requestVideo();
        $this->watch($id);
        $this->postJson("/api/v1/ads/{$id}/complete")->assertOk()->assertJsonPath('data.shown', true);
        match ($reason) {
            'disabled' => app(Settings::class)->set(['ads.managed_video_enabled' => false]),
            'missing' => Storage::disk('public')->delete('media/reward.mp4'),
            'changed' => $this->asset->update(['duration_seconds' => 9]),
            'expired' => $this->travel(31)->minutes(),
            'minor' => $this->learner->update(['date_of_birth' => now()->subYears(8)->toDateString()]),
        };
        $this->refill($id)->assertUnprocessable();
        $this->assertNull(AdImpression::findOrFail($id)->consumed_at);
        $this->assertDatabaseHas('hearts', ['learner_profile_id' => $this->learner->id, 'current' => 0]);
    }

    public function test_foreign_households_cannot_report_complete_or_redeem_video(): void
    {
        $id = $this->requestVideo();
        $this->actingAsUser($this->userWithRole('parent'));
        $this->postJson("/api/v1/ads/{$id}/progress", ['position_seconds' => 2])->assertForbidden();
        $this->postJson("/api/v1/ads/{$id}/complete")->assertForbidden();
        $this->refill($id)->assertForbidden();
        $this->assertSame(0, AdImpression::findOrFail($id)->managed_video['position']);
    }

    public function test_missing_video_unknown_age_and_other_placements_are_unavailable(): void
    {
        Storage::disk('public')->delete('media/reward.mp4');
        $this->postJson('/api/v1/ads/request', ['learner_id' => $this->learner->id, 'placement' => 'rewarded_heart'])
            ->assertOk()->assertJsonPath('data.eligible', false)->assertJsonPath('data.reason', 'unavailable');
        $this->learner->update(['date_of_birth' => null]);
        $this->postJson('/api/v1/ads/request', ['learner_id' => $this->learner->id, 'placement' => 'rewarded_heart'])
            ->assertOk()->assertJsonPath('data.reason', 'coppa');
        $this->learner->update(['date_of_birth' => now()->subYears(20)->toDateString()]);
        $this->postJson('/api/v1/ads/request', ['learner_id' => $this->learner->id, 'placement' => 'post_lesson'])
            ->assertOk()->assertJsonPath('data.eligible', false);
    }

    public function test_admin_can_configure_an_uploaded_video_without_duration_metadata(): void
    {
        $this->asset->update(['duration_seconds' => null]);
        $this->actingAsUser($this->userWithRole('super_admin'));
        $this->patchJson('/api/v1/admin/settings', ['values' => [
            'ads.managed_video_enabled' => true, 'ads.managed_video_asset_id' => $this->asset->id,
            'ads.managed_video_duration_seconds' => 10,
        ]])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'system.settings_updated']);
        $owner = $this->learner->family->owner;
        $this->actingAsUser($owner);
        $this->requestVideo();
    }

    public function test_premium_and_staff_accounts_are_not_advertising_targets(): void
    {
        $this->mock(EntitlementResolver::class)->shouldReceive('forLearner')->andReturn(['ads' => false]);
        $this->postJson('/api/v1/ads/request', ['learner_id' => $this->learner->id, 'placement' => 'rewarded_heart'])
            ->assertOk()->assertJsonPath('data.eligible', false);
        $this->mock(EntitlementResolver::class)->shouldReceive('forLearner')->andReturn(['ads' => true]);
        $this->learner->family->owner->assignRole('teacher');
        $this->actingAsUser($this->learner->family->owner->fresh());
        $this->postJson('/api/v1/ads/request', ['learner_id' => $this->learner->id, 'placement' => 'rewarded_heart'])
            ->assertOk()->assertJsonPath('data.eligible', false);
    }

    public function test_migration_can_roll_back_and_upgrade_preserving_legacy_impressions(): void
    {
        $legacy = AdImpression::create(['learner_profile_id' => $this->learner->id, 'placement' => 'rewarded_heart', 'ad_ref' => 'legacy']);
        $migration = require database_path('migrations/2026_10_08_000000_add_managed_video_to_ad_impressions.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('ad_impressions', 'managed_video'));
        $this->assertDatabaseHas('ad_impressions', ['id' => $legacy->id, 'ad_ref' => 'legacy']);
        $migration->up();
        $this->assertTrue(Schema::hasColumn('ad_impressions', 'managed_video'));
        $this->assertNull($legacy->fresh()->managed_video);
        $this->refill($legacy->id)->assertUnprocessable();
    }
}

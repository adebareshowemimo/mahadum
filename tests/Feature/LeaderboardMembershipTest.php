<?php

namespace Tests\Feature;

use App\Models\League;
use App\Models\LeagueMembership;
use App\Models\XpLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class LeaderboardMembershipTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    private function currentLeague(): League
    {
        // SQLite fixtures must store the date text as production's DATE column does.
        $id = DB::table('leagues')->insertGetId([
            'name' => 'Current week', 'tier' => 1, 'week_start' => now()->startOfWeek()->toDateString(),
        ]);

        return League::findOrFail($id);
    }

    public function test_duplicate_memberships_show_one_row_per_learner_without_merging_names_or_xp(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $first = $this->parentWithChild($parent);
        $second = $first->family->learnerProfiles()->create(['display_name' => 'Lucy Okafor', 'current_level' => 0]);
        $first->update(['display_name' => 'Lucy Okafor']);
        $weekStart = now()->startOfWeek();
        $league = $this->currentLeague();
        foreach ([$first, $first, $second] as $learner) {
            LeagueMembership::create(['league_id' => $league->id, 'learner_profile_id' => $learner->id, 'weekly_xp' => 999]);
        }
        XpLedger::create(['learner_profile_id' => $first->id, 'amount' => 16, 'source' => 'test', 'created_at' => $weekStart->copy()->addHours(1)]);
        XpLedger::create(['learner_profile_id' => $first->id, 'amount' => 7, 'source' => 'test', 'created_at' => $weekStart->copy()->subDay()]);
        XpLedger::create(['learner_profile_id' => $second->id, 'amount' => 9, 'source' => 'test', 'created_at' => $weekStart->copy()->addHours(1)]);

        for ($read = 0; $read < 2; $read++) {
            $response = $this->getJson("/api/v1/leaderboard?learner_id={$first->id}")->assertOk()->assertJsonCount(2, 'data');
            $response->assertJsonPath('data.0.learner_id', $first->id)->assertJsonPath('data.0.rank', 1)
                ->assertJsonPath('data.0.weekly_xp', 16)->assertJsonPath('data.0.learning_level.lifetime_xp', 23)
                ->assertJsonPath('data.1.learner_id', $second->id)->assertJsonPath('data.1.rank', 2)
                ->assertJsonPath('data.1.weekly_xp', 9)->assertJsonPath('data.1.learning_level.lifetime_xp', 9);
            $this->assertSame(['Lucy Okafor', 'Lucy Okafor'], array_column($response->json('data'), 'display_name'));
        }

        $this->assertDatabaseCount('league_memberships', 3);
        $this->assertDatabaseCount('learner_profiles', 2);
        $this->assertDatabaseCount('xp_ledger', 3);
    }

    public function test_current_position_matches_board_rank_when_a_higher_learner_has_duplicate_memberships(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $mine = $this->parentWithChild($parent);
        $higher = $mine->family->learnerProfiles()->create(['display_name' => 'Higher', 'current_level' => 0]);
        $league = $this->currentLeague();
        foreach ([$higher, $higher, $mine] as $learner) {
            LeagueMembership::create(['league_id' => $league->id, 'learner_profile_id' => $learner->id, 'weekly_xp' => 0]);
        }
        XpLedger::create(['learner_profile_id' => $higher->id, 'amount' => 10, 'source' => 'test']);
        XpLedger::create(['learner_profile_id' => $mine->id, 'amount' => 4, 'source' => 'test']);

        $this->getJson("/api/v1/leagues/current?learner_id={$mine->id}")->assertOk()
            ->assertJsonPath('data.rank', 2)->assertJsonPath('data.weekly_xp', 4)->assertJsonPath('data.learning_level.lifetime_xp', 4);
        $this->getJson("/api/v1/leaderboard?learner_id={$mine->id}")->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonPath('data.1.learner_id', $mine->id)->assertJsonPath('data.1.rank', 2);
        $this->assertDatabaseCount('league_memberships', 3);
        $this->assertDatabaseCount('xp_ledger', 2);
    }
}

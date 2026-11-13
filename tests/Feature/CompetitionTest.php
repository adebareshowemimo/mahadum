<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Family;
use App\Models\LearnerProfile;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompetitionTest extends TestCase
{
    use RefreshDatabase;

    private function openCompetition(array $attrs = []): Competition
    {
        return Competition::create(array_merge([
            'title' => 'Language & Culture 2026',
            'slug' => 'language-culture-2026',
            'season' => 2026,
            'status' => 'open',
            'min_activity_days' => 90,
        ], $attrs));
    }

    public function test_content_owner_creates_and_opens_a_competition(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('content_owner'));

        $created = $this->postJson('/api/v1/admin/competitions', [
            'title' => 'Language & Culture 2026',
            'season' => 2026,
            'description' => 'Celebrate our languages.',
            'rules' => 'One entry per school.',
            'prizes' => 'Books for winners.',
            'eligibility' => 'Schools and diaspora families.',
            'entries_open_at' => now()->toIso8601String(),
            'submissions_close_at' => now()->addWeek()->toIso8601String(),
            'voting_closes_at' => now()->addWeeks(2)->toIso8601String(),
            'judging_starts_at' => now()->addWeeks(3)->toIso8601String(),
            'judging_closes_at' => now()->addWeeks(4)->toIso8601String(),
        ])->assertCreated()->json('data');

        $this->postJson("/api/v1/admin/competitions/{$created['id']}/status", ['status' => 'open'])
            ->assertOk()->assertJsonPath('data.status', 'open');

        $this->patchJson("/api/v1/admin/competitions/{$created['id']}", ['prizes' => 'A new book prize.'])
            ->assertOk()->assertJsonPath('data.prizes', 'A new book prize.');
        $this->patchJson("/api/v1/admin/competitions/{$created['id']}", ['rules' => ''])
            ->assertStatus(422);

        // A draft competition is hidden from the public browse.
        $this->getJson('/api/v1/competitions')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_guests_can_read_open_competitions_by_slug_but_not_drafts_or_unapproved_entries(): void
    {
        $competition = $this->openCompetition([
            'description' => 'A celebration of language.',
            'rules' => 'One entry per school.',
            'prizes' => 'Books for winners.',
            'eligibility' => 'Schools and diaspora families.',
            'entries_open_at' => now()->subDay(),
            'submissions_close_at' => now()->addWeek(),
            'judging_starts_at' => now()->addWeeks(2),
        ]);
        $competition->entries()->create(['category' => 'school_play', 'title' => 'Awaiting review', 'status' => 'submitted']);
        $competition->entries()->create(['category' => 'school_play', 'title' => 'Approved play', 'status' => 'approved']);
        $this->openCompetition(['slug' => 'private-draft', 'status' => 'draft']);

        $this->getJson('/api/v1/public/competitions')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/public/competitions/'.$competition->slug)
            ->assertOk()->assertJsonPath('data.rules', 'One entry per school.')
            ->assertJsonPath('data.prizes', 'Books for winners.')
            ->assertJsonPath('data.can_enter', false)
            ->assertJsonCount(1, 'data.entries')
            ->assertJsonPath('data.entries.0.title', 'Approved play');
        $this->getJson('/api/v1/public/competitions/private-draft')->assertNotFound();
        $this->getJson('/api/v1/public/competitions/'.$competition->id)
            ->assertOk()->assertJsonPath('data.slug', $competition->slug);
    }

    public function test_organiser_cannot_publish_a_competition_without_rules_prizes_eligibility_and_dates(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('content_owner'));
        $created = $this->postJson('/api/v1/admin/competitions', [
            'title' => 'Incomplete event', 'season' => 2026,
        ])->assertCreated()->json('data');
        $this->postJson("/api/v1/admin/competitions/{$created['id']}/status", ['status' => 'open'])
            ->assertStatus(422);
        $this->getJson('/api/v1/public/competitions')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_competition_entry_dates_are_enforced(): void
    {
        $competition = $this->openCompetition(['entries_open_at' => now()->addDay()]);
        $this->assertFalse($competition->acceptingEntries());
        $competition->update(['entries_open_at' => now()->subDay(), 'submissions_close_at' => now()->subMinute()]);
        $this->assertFalse($competition->fresh()->acceptingEntries());
        $competition->update(['voting_closes_at' => now()->subMinute()]);
        $this->assertFalse($competition->fresh()->acceptingVotes());
    }

    public function test_signed_in_parent_can_enter_from_public_competition_page(): void
    {
        $this->seedRbac();
        $competition = $this->openCompetition();
        $this->actingAsUser($this->userWithRole('parent'));
        $this->getJson('/api/v1/public/competitions/'.$competition->slug)
            ->assertOk()->assertJsonPath('data.can_enter', true);
    }

    public function test_school_admin_enters_a_play_and_users_vote_once_per_category(): void
    {
        $this->seedRbac();
        $competition = $this->openCompetition();

        $org = Organization::create(['name' => 'Greenfield', 'type' => 'school', 'slug' => 'greenfield', 'status' => 'active']);
        $admin = $this->userWithRole('school_admin');
        $org->members()->attach($admin->id, ['role' => 'school_admin', 'status' => 'active']);

        $this->actingAsUser($admin);
        $entry = $this->postJson("/api/v1/competitions/{$competition->id}/entries", [
            'category' => 'school_play',
            'title' => 'The Talking Drum',
            'organization_id' => $org->id,
        ])->assertCreated()->json('data');

        // Entries cannot receive votes until the organiser has approved them.
        $this->postJson("/api/v1/competitions/{$competition->id}/entries/{$entry['id']}/vote")
            ->assertStatus(422);
        $competition->entries()->whereKey($entry['id'])->update(['status' => 'approved']);

        // Any signed-in user can vote — but only once in the school_play category.
        $voter = $this->userWithRole('student');
        $this->actingAsUser($voter);
        $this->postJson("/api/v1/competitions/{$competition->id}/entries/{$entry['id']}/vote")
            ->assertCreated()->assertJsonPath('data.votes_count', 1);
        $this->postJson("/api/v1/competitions/{$competition->id}/entries/{$entry['id']}/vote")
            ->assertStatus(409);
    }

    public function test_parent_enters_child_only_when_activity_threshold_is_met(): void
    {
        $this->seedRbac();
        $competition = $this->openCompetition();

        $parent = $this->userWithRole('parent');
        $family = Family::create(['owner_user_id' => $parent->id, 'name' => 'Ade Family']);

        // A learner with no activity does not qualify — even if the account is old.
        $fresh = LearnerProfile::create(['family_id' => $family->id, 'display_name' => 'Tunde']);
        $fresh->forceFill(['created_at' => now()->subDays(200)])->save();
        $this->actingAsUser($parent);
        $this->postJson("/api/v1/competitions/{$competition->id}/entries", [
            'category' => 'diaspora_folklore',
            'title' => 'The Tortoise and the Birds',
            'learner_profile_id' => $fresh->id,
            'guardian_consent' => true,
        ])->assertStatus(422);

        // A learner whose first XP was earned beyond the threshold qualifies.
        $eligible = LearnerProfile::create(['family_id' => $family->id, 'display_name' => 'Bisi']);
        $eligible->xpEntries()->create(['amount' => 20, 'source' => 'lesson', 'created_at' => now()->subDays(120)]);
        $this->postJson("/api/v1/competitions/{$competition->id}/entries", [
            'category' => 'diaspora_folklore',
            'title' => 'The Tortoise and the Birds',
            'learner_profile_id' => $eligible->id,
            'guardian_consent' => true,
        ])->assertCreated();
        $this->assertDatabaseHas('competition_entries', [
            'learner_profile_id' => $eligible->id,
            'guardian_approved_by' => $parent->id,
        ]);
    }

    public function test_parent_cannot_enter_a_child_they_do_not_own(): void
    {
        $this->seedRbac();
        $competition = $this->openCompetition();

        $owner = $this->userWithRole('parent');
        $otherFamily = Family::create(['owner_user_id' => $owner->id, 'name' => 'Owner Family']);
        $child = LearnerProfile::create(['family_id' => $otherFamily->id, 'display_name' => 'Not Yours']);
        $child->forceFill(['created_at' => now()->subDays(200)])->save();

        $intruder = $this->userWithRole('parent');
        $this->actingAsUser($intruder);
        $this->postJson("/api/v1/competitions/{$competition->id}/entries", [
            'category' => 'diaspora_folklore',
            'title' => 'Stolen Story',
            'learner_profile_id' => $child->id,
            'guardian_consent' => true,
        ])->assertStatus(403);
    }

    public function test_older_child_entry_needs_parent_approval_before_publication_and_voting(): void
    {
        $this->seedRbac();
        $competition = $this->openCompetition();
        $parent = $this->userWithRole('parent');
        $family = Family::create(['owner_user_id' => $parent->id, 'name' => 'Ade Family']);
        $child = LearnerProfile::create(['family_id' => $family->id, 'display_name' => 'Bisi']);
        $entry = $competition->entries()->create([
            'category' => 'diaspora_folklore', 'learner_profile_id' => $child->id,
            'title' => 'A family story', 'status' => 'submitted',
        ]);

        $this->actingAsUser($this->userWithRole('content_owner'));
        $this->postJson("/api/v1/admin/competitions/{$competition->id}/entries/{$entry->id}/moderate", ['status' => 'approved'])
            ->assertStatus(422);
        $this->actingAsUser($this->userWithRole('parent'));
        $this->postJson("/api/v1/competitions/{$competition->id}/entries/{$entry->id}/guardian-approve", ['guardian_consent' => true])
            ->assertForbidden();
        $this->actingAsUser($parent);
        $this->postJson("/api/v1/competitions/{$competition->id}/entries/{$entry->id}/guardian-approve", [])
            ->assertUnprocessable();
        $this->postJson("/api/v1/competitions/{$competition->id}/entries/{$entry->id}/guardian-approve", ['guardian_consent' => true])
            ->assertOk();
        $this->assertDatabaseHas('competition_entries', ['id' => $entry->id, 'guardian_approved_by' => $parent->id]);

        $this->actingAsUser($this->userWithRole('content_owner'));
        $this->postJson("/api/v1/admin/competitions/{$competition->id}/entries/{$entry->id}/moderate", ['status' => 'approved'])
            ->assertOk();
        $this->getJson('/api/v1/public/competitions/'.$competition->slug)
            ->assertOk()->assertJsonPath('data.entries.0.title', 'A family story');
    }

    public function test_organiser_lists_and_moderates_entries(): void
    {
        $this->seedRbac();
        $competition = $this->openCompetition();
        $org = Organization::create(['name' => 'Greenfield', 'type' => 'school', 'slug' => 'greenfield', 'status' => 'active']);
        $entry = $competition->entries()->create([
            'category' => 'school_play', 'organization_id' => $org->id, 'title' => 'A Play', 'status' => 'submitted',
        ]);

        $this->actingAsUser($this->userWithRole('content_owner'));

        $this->getJson("/api/v1/admin/competitions/{$competition->id}")
            ->assertOk()
            ->assertJsonPath('data.entries.0.title', 'A Play')
            ->assertJsonPath('data.entries.0.entrant', 'Greenfield');

        $this->postJson("/api/v1/admin/competitions/{$competition->id}/entries/{$entry->id}/moderate", ['status' => 'disqualified'])
            ->assertOk()->assertJsonPath('data.status', 'disqualified');

        $this->assertSame('disqualified', $entry->refresh()->status);
    }

    public function test_organiser_judges_and_closes_the_competition(): void
    {
        $this->seedRbac();
        $competition = $this->openCompetition();
        $org = Organization::create(['name' => 'Greenfield', 'type' => 'school', 'slug' => 'greenfield', 'status' => 'active']);
        $entry = $competition->entries()->create([
            'category' => 'school_play', 'organization_id' => $org->id, 'title' => 'A Play', 'status' => 'approved',
        ]);

        $this->actingAsUser($this->userWithRole('content_owner'));
        $this->postJson("/api/v1/admin/competitions/{$competition->id}/judge", [
            'awards' => [['entry_id' => $entry->id, 'rank' => 1]],
        ])->assertOk()->assertJsonPath('data.status', 'closed');

        $this->assertSame(1, $entry->refresh()->award_rank);
    }

    public function test_entries_are_rejected_once_submissions_close(): void
    {
        $this->seedRbac();
        $competition = $this->openCompetition(['status' => 'voting']); // past entry window
        $org = Organization::create(['name' => 'Greenfield', 'type' => 'school', 'slug' => 'greenfield', 'status' => 'active']);
        $admin = $this->userWithRole('school_admin');
        $org->members()->attach($admin->id, ['role' => 'school_admin', 'status' => 'active']);

        $this->actingAsUser($admin);
        $this->postJson("/api/v1/competitions/{$competition->id}/entries", [
            'category' => 'school_play',
            'title' => 'Too Late',
            'organization_id' => $org->id,
        ])->assertStatus(422);
    }
}

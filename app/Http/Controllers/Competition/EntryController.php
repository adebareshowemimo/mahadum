<?php

namespace App\Http\Controllers\Competition;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\LearnerProfile;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Submissions into a competition. Two paths, both gated by `competitions.enter`:
 *  • school_play       — a school staffer enters on the school's behalf.
 *  • diaspora_folklore — a parent enters their child (who must meet the minimum
 *                        activity requirement — BRD: 3 months to qualify).
 */
class EntryController extends Controller
{
    public function approveAsGuardian(Request $request, Competition $competition, CompetitionEntry $entry, AuditLogger $audit): JsonResponse
    {
        abort_unless($entry->competition_id === $competition->id && $entry->category === 'diaspora_folklore', 404);
        $request->validate(['guardian_consent' => ['accepted']]);
        $profile = $entry->learnerProfile;
        $user = $request->user();
        abort_unless($profile && $profile->family
            && (int) $profile->family->owner_user_id === (int) $user->id
            && (int) $profile->user_id !== (int) $user->id, 403);
        abort_if($entry->guardian_approved_at !== null, 409, 'This entry already has parent approval.');

        $approvedAt = now();
        $entry->update(['guardian_approved_by' => $user->id, 'guardian_approved_at' => $approvedAt]);
        $audit->record('competition.entry_guardian_approved', $entry, [], ['guardian_approved_by' => $user->id]);

        return response()->json(['data' => ['id' => $entry->id, 'guardian_approved_at' => $approvedAt->toIso8601String()]]);
    }

    public function store(Request $request, Competition $competition): JsonResponse
    {
        abort_unless($competition->acceptingEntries(), 422, 'This competition is not accepting entries.');

        $validated = $request->validate([
            'category' => ['required', Rule::in(Competition::CATEGORIES)],
            'title' => ['required', 'string', 'max:160'],
            'synopsis' => ['nullable', 'string', 'max:2000'],
            'language_id' => ['nullable', 'integer', 'exists:languages,id'],
            'media_asset_id' => ['nullable', 'integer', 'exists:media_assets,id'],
            'organization_id' => ['required_if:category,school_play', 'integer', 'exists:organizations,id'],
            'learner_profile_id' => ['required_if:category,diaspora_folklore', 'integer', 'exists:learner_profiles,id'],
            'guardian_consent' => ['accepted_if:category,diaspora_folklore'],
        ]);

        $user = $request->user();

        if ($validated['category'] === 'school_play') {
            $this->authorizeSchoolEntry($user->id, (int) $validated['organization_id']);
            $validated['learner_profile_id'] = null;
        } else {
            $profile = LearnerProfile::findOrFail($validated['learner_profile_id']);
            $this->authorizeFolkloreEntry($user, $profile, $competition);
            $validated['organization_id'] = null;
        }

        unset($validated['guardian_consent']);

        $entry = $competition->entries()->create([
            ...$validated,
            'submitted_by' => $user->id,
            'status' => 'submitted',
            'submitted_at' => now(),
            'guardian_approved_by' => $validated['category'] === 'diaspora_folklore' ? $user->id : null,
            'guardian_approved_at' => $validated['category'] === 'diaspora_folklore' ? now() : null,
        ]);

        return response()->json(['data' => ['id' => $entry->id, 'status' => $entry->status]], 201);
    }

    /** The caller's own entries (as submitter). */
    public function mine(Request $request): JsonResponse
    {
        $ownedLearners = LearnerProfile::whereHas('family', fn ($query) => $query->where('owner_user_id', $request->user()->id))->select('id');
        $entries = CompetitionEntry::where(fn ($query) => $query
            ->where('submitted_by', $request->user()->id)
            ->orWhereIn('learner_profile_id', $ownedLearners))
            ->with('competition:id,title,season')
            ->latest()
            ->get()
            ->map(fn (CompetitionEntry $e) => [
                'id' => $e->id,
                'competition_id' => $e->competition_id,
                'competition' => $e->competition->title,
                'season' => $e->competition->season,
                'category' => $e->category,
                'title' => $e->title,
                'status' => $e->status,
                'guardian_approved_at' => $e->guardian_approved_at?->toIso8601String(),
                'votes_count' => $e->votes_count,
                'award_rank' => $e->award_rank,
            ]);

        return response()->json(['data' => $entries]);
    }

    /** A school entry requires an active membership in that organization. */
    private function authorizeSchoolEntry(int $userId, int $organizationId): void
    {
        $member = OrganizationUser::where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();

        abort_unless($member, 403, 'You can only enter on behalf of a school you belong to.');
    }

    /** A folklore entry requires owning the child and the child qualifying by activity. */
    private function authorizeFolkloreEntry(User $user, LearnerProfile $profile, Competition $competition): void
    {
        $ownsChild = $profile->family && (int) $profile->family->owner_user_id === (int) $user->id
            && (int) $profile->user_id !== (int) $user->id;

        abort_unless($ownsChild, 403, 'You can only enter your own child.');

        // Minimum activity to qualify (BRD: 3 months). Measured from the learner's
        // FIRST earned XP — real platform activity, not account age — so a dormant
        // account that merely existed long enough doesn't slip through.
        $firstActivity = $profile->xpEntries()->min('created_at');
        $qualifies = $firstActivity !== null
            && Carbon::parse($firstActivity)->lte(now()->subDays($competition->min_activity_days));

        abort_unless($qualifies, 422, "This learner needs at least {$competition->min_activity_days} days of activity to qualify.");
    }
}

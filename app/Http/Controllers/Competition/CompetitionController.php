<?php

namespace App\Http\Controllers\Competition;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CompetitionVote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public (any signed-in user) browse of the Language & Culture competition and
 * its entries. Draft competitions are hidden until an organiser opens them.
 */
class CompetitionController extends Controller
{
    public function index(): JsonResponse
    {
        $competitions = Competition::where('status', '!=', 'draft')
            ->withCount('entries')
            ->orderByDesc('season')
            ->get()
            ->map(fn (Competition $c) => $this->summary($c));

        return response()->json(['data' => $competitions]);
    }

    public function show(Request $request, Competition $competition): JsonResponse
    {
        abort_if($competition->status === 'draft', 404);

        $entries = $competition->entries()
            ->whereIn('status', ['submitted', 'approved'])
            ->with(['organization:id,name', 'learnerProfile:id,display_name', 'language:id,name'])
            ->orderByDesc('votes_count')
            ->orderBy('id')
            ->get();

        // Which categories the caller has already voted in (one vote per category).
        $votedCategories = CompetitionVote::where('competition_id', $competition->id)
            ->where('user_id', $request->user()->id)
            ->pluck('category')
            ->all();

        return response()->json(['data' => [
            ...$this->summary($competition),
            'description' => $competition->description,
            'voted_categories' => $votedCategories,
            'can_enter' => $request->user()->can('competitions.enter') && $competition->acceptingEntries(),
            'entries' => $entries->map(fn (CompetitionEntry $e) => $this->entry($e))->values(),
        ]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Competition $c): array
    {
        return [
            'id' => $c->id,
            'title' => $c->title,
            'slug' => $c->slug,
            'season' => $c->season,
            'status' => $c->status,
            'entries_count' => $c->entries_count ?? $c->entries()->count(),
            'submissions_close_at' => $c->submissions_close_at?->toIso8601String(),
            'voting_closes_at' => $c->voting_closes_at?->toIso8601String(),
            'min_activity_days' => $c->min_activity_days,
            'accepting_entries' => $c->acceptingEntries(),
            'accepting_votes' => $c->acceptingVotes(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(CompetitionEntry $e): array
    {
        return [
            'id' => $e->id,
            'category' => $e->category,
            'title' => $e->title,
            'synopsis' => $e->synopsis,
            'status' => $e->status,
            'votes_count' => $e->votes_count,
            'award_rank' => $e->award_rank,
            'entrant' => $e->organization !== null ? $e->organization->name : $e->learnerProfile?->display_name,
            'language' => $e->language?->name,
        ];
    }
}

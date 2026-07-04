<?php

namespace App\Http\Controllers\Competition;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CompetitionVote;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public voting — open to any signed-in user, one vote per category per
 * competition (enforced by a unique index; a second attempt returns 409).
 */
class VoteController extends Controller
{
    public function store(Request $request, Competition $competition, CompetitionEntry $entry): JsonResponse
    {
        abort_unless($competition->acceptingVotes(), 422, 'Voting is not open for this competition.');
        abort_unless($entry->competition_id === $competition->id, 404);
        abort_if(in_array($entry->status, ['rejected', 'disqualified'], true), 422, 'This entry is not eligible for votes.');

        try {
            CompetitionVote::create([
                'competition_id' => $competition->id,
                'competition_entry_id' => $entry->id,
                'category' => $entry->category,
                'user_id' => $request->user()->id,
            ]);
        } catch (QueryException) {
            // Unique (competition, category, user) violated → already voted here.
            abort(409, "You have already voted in the {$entry->category} category.");
        }

        $entry->increment('votes_count');

        return response()->json(['data' => ['votes_count' => $entry->votes_count]], 201);
    }
}

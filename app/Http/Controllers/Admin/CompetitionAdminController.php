<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Organiser console for the Language & Culture competition (content_owner /
 * super_admin): create competitions, move them through their lifecycle,
 * moderate entries, and record the judged awards. All actions are audited.
 */
class CompetitionAdminController extends Controller
{
    private const STATUSES = ['draft', 'open', 'voting', 'closed'];

    public function __construct(private AuditLogger $audit) {}

    public function index(): JsonResponse
    {
        $competitions = Competition::withCount('entries')
            ->orderByDesc('season')
            ->get()
            ->map(fn (Competition $c) => $this->present($c));

        return response()->json(['data' => $competitions]);
    }

    /** Organiser drill-down: the competition plus every entry (all statuses). */
    public function show(Competition $competition): JsonResponse
    {
        $entries = $competition->entries()
            ->with(['organization:id,name', 'learnerProfile:id,display_name', 'language:id,name'])
            ->orderBy('category')
            ->orderByDesc('votes_count')
            ->get()
            ->map(fn (CompetitionEntry $e) => [
                'id' => $e->id,
                'category' => $e->category,
                'title' => $e->title,
                'synopsis' => $e->synopsis,
                'status' => $e->status,
                'votes_count' => $e->votes_count,
                'award_rank' => $e->award_rank,
                'entrant' => $e->organization !== null ? $e->organization->name : $e->learnerProfile?->display_name,
                'language' => $e->language?->name,
                'submitted_at' => $e->submitted_at?->toIso8601String(),
            ]);

        return response()->json(['data' => [
            ...$this->present($competition),
            'entries' => $entries,
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'season' => ['required', 'integer', 'min:2020', 'max:2100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'rules' => ['nullable', 'string', 'max:10000'],
            'prizes' => ['nullable', 'string', 'max:5000'],
            'eligibility' => ['nullable', 'string', 'max:5000'],
            'entries_open_at' => ['nullable', 'date'],
            'submissions_close_at' => ['nullable', 'date'],
            'voting_closes_at' => ['nullable', 'date'],
            'judging_starts_at' => ['nullable', 'date'],
            'judging_closes_at' => ['nullable', 'date'],
            'min_activity_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ]);

        $competition = Competition::create([
            ...$validated,
            'slug' => $this->uniqueSlug($validated['title'], (int) $validated['season']),
            'min_activity_days' => $validated['min_activity_days'] ?? 90,
            'status' => 'draft',
        ]);

        $this->audit->record('competition.created', $competition, [], $this->present($competition));

        return response()->json(['data' => $this->present($competition)], 201);
    }

    public function update(Request $request, Competition $competition): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'rules' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'prizes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'eligibility' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'entries_open_at' => ['sometimes', 'nullable', 'date'],
            'submissions_close_at' => ['sometimes', 'nullable', 'date'],
            'voting_closes_at' => ['sometimes', 'nullable', 'date'],
            'judging_starts_at' => ['sometimes', 'nullable', 'date'],
            'judging_closes_at' => ['sometimes', 'nullable', 'date'],
            'min_activity_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
        ]);

        if ($competition->status !== 'draft') {
            $candidate = clone $competition;
            $candidate->fill($validated);
            $this->ensurePublishReady($candidate);
        }

        $before = $this->present($competition);
        $competition->fill($validated)->save();
        $this->audit->record('competition.updated', $competition, $before, $this->present($competition->fresh()));

        return response()->json(['data' => $this->present($competition)]);
    }

    public function setStatus(Request $request, Competition $competition): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
        ]);

        if ($competition->status === 'draft' && $validated['status'] === 'open') {
            $this->ensurePublishReady($competition);
        }

        $before = $this->present($competition);
        $competition->update(['status' => $validated['status']]);
        $this->audit->record('competition.status_changed', $competition, $before, $this->present($competition));

        return response()->json(['data' => $this->present($competition)]);
    }

    /** Moderate a single entry (approve / reject / disqualify). */
    public function moderateEntry(Request $request, Competition $competition, CompetitionEntry $entry): JsonResponse
    {
        abort_unless($entry->competition_id === $competition->id, 404);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected', 'disqualified'])],
        ]);

        abort_if($validated['status'] === 'approved' && $entry->category === 'diaspora_folklore'
            && $entry->guardian_approved_at === null, 422, 'Parent approval is required before this entry can be approved.');

        $before = ['status' => $entry->status];
        $entry->update(['status' => $validated['status']]);
        $this->audit->record('competition.entry_moderated', $entry, $before, ['status' => $entry->status]);

        return response()->json(['data' => ['id' => $entry->id, 'status' => $entry->status]]);
    }

    /**
     * Record the judged results: a list of {entry_id, rank}. Ranks are set on the
     * entries and the competition is closed.
     */
    public function judge(Request $request, Competition $competition): JsonResponse
    {
        $validated = $request->validate([
            'awards' => ['required', 'array', 'min:1'],
            'awards.*.entry_id' => ['required', 'integer'],
            'awards.*.rank' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $before = $this->present($competition);

        foreach ($validated['awards'] as $award) {
            $competition->entries()->whereKey($award['entry_id'])->update(['award_rank' => $award['rank']]);
        }
        $competition->update(['status' => 'closed']);

        $this->audit->record('competition.judged', $competition, $before, [
            ...$this->present($competition),
            'awards' => $validated['awards'],
        ]);

        return response()->json(['data' => $this->present($competition->fresh())]);
    }

    private function uniqueSlug(string $title, int $season): string
    {
        $base = Str::slug($title.'-'.$season);
        $slug = $base;
        $i = 1;
        while (Competition::where('slug', $slug)->exists()) {
            $slug = $base.'-'.++$i;
        }

        return $slug;
    }

    private function ensurePublishReady(Competition $competition): void
    {
        foreach (['description', 'rules', 'prizes', 'eligibility', 'entries_open_at', 'submissions_close_at', 'voting_closes_at', 'judging_starts_at', 'judging_closes_at'] as $field) {
            abort_if(blank($competition->{$field}), 422, "Complete {$field} before publishing this competition.");
        }
        abort_unless($competition->entries_open_at->lt($competition->submissions_close_at)
            && $competition->submissions_close_at->lte($competition->voting_closes_at)
            && $competition->voting_closes_at->lte($competition->judging_starts_at)
            && $competition->judging_starts_at->lt($competition->judging_closes_at),
            422, 'Competition dates must follow entries, voting, then judging in order.');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Competition $c): array
    {
        return [
            'id' => $c->id,
            'title' => $c->title,
            'slug' => $c->slug,
            'season' => $c->season,
            'description' => $c->description,
            'rules' => $c->rules,
            'prizes' => $c->prizes,
            'eligibility' => $c->eligibility,
            'status' => $c->status,
            'statuses' => self::STATUSES,
            'submissions_close_at' => $c->submissions_close_at?->toIso8601String(),
            'entries_open_at' => $c->entries_open_at?->toIso8601String(),
            'judging_starts_at' => $c->judging_starts_at?->toIso8601String(),
            'judging_closes_at' => $c->judging_closes_at?->toIso8601String(),
            'voting_closes_at' => $c->voting_closes_at?->toIso8601String(),
            'min_activity_days' => $c->min_activity_days,
            'entries_count' => $c->entries_count ?? $c->entries()->count(),
        ];
    }
}

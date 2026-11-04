<?php

namespace App\Http\Controllers\Family;

use App\Http\Controllers\Concerns\ResolvesFamily;
use App\Http\Controllers\Controller;
use App\Http\Requests\Family\AddChildRequest;
use App\Http\Requests\Family\SetChildPinRequest;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\FamilyMember;
use App\Models\LeagueMembership;
use App\Models\LearnerProfile;
use App\Models\ParentalConsent;
use App\Models\StudyRewardDay;
use App\Services\AuditLogger;
use App\Services\Family\WalletService;
use App\Services\Gamification\LeagueService;
use App\Services\Learning\LearningAnalytics;
use App\Services\Learning\LearningGoals;
use App\Services\Learning\PathBuilder;
use App\Services\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class FamilyController extends Controller
{
    use ResolvesFamily;

    public function __construct(private WalletService $wallets, private AuditLogger $audit, private Settings $settings) {}

    public function show(Request $request, LearningAnalytics $analytics, LeagueService $leagues): JsonResponse
    {
        $family = $this->family($request->user())->load(['learnerProfiles.targetLanguage', 'learnerProfiles.profilePhoto', 'learnerProfiles.streak', 'owner']);
        $wallet = $this->wallets->walletFor($family);
        $learnerIds = $family->learnerProfiles->pluck('id');
        $learning = $analytics->byLearner($learnerIds);
        $memberships = LeagueMembership::with('league')->whereIn('learner_profile_id', $learnerIds)
            ->whereHas('league', fn ($query) => $query->whereDate('week_start', $leagues->currentWeekStart()))->get();
        $ranks = $memberships->pluck('league')->unique('id')
            ->flatMap(fn ($league) => $leagues->refreshAndRank($league))->keyBy('learner_profile_id');

        return response()->json(['data' => [
            'id' => $family->id,
            'name' => $family->name,
            'child_limit' => $family->child_limit,
            'pending_study_rewards' => StudyRewardDay::where('family_id', $family->id)->where('status', 'pending')->count(),
            'wallet' => [
                'coin_balance' => $wallet->coin_balance,
                'currency_minor' => $wallet->currency_balance_minor,
                'currency' => $wallet->currency,
            ],
            'parent' => [
                'id' => $family->owner->id,
                'name' => $family->owner->name,
                'email' => $family->owner->email,
            ],
            'learners' => $family->learnerProfiles->map(fn ($l) => [
                'id' => $l->id,
                'display_name' => $l->display_name,
                'avatar_id' => $l->avatar_id,
                'avatar_url' => $this->avatarUrl($l),
                'is_child' => $l->user_id === null,
                'pin_protected' => $l->parental_pin !== null,
                'coin_balance' => $this->wallets->walletFor($l)->coin_balance,
                'currency_minor' => $this->wallets->walletFor($l)->currency_balance_minor,
                'lessons_completed' => $learning[$l->id]['lessons_completed'] ?? 0,
                'streak_days' => $l->streak->current_count ?? 0,
                'speaking_score' => ($learning[$l->id]['speaking_scored'] ?? 0) > 0
                    ? round($learning[$l->id]['speaking_score_sum'] / $learning[$l->id]['speaking_scored'], 2) : null,
                'rank' => $ranks[$l->id]->rank ?? null,
            ])->values(),
        ]]);
    }

    public function addChild(AddChildRequest $request): JsonResponse
    {
        return DB::transaction(fn () => $this->createChild($request));
    }

    private function createChild(AddChildRequest $request): JsonResponse
    {
        if ($request->filled('learning_goal')) {
            abort_unless(app(LearningGoals::class)->find($request->input('learning_goal')) !== null, 422, 'Choose an available learning goal.');
        }
        $family = $this->family($request->user());
        $family->newQuery()->whereKey($family->id)->lockForUpdate()->firstOrFail();

        if ($family->learnerProfiles()->count() >= $family->child_limit) {
            return response()->json([
                'error' => ['code' => 'child_limit_reached', 'message' => "This family allows up to {$family->child_limit} profiles.", 'status' => 422],
            ], 422);
        }

        $learner = LearnerProfile::create([
            'family_id' => $family->id,
            'display_name' => $request->string('display_name'),
            'date_of_birth' => $request->input('date_of_birth'),
            'age_band' => $request->input('age_band'),
            'target_language_id' => $request->input('target_language_id'),
            'learning_goal' => $request->input('learning_goal'),
        ]);

        FamilyMember::create([
            'family_id' => $family->id,
            'learner_profile_id' => $learner->id,
            'relationship' => 'child',
            'is_account_owner' => false,
        ]);

        // COPPA / NDPA: record verifiable parental consent for this child.
        ParentalConsent::create([
            'family_id' => $family->id,
            'guardian_user_id' => $request->user()->id,
            'learner_profile_id' => $learner->id,
            'type' => $this->consentType($request->input('date_of_birth')),
            'policy_version' => (string) config('compliance.policy_version'),
            'granted_at' => now(),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);

        if ($learner->target_language_id !== null) {
            $courses = Course::where('language_id', $learner->target_language_id)->where('is_published', true)
                ->whereHas('levels.lessons', fn ($lessons) => $lessons->whereNotNull('published_at'))->orderBy('id')->get();
            foreach ($courses as $course) {
                $enrollment = Enrollment::firstOrCreate(['learner_profile_id' => $learner->id, 'course_id' => $course->id],
                    ['status' => 'active', 'started_at' => now()]);
                app(PathBuilder::class)->build($enrollment);
            }
        }

        return response()->json(['data' => ['id' => $learner->id, 'display_name' => $learner->display_name]], 201);
    }

    /** Parent-only child detail, including the child's own ledger-backed coins. */
    public function child(LearnerProfile $learner, Request $request): JsonResponse
    {
        $family = $this->family($request->user());
        abort_unless((int) $learner->family_id === $family->id, 403, 'Not your family.');

        $learner->load(['targetLanguage', 'profilePhoto']);
        $wallet = $this->wallets->walletFor($learner);

        return response()->json(['data' => [
            'id' => $learner->id,
            'display_name' => $learner->display_name,
            'learning_goal' => $learner->learning_goal,
            'avatar_id' => $learner->avatar_id,
            'avatar_url' => $this->avatarUrl($learner),
            'age_band' => $learner->age_band,
            'current_level' => $learner->current_level,
            'starting_level_position' => $learner->starting_level_position,
            'starting_level_options' => $this->startingLevelOptions($learner),
            'target_language' => $learner->targetLanguage?->code,
            'pin_protected' => $learner->parental_pin !== null,
            // This is the learner-owned wallet balance maintained atomically
            // alongside the append-only transactions returned below.
            'coin_balance' => $wallet->coin_balance,
            'currency_minor' => $wallet->currency_balance_minor,
            'coin_transactions' => $wallet->transactions()
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn ($transaction) => [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'source' => $transaction->source,
                    'amount' => $transaction->amount,
                    'balance_after' => $transaction->balance_after,
                    'created_at' => $transaction->created_at,
                ])->values(),
        ]]);
    }

    public function setStartingLevel(LearnerProfile $learner, Request $request, PathBuilder $paths): JsonResponse
    {
        $family = $this->family($request->user());
        abort_unless((int) $learner->family_id === (int) $family->id && $learner->user_id !== $request->user()->id, 403);
        $values = $request->validate(['position' => ['required', 'integer', 'min:0']]);
        abort_unless(in_array($values['position'], $this->startingLevelOptions($learner), true), 422,
            'Choose a level offered by this child’s courses.');
        abort_if($learner->lessonProgress()->exists() || $learner->enrollments()->whereHas('pathNodes', fn ($q) => $q->where('state', 'completed'))->exists(),
            409, 'Starting level can only be changed before this child starts lessons.');

        DB::transaction(function () use ($learner, $values, $paths) {
            $learner->update(['starting_level_position' => $values['position']]);
            foreach ($learner->enrollments()->with('course')->get() as $enrollment) {
                $enrollment->pathNodes()->delete();
                $enrollment->setRelation('learnerProfile', $learner);
                $paths->build($enrollment);
            }
        });
        $this->audit->record('family.starting_level_changed', $learner, [], ['position' => $values['position']]);

        return response()->json(['data' => ['starting_level_position' => $values['position']]]);
    }

    /** @return array<int, int> */
    private function startingLevelOptions(LearnerProfile $learner): array
    {
        $courses = $learner->enrollments()->with('course.levels')->get()->pluck('course')->filter();
        if ($courses->isEmpty()) {
            return [0];
        }
        $options = null;
        foreach ($courses as $course) {
            $positions = $course->levels->filter(fn ($level) => $level->lessons()->whereNotNull('published_at')->exists())
                ->pluck('position')->map(fn ($position) => (int) $position)->all();
            $options = $options === null ? $positions : array_values(array_intersect($options, $positions));
        }

        return array_values(array_unique($options ?? []));
    }

    /** Set, change, or clear one child's own PIN — each child has a distinct code. */
    public function setChildPin(LearnerProfile $learner, SetChildPinRequest $request): JsonResponse
    {
        $family = $this->family($request->user());
        abort_unless((int) $learner->family_id === $family->id, 403, 'Not your family.');

        $pin = $request->input('pin');
        $wasProtected = $learner->parental_pin !== null;
        $learner->update(['parental_pin' => $pin === null ? null : Hash::make($pin)]);

        $this->audit->record(
            'family.child_pin_set',
            $learner,
            ['pin_protected' => $wasProtected],
            ['pin_protected' => $pin !== null],
        );

        return response()->json(['data' => ['id' => $learner->id, 'pin_protected' => $pin !== null]]);
    }

    /** COPPA applies under the configured minor age; otherwise general data-processing consent. */
    private function consentType(?string $dateOfBirth): string
    {
        if ($dateOfBirth === null) {
            return 'coppa_parental'; // unknown age → treat as a minor (safe default)
        }

        $age = Carbon::parse($dateOfBirth)->age;

        $minorAge = (int) $this->settings->get('compliance.minor_age', config('compliance.minor_age'));

        return $age < $minorAge ? 'coppa_parental' : 'data_processing';
    }

    private function avatarUrl(LearnerProfile $learner): ?string
    {
        if (! $learner->profilePhoto) {
            return null;
        }

        return str_starts_with($learner->profilePhoto->url, 'http')
            ? $learner->profilePhoto->url
            : Storage::disk('public')->url($learner->profilePhoto->url);
    }
}

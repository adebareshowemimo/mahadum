<?php

namespace App\Http\Controllers\Family;

use App\Http\Controllers\Concerns\ResolvesFamily;
use App\Http\Controllers\Controller;
use App\Models\CoinTransaction;
use App\Models\Family;
use App\Models\FamilyAlertPreference;
use App\Models\FamilyChallenge;
use App\Models\FamilyCheer;
use App\Models\FamilyCoinPool;
use App\Models\LearnerProfile;
use App\Models\LessonProgress;
use App\Models\Wallet;
use App\Services\AuditLogger;
use App\Services\Family\FamilyAlertService;
use App\Services\Family\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class FamilyGoalsController extends Controller
{
    use ResolvesFamily;

    private function ownedFamily(Request $request): Family
    {
        abort_unless($request->user()->status === 'active', 403);

        return $this->family($request->user());
    }

    public function index(Request $request, WalletService $wallets): JsonResponse
    {
        $family = $this->ownedFamily($request);
        $challenges = FamilyChallenge::where('family_id', $family->id)->latest()->get()->map(function ($challenge) use ($family) {
            $ids = $family->learnerProfiles()->whereIn('id', $challenge->learner_ids)->pluck('id');
            $completed = LessonProgress::whereIn('learner_profile_id', $ids)->where('status', 'completed')
                ->whereBetween('completed_at', [$challenge->starts_at, $challenge->ends_at])->count();

            return ['id' => $challenge->id, 'title' => $challenge->title, 'target_lessons' => $challenge->target_lessons,
                'completed_lessons' => $completed, 'learner_ids' => $ids, 'starts_at' => $challenge->starts_at, 'ends_at' => $challenge->ends_at,
                'status' => $completed >= $challenge->target_lessons ? 'completed' : ($challenge->ends_at->isPast() ? 'ended' : 'active')];
        });
        $pools = FamilyCoinPool::where('family_id', $family->id)->latest()->get()->map(function ($pool) use ($wallets) {
            $wallet = $wallets->walletFor($pool);

            return ['id' => $pool->id, 'name' => $pool->name, 'goal_coins' => $pool->goal_coins, 'coin_balance' => $wallet->coin_balance,
                'transactions' => CoinTransaction::where('wallet_id', $wallet->id)->latest('id')->limit(20)->get(['id', 'type', 'source', 'amount', 'balance_after', 'created_at'])];
        });

        return response()->json(['data' => ['pools' => $pools, 'challenges' => $challenges,
            'alerts' => FamilyAlertPreference::where('family_id', $family->id)->first(['low_balance_coins', 'inactive_days', 'review_alerts'])
                ?? ['low_balance_coins' => null, 'inactive_days' => null, 'review_alerts' => false]]]);
    }

    public function createChallenge(Request $request): JsonResponse
    {
        $family = $this->ownedFamily($request);
        $values = $request->validate(['title' => ['required', 'string', 'max:255'], 'target_lessons' => ['required', 'integer', 'min:1', 'max:10000'],
            'learner_ids' => ['required', 'array', 'min:1'], 'learner_ids.*' => ['integer', 'distinct'], 'ends_at' => ['required', 'date', 'after:now']]);
        abort_unless($family->learnerProfiles()->whereIn('id', $values['learner_ids'])->count() === count($values['learner_ids']), 422, 'Choose only learners in your family.');
        $challenge = FamilyChallenge::create([...$values, 'family_id' => $family->id, 'starts_at' => now()]);
        app(AuditLogger::class)->record('family.challenge_created', $challenge, [], ['target_lessons' => $challenge->target_lessons]);

        return response()->json(['data' => ['id' => $challenge->id]], 201);
    }

    public function createPool(Request $request): JsonResponse
    {
        $family = $this->ownedFamily($request);
        $values = $request->validate(['name' => ['required', 'string', 'max:255'], 'goal_coins' => ['required', 'integer', 'min:1', 'max:1000000']]);
        $pool = FamilyCoinPool::create([...$values, 'family_id' => $family->id]);
        app(AuditLogger::class)->record('family.pool_created', $pool, [], $values);

        return response()->json(['data' => ['id' => $pool->id]], 201);
    }

    public function movePool(Request $request, FamilyCoinPool $pool, WalletService $wallets): JsonResponse
    {
        $family = $this->ownedFamily($request);
        abort_unless((int) $pool->family_id === (int) $family->id, 404);
        $values = $request->validate(['direction' => ['required', 'in:contribute,return,distribute'], 'coins' => ['required', 'integer', 'min:1', 'max:1000000'],
            'learner_id' => ['required_if:direction,distribute', 'nullable', 'integer']]);
        $key = (string) $request->header('Idempotency-Key');
        abort_unless($key !== '' && strlen($key) <= 100, 422, 'A request key is required.');
        $fingerprint = hash('sha256', json_encode([$pool->id, $values]));
        DB::transaction(function () use ($family, $pool, $wallets, $values, $key, $fingerprint, $request) {
            Family::whereKey($family->id)->lockForUpdate()->firstOrFail();
            if ($prior = DB::table('family_pool_movements')->where('family_id', $family->id)->where('request_key', $key)->first()) {
                abort_unless($prior->fingerprint === $fingerprint, 409, 'This request key was used for another movement.');

                return;
            }
            $poolWallet = $wallets->walletFor($pool);
            $familyWallet = $wallets->walletFor($family);
            $learner = $values['direction'] === 'distribute' ? $family->learnerProfiles()->findOr($values['learner_id'], fn () => abort(422, 'Choose a learner in your family.')) : null;
            $otherWallet = $learner ? $wallets->walletFor($learner) : $familyWallet;
            $from = $values['direction'] === 'contribute' ? $familyWallet : $poolWallet;
            $to = $values['direction'] === 'contribute' ? $poolWallet : $otherWallet;
            $locked = Wallet::whereIn('id', [$from->id, $to->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            abort_unless($locked[$from->id]->coin_balance >= $values['coins'], 422, 'Not enough coins for this movement.');
            $source = 'pool_'.$values['direction'];
            $wallets->debit($from, $values['coins'], $source, $learner?->id, $pool);
            $wallets->credit($to, $values['coins'], $source, $learner?->id, $pool);
            DB::table('family_pool_movements')->insert(['family_id' => $family->id, 'family_coin_pool_id' => $pool->id, 'request_key' => $key,
                'fingerprint' => $fingerprint, 'direction' => $values['direction'], 'coins' => $values['coins'], 'learner_profile_id' => $learner?->id,
                'approved_by_user_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            app(AuditLogger::class)->record('family.pool_moved', $pool, [], ['direction' => $values['direction'], 'coins' => $values['coins'], 'learner_id' => $learner?->id]);
        });

        rescue(fn () => app(FamilyAlertService::class)->evaluate($family), report: true);

        return response()->json(['data' => ['pool_balance' => $wallets->walletFor($pool)->coin_balance]]);
    }

    public function preferences(Request $request): JsonResponse
    {
        $family = $this->ownedFamily($request);
        $values = $request->validate(['low_balance_coins' => ['present', 'nullable', 'integer', 'min:0', 'max:1000000'],
            'inactive_days' => ['present', 'nullable', 'integer', 'min:1', 'max:365'], 'review_alerts' => ['required', 'boolean']]);
        $old = FamilyAlertPreference::where('family_id', $family->id)->first()?->toArray() ?? [];
        $preferences = FamilyAlertPreference::updateOrCreate(['family_id' => $family->id], $values);
        app(AuditLogger::class)->record('family.alert_preferences_updated', $preferences, $old, $values);

        return response()->json(['data' => $values]);
    }

    public function cheer(Request $request, LearnerProfile $learner): JsonResponse
    {
        $family = $this->ownedFamily($request);
        abort_unless((int) $learner->family_id === (int) $family->id, 403);
        $values = $request->validate(['message' => ['required', 'in:Keep going!,Well done!,We are proud of you!']]);
        $cheer = FamilyCheer::firstOrCreate(['learner_profile_id' => $learner->id, 'sender_user_id' => $request->user()->id, 'week_start' => now()->startOfWeek()->toDateString()],
            ['family_id' => $family->id, 'message' => $values['message']]);

        return response()->json(['data' => ['id' => $cheer->id, 'message' => $cheer->message]], $cheer->wasRecentlyCreated ? 201 : 200);
    }

    public function cheers(LearnerProfile $learner): JsonResponse
    {
        Gate::authorize('redeemReward', $learner);

        return response()->json(['data' => FamilyCheer::where('learner_profile_id', $learner->id)->where('family_id', $learner->family_id)
            ->whereDate('week_start', now()->startOfWeek())->orderBy('id')->get(['id', 'message'])]);
    }
}

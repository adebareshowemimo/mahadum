<?php

namespace App\Http\Controllers\Family;

use App\Http\Controllers\Concerns\ResolvesFamily;
use App\Http\Controllers\Controller;
use App\Http\Requests\Family\AddChildRequest;
use App\Http\Requests\Family\SetChildPinRequest;
use App\Models\FamilyMember;
use App\Models\LearnerProfile;
use App\Models\ParentalConsent;
use App\Services\AuditLogger;
use App\Services\Family\WalletService;
use App\Services\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class FamilyController extends Controller
{
    use ResolvesFamily;

    public function __construct(private WalletService $wallets, private AuditLogger $audit, private Settings $settings) {}

    public function show(Request $request): JsonResponse
    {
        $family = $this->family($request->user())->load(['learnerProfiles.targetLanguage', 'learnerProfiles.profilePhoto', 'owner']);
        $wallet = $this->wallets->walletFor($family);

        return response()->json(['data' => [
            'id' => $family->id,
            'name' => $family->name,
            'child_limit' => $family->child_limit,
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
            ])->values(),
        ]]);
    }

    public function addChild(AddChildRequest $request): JsonResponse
    {
        $family = $this->family($request->user());

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
            'avatar_id' => $learner->avatar_id,
            'avatar_url' => $this->avatarUrl($learner),
            'age_band' => $learner->age_band,
            'current_level' => $learner->current_level,
            'target_language' => $learner->targetLanguage?->code,
            'pin_protected' => $learner->parental_pin !== null,
            // This is the learner-owned wallet balance maintained atomically
            // alongside the append-only transactions returned below.
            'coin_balance' => $wallet->coin_balance,
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

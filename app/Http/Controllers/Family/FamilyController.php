<?php

namespace App\Http\Controllers\Family;

use App\Http\Controllers\Concerns\ResolvesFamily;
use App\Http\Controllers\Controller;
use App\Http\Requests\Family\AddChildRequest;
use App\Http\Requests\Family\SetPinRequest;
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

class FamilyController extends Controller
{
    use ResolvesFamily;

    public function __construct(private WalletService $wallets, private AuditLogger $audit, private Settings $settings) {}

    public function show(Request $request): JsonResponse
    {
        $family = $this->family($request->user())->load(['learnerProfiles.targetLanguage', 'members.user']);
        $wallet = $this->wallets->walletFor($family);

        return response()->json(['data' => [
            'id' => $family->id,
            'name' => $family->name,
            'child_limit' => $family->child_limit,
            'pin_set' => $family->parental_pin !== null,
            'wallet' => [
                'coin_balance' => $wallet->coin_balance,
                'currency_minor' => $wallet->currency_balance_minor,
                'currency' => $wallet->currency,
            ],
            'learners' => $family->learnerProfiles->map(fn ($l) => [
                'id' => $l->id,
                'display_name' => $l->display_name,
                'is_child' => $l->user_id === null,
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
            'parental_pin_protected' => $family->parental_pin !== null,
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

    public function setPin(SetPinRequest $request): JsonResponse
    {
        $family = $this->family($request->user());
        $hadPin = $family->parental_pin !== null;
        $family->update(['parental_pin' => Hash::make($request->string('pin'))]);

        // Existing child profiles become PIN-protected once a PIN exists.
        $family->learnerProfiles()->whereNull('user_id')->update(['parental_pin_protected' => true]);

        $this->audit->record('family.pin_set', $family, ['pin_set' => $hadPin], ['pin_set' => true]);

        return response()->json(['data' => ['pin_set' => true]]);
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
}

<?php

namespace App\Services\Referral;

use App\Models\Commission;
use App\Models\LearnerProfile;
use App\Models\LessonProgress;
use App\Models\Organization;
use App\Models\QuizAttempt;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\ReferralInvitation;
use App\Models\Subscription;
use App\Models\User;
use App\Models\XpLedger;
use App\Services\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Referral lifecycle:
 *   1. issue a code (codeFor) and send invites by email/phone (invite),
 *   2. attribute a sign-up to a code + match it back to an invite (attribute),
 *   3. ACTIVATE once the referred person holds a paid subscription AND has
 *      finished the required lessons + quizzes (maybeActivate),
 *   4. pay the referrer a % of every purchase the referred person makes within
 *      the earning window after activation (recordReferredPurchase),
 *   5. unwind commissions on refund/chargeback (reverseForSource).
 *
 * Every rule (commission %, windows, activation thresholds, velocity limit) is a
 * live admin setting read through App\Services\Settings — see config/settings.php.
 *
 * Fraud guards: self-referral block, device-fingerprint reuse block (FR-7.1),
 * code freeze (FlagReferralVelocity / FR-7.5), and the "can't refer an existing
 * active account" guard on invite().
 */
class ReferralService
{
    public function __construct(private Settings $settings) {}

    /**
     * @param  User|Organization  $owner  ReferralCode.owner is polymorphic — a
     *                                    personal code (kind 'user') or a school's own code (kind 'org').
     */
    public function codeFor(Model $owner): ReferralCode
    {
        return ReferralCode::firstOrCreate(
            ['owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey(), 'kind' => $owner instanceof Organization ? 'org' : 'user'],
            ['code' => $this->uniqueCode(), 'status' => 'active'],
        );
    }

    /**
     * Send (or re-send) an invite to a specific email or phone. Refuses when the
     * contact already belongs to an active account (ReferralAccountExistsException)
     * or is the inviter's own. Idempotent per (code, contact).
     *
     * @param  string  $channel  'email' | 'phone'
     */
    public function invite(User $inviter, string $channel, string $rawContact): ReferralInvitation
    {
        $contact = $channel === 'email' ? $this->normalizeEmail($rawContact) : $this->normalizePhone($rawContact);
        abort_if($contact === null, 422, 'Enter a valid '.$channel.'.');

        if ($this->normalizeEmail($inviter->email) === $contact || $this->normalizePhone($inviter->phone) === $contact) {
            abort(422, "That's your own contact — invite someone else.");
        }

        if ($this->activeAccountExists($channel, $contact, $rawContact)) {
            throw new ReferralAccountExistsException;
        }

        $code = $this->codeFor($inviter);

        $invitation = ReferralInvitation::firstOrNew([
            'referral_code_id' => $code->id,
            'contact' => $contact,
        ]);
        $invitation->fill([
            'inviter_user_id' => $inviter->id,
            'channel' => $channel,
            'status' => $invitation->status === 'accepted' ? 'accepted' : 'sent',
            'sent_at' => now(),
        ])->save();

        return $invitation;
    }

    /**
     * Record a sign-up against a referral code. Returns the Referral, or null if
     * the code is unknown/inactive or it's a self-referral. Device reuse is
     * recorded as `rejected` (kept for audit), not silently dropped. A pending
     * invitation matching the new user's email/phone is linked and marked accepted.
     */
    public function attribute(User $referred, ?string $code, ?string $fingerprint): ?Referral
    {
        if (! $code) {
            return null;
        }

        $referralCode = ReferralCode::where('code', $code)->where('status', 'active')->first();

        if (! $referralCode) {
            return null;
        }

        // Self-referral guard.
        if ($referralCode->owner_type === $referred->getMorphClass() && (int) $referralCode->owner_id === (int) $referred->id) {
            return null;
        }

        // FR-7.1: same device already used for a referral → fraud.
        $deviceReused = $fingerprint && Referral::where('device_fingerprint', $fingerprint)->exists();

        $invitation = $this->matchInvitation($referralCode, $referred);

        $referral = Referral::create([
            'referral_code_id' => $referralCode->id,
            'referred_user_id' => $referred->id,
            'device_fingerprint' => $fingerprint,
            'status' => $deviceReused ? 'rejected' : 'pending',
            'signed_up_at' => now(),
            'contact_channel' => $invitation?->channel,
            'contact_value' => $invitation?->contact,
            'referral_invitation_id' => $invitation?->id,
        ]);

        $invitation?->update(['status' => 'accepted', 'accepted_referral_id' => $referral->id]);

        return $referral;
    }

    /**
     * Re-check the activation gate for every pending referral tied to the user(s)
     * who own or head the household this learner belongs to. Called from lesson
     * and quiz completion.
     */
    public function maybeActivateForLearner(LearnerProfile $learner): void
    {
        $userIds = array_values(array_filter([
            $learner->user_id,
            $learner->family?->owner_user_id,
        ]));

        foreach ($userIds as $userId) {
            $this->maybeActivatePendingFor((int) $userId);
        }
    }

    /** Re-check the activation gate for every pending referral of one user. */
    public function maybeActivateForUser(User $user): void
    {
        $this->maybeActivatePendingFor($user->id);
    }

    private function maybeActivatePendingFor(int $userId): void
    {
        Referral::where('status', 'pending')
            ->where('referred_user_id', $userId)
            ->whereHas('referralCode', fn ($q) => $q->where('status', 'active'))
            ->get()
            ->each(fn (Referral $referral) => $this->maybeActivate($referral));
    }

    /**
     * Activation gate (idempotent): the referred person must have finished at
     * least the configured number of lessons and quizzes and — unless disabled —
     * hold a paid subscription. On success the referral becomes `qualified` with
     * an `activated_at` stamp; no commission is created here (the qualifying
     * subscription predates activation).
     */
    public function maybeActivate(Referral $referral): void
    {
        if ($referral->activated_at !== null || $referral->status !== 'pending') {
            return;
        }

        $referred = $referral->referredUser;
        if (! $referred) {
            return;
        }

        $profileIds = $this->householdLearnerProfileIds($referred);

        $lessons = $profileIds === [] ? 0 : LessonProgress::whereIn('learner_profile_id', $profileIds)
            ->where('status', 'completed')->count();
        $quizzes = $profileIds === [] ? 0 : QuizAttempt::whereIn('learner_profile_id', $profileIds)
            ->whereNotNull('completed_at')->count();

        $updates = [];
        if ($lessons > 0 && $referral->first_lesson_completed_at === null) {
            $updates['first_lesson_completed_at'] = now();
        }
        if ($quizzes > 0 && $referral->first_quiz_completed_at === null) {
            $updates['first_quiz_completed_at'] = now();
        }

        $minLessons = (int) $this->settings->get('referral.activation_min_lessons', 1);
        $minQuizzes = (int) $this->settings->get('referral.activation_min_quizzes', 1);
        $requiresSub = (bool) $this->settings->get('referral.activation_requires_paid_subscription', true);

        $paidSubscription = $this->activePaidSubscription($referred);

        $activates = $lessons >= $minLessons
            && $quizzes >= $minQuizzes
            && (! $requiresSub || $paidSubscription !== null);

        if ($activates) {
            $updates['status'] = 'qualified';
            $updates['activated_at'] = now();
            if ($paidSubscription !== null && $referral->referred_subscription_id === null) {
                $updates['referred_subscription_id'] = $paidSubscription->id;
            }
        }

        if ($updates !== []) {
            $referral->update($updates);
        }
    }

    /**
     * A purchase the referred person made. If their referral is activated and we
     * are still inside the earning window, create an escrowed commission for the
     * code owner worth `referral.commission_bps` of the amount. Idempotent on
     * `$sourceEvent` (one commission per settled webhook).
     */
    public function recordReferredPurchase(User $payer, Model $source, ?int $amountMinor, string $sourceEvent): ?Commission
    {
        $amountMinor = (int) $amountMinor;
        if ($amountMinor < 1) {
            return null;
        }

        $referral = Referral::where('referred_user_id', $payer->id)
            ->where('status', 'qualified')
            ->whereNotNull('activated_at')
            ->whereHas('referralCode', fn ($q) => $q->where('status', 'active'))
            ->with('referralCode')
            ->first();

        if (! $referral || $referral->activated_at === null) {
            return null;
        }

        $windowDays = (int) $this->settings->get('referral.earning_window_days', 30);
        if (now()->greaterThan($referral->activated_at->copy()->addDays($windowDays))) {
            return null;
        }

        if (Commission::where('source_event', $sourceEvent)->exists()) {
            return null;
        }

        $bps = (int) $this->settings->get('referral.commission_bps', 500);
        $amount = intdiv($amountMinor * $bps, 10_000);
        if ($amount < 1) {
            return null;
        }

        $escrowDays = (int) $this->settings->get('referral.escrow_days', 14);

        $commission = new Commission([
            'amount_minor' => $amount,
            'status' => 'pending_escrow',
            'kind' => 'purchase',
            'source_event' => $sourceEvent,
            'escrow_until' => now()->addDays($escrowDays),
        ]);
        $commission->referral()->associate($referral);
        $commission->beneficiary()->associate($referral->referralCode->owner);
        $commission->source()->associate($source);
        $commission->save();

        return $commission;
    }

    /**
     * Refund/chargeback clawback (FR-7.3): unwind commissions a purchase earned.
     * A still-escrowed commission is voided (`reversed`); an already-cleared one
     * is flagged `clawback_pending` for finance. Idempotent.
     */
    public function reverseForSource(string $type, int $id): void
    {
        $commissions = Commission::where('source_type', $type)->where('source_id', $id)->get();

        // Legacy qualifying commissions link only via referrals.referred_subscription_id.
        if ($type === Subscription::class) {
            $legacy = Commission::whereHas('referral', fn ($q) => $q->where('referred_subscription_id', $id))->get();
            $commissions = $commissions->concat($legacy)->unique('id');
        }

        foreach ($commissions as $commission) {
            $reversedStatus = match ($commission->status) {
                'pending_escrow' => 'reversed',
                'cleared' => 'clawback_pending',
                default => null,
            };

            if ($reversedStatus !== null) {
                $commission->update(['status' => $reversedStatus]);
            }
        }

        // A refunded qualifying subscription also drops the referral out of qualified.
        if ($type === Subscription::class) {
            Referral::where('referred_subscription_id', $id)
                ->where('status', 'qualified')
                ->update(['status' => 'reversed', 'activated_at' => null]);
        }
    }

    /** Back-compat shim for PaymentService. */
    public function reverseForSubscription(Subscription $subscription): void
    {
        $this->reverseForSource(Subscription::class, $subscription->id);
    }

    /**
     * Is the referred person "active" — logged in, or any learning activity in
     * their household, within the last 30 days? Drives the dashboards' status.
     */
    public function isReferredUserActive(Referral $referral, ?Carbon $since = null): bool
    {
        $since ??= now()->subDays(30);
        $user = $referral->referredUser;
        if (! $user) {
            return false;
        }

        if ($user->last_login_at !== null && $user->last_login_at->greaterThanOrEqualTo($since)) {
            return true;
        }

        $profileIds = $this->householdLearnerProfileIds($user);
        if ($profileIds === []) {
            return false;
        }

        return XpLedger::whereIn('learner_profile_id', $profileIds)->where('created_at', '>=', $since)->exists()
            || LessonProgress::whereIn('learner_profile_id', $profileIds)->where('updated_at', '>=', $since)->exists();
    }

    private function matchInvitation(ReferralCode $code, User $user): ?ReferralInvitation
    {
        $candidates = array_values(array_filter([
            $this->normalizeEmail($user->email),
            $this->normalizePhone($user->phone),
        ]));

        if ($candidates === []) {
            return null;
        }

        return $code->invitations()
            ->where('status', 'sent')
            ->whereIn('contact', $candidates)
            ->orderBy('id')
            ->first();
    }

    private function activePaidSubscription(User $user): ?Subscription
    {
        return Subscription::where('subscriber_type', $user->getMorphClass())
            ->where('subscriber_id', $user->id)
            ->where('status', 'active')
            ->whereHas('plan', fn ($q) => $q->where('price_minor', '>', 0))
            ->first();
    }

    /**
     * @return array<int, int>
     */
    private function householdLearnerProfileIds(User $user): array
    {
        $familyIds = $user->ownedFamilies()->pluck('id')->all();

        return LearnerProfile::query()
            ->where(fn ($q) => $q->where('user_id', $user->id)
                ->orWhereIn('family_id', $familyIds))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function activeAccountExists(string $channel, string $contact, string $rawContact): bool
    {
        $query = User::where('status', 'active');

        if ($channel === 'email') {
            return $query->whereRaw('LOWER(email) = ?', [$contact])->exists();
        }

        return $query->where(fn ($q) => $q
            ->where('phone', $rawContact)
            ->orWhere('phone', $contact)
            ->orWhere('phone', '+'.$contact)
            ->orWhere('phone', '0'.substr($contact, 3)))
            ->exists();
    }

    private function normalizeEmail(?string $email): ?string
    {
        $email = strtolower(trim((string) $email));

        return $email !== '' && str_contains($email, '@') ? $email : null;
    }

    private function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if (strlen($digits) < 7) {
            return null;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = '234'.substr($digits, 1);
        }

        return $digits;
    }

    private function uniqueCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (ReferralCode::where('code', $code)->exists());

        return $code;
    }
}

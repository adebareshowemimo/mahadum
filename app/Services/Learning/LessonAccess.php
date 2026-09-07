<?php

namespace App\Services\Learning;

use App\Models\LearnerProfile;
use App\Models\Lesson;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\EntitlementResolver;
use App\Services\Gamification\PracticeModeService;
use Illuminate\Http\Exceptions\HttpResponseException;

class LessonAccess
{
    public function __construct(private EntitlementResolver $entitlements, private PracticeModeService $hearts) {}

    /** Resolve actual subscriptions so airtime cannot inherit card-plan content access. */
    public function tier(LearnerProfile $learner): string
    {
        $learner->loadMissing('family');
        $subscriptions = Subscription::with('plan')->whereIn('status', ['active', 'grace'])
            ->where(function ($query) use ($learner) {
                $query->where(function ($q) use ($learner) {
                    $q->where('subscriber_type', User::class)
                        ->whereIn('subscriber_id', array_filter([$learner->user_id, $learner->family?->owner_user_id]));
                });
                if ($learner->organization_id !== null) {
                    $query->orWhere(fn ($q) => $q->where('subscriber_type', Organization::class)->where('subscriber_id', $learner->organization_id));
                }
            })->get()->filter(fn ($subscription) => $subscription->plan->price_minor > 0
                || ($subscription->subscriber_type === Organization::class && $subscription->plan->audience === 'school'));

        if ($subscriptions->contains(fn ($subscription) => $subscription->method !== 'airtime')) {
            return 'paid';
        }

        return $subscriptions->isNotEmpty() ? 'telco' : 'free';
    }

    /** @return array{allowed:bool, reason:?string} */
    public function content(LearnerProfile $learner, Lesson $lesson, ?string $tier = null): array
    {
        $tier ??= $this->tier($learner);
        $allowed = match ($tier) {
            'paid' => true,
            'telco' => (int) $lesson->courseLevel->position === 1,
            default => (bool) $lesson->is_free_preview,
        };

        return ['allowed' => $allowed, 'reason' => $allowed ? null : ($tier === 'telco'
            ? 'Your airtime subscription includes Level 1 only. Choose a card or bank plan to unlock other levels.'
            : 'Lesson 0 is free. Upgrade to unlock Lesson 1 and all subsequent lessons.')];
    }

    public function authorize(LearnerProfile $learner, Lesson $lesson): void
    {
        abort_if($lesson->published_at === null, 404, 'This lesson is not published.');
        $access = $this->content($learner, $lesson);
        if (! $access['allowed']) {
            $this->deny('lesson_subscription_required', $access['reason'], 403);
        }
        if (! $this->entitlements->forLearner($learner)['unlimited_hearts']) {
            $state = $this->hearts->state($learner);
            if ($state['practice_mode']) {
                $this->deny('hearts_exhausted', 'See you in 12 hours, or upgrade for unlimited hearts.', 423, $state['competitive_paused_until']);
            }
        }
    }

    private function deny(string $code, string $message, int $status, ?string $until = null): never
    {
        throw new HttpResponseException(response()->json(['error' => [
            'code' => $code, 'message' => $message, 'status' => $status,
            'details' => ['locked_until' => $until],
        ]], $status));
    }
}

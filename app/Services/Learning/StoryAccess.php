<?php

namespace App\Services\Learning;

use App\Models\LearnerProfile;
use App\Models\Story;
use App\Models\StoryPurchase;
use App\Models\User;
use App\Services\UserAccountType;
use App\Support\ActiveChildProfile;
use Illuminate\Support\Facades\Gate;

class StoryAccess
{
    public function canPurchase(LearnerProfile $learner, User $viewer): bool
    {
        if (ActiveChildProfile::id(request()) !== null) {
            return false;
        }
        if (! Gate::forUser($viewer)->allows('redeemReward', $learner)) {
            return false;
        }

        return $viewer->hasRole('parent')
            || ($learner->user_id === $viewer->id
                && $this->ageFor($learner, $viewer) >= 18
                && $learner->organization_id === null);
    }

    public function isVisible(Story $story, LearnerProfile $learner, User $viewer): bool
    {
        if ($story->age_band !== '18_plus') {
            return true;
        }

        return $this->maySeeAdult($learner, $viewer);
    }

    public function maySeeAdult(LearnerProfile $learner, User $viewer): bool
    {
        return (! $viewer->hasAnyRole(['teacher', 'school_admin', 'supervisor'])
                || UserAccountType::forUser($viewer) === 'family')
            && $learner->organization_id === null
            && $this->ageFor($learner, $viewer) >= 18;
    }

    public function ageFor(LearnerProfile $learner, User $viewer): ?int
    {
        // Direct learner profiles created before DOB was copied at sign-up
        // still belong to the account holder. Family child DOB never falls
        // back to a parent's age.
        return ($learner->date_of_birth
            ?? ($learner->user_id === $viewer->id && $learner->family_id === null
                ? $viewer->date_of_birth
                : null))?->age;
    }

    public function isPurchased(Story $story, LearnerProfile $learner): bool
    {
        $learner->loadMissing('family');
        $payerIds = array_unique(array_filter([$learner->user_id, $learner->family?->owner_user_id]));

        return StoryPurchase::where('story_id', $story->id)
            ->whereIn('user_id', $payerIds)
            ->where('status', 'paid')
            ->exists();
    }

    public function previewPageCount(Story $story): int
    {
        return $story->preview_count ?? (in_array($story->format, ['colouring', 'tracing'], true) ? 12 : 1);
    }
}

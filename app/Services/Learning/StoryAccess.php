<?php

namespace App\Services\Learning;

use App\Models\LearnerProfile;
use App\Models\Story;
use App\Models\StoryPurchase;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class StoryAccess
{
    public function canPurchase(LearnerProfile $learner, User $viewer): bool
    {
        if (! Gate::forUser($viewer)->allows('redeemReward', $learner)) {
            return false;
        }

        return $viewer->hasRole('parent')
            || ($learner->user_id === $viewer->id
                && $learner->date_of_birth?->age >= 18
                && $learner->organization_id === null);
    }

    public function isVisible(Story $story, LearnerProfile $learner, User $viewer): bool
    {
        if ($story->age_band !== '18_plus') {
            return true;
        }

        return ! $viewer->hasAnyRole(['teacher', 'school_admin', 'supervisor'])
            && $learner->organization_id === null
            && $learner->date_of_birth !== null
            && $learner->date_of_birth->age >= 18;
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
        return in_array($story->format, ['colouring', 'tracing'], true) ? 12 : 1;
    }
}

<?php

namespace App\Services\Learning;

use App\Models\LearnerProfile;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Who a "Practice with me" invitation may be sent to for a given learner:
 * another adult in the learner's family, or teaching staff at the learner's
 * school. Shared by the recipient search (PracticeContactController) and the
 * invitation store (CoursePracticeInvitationController) so eligibility can't
 * drift between "who shows up in search" and "who can actually be invited".
 */
class PracticeRecipientEligibility
{
    private const SCHOOL_STAFF_ROLES = ['teacher', 'supervisor', 'school_admin'];

    /** @return Collection<int, array{id:int,name:string,email:string,relation:string,role:string}> */
    public function candidates(LearnerProfile $learner, User $excluding, string $search): Collection
    {
        return $learner->family_id !== null
            ? $this->familyCandidates($learner, $excluding, $search)
            : $this->schoolCandidates($learner, $excluding, $search);
    }

    public function isEligible(LearnerProfile $learner, User $excluding, User $recipient): bool
    {
        if ($recipient->id === $learner->user_id || $recipient->status !== 'active') {
            return false;
        }

        if ($learner->family_id !== null) {
            return $recipient->hasVerifiedEmail()
                && $recipient->hasAnyRole(['parent', 'teacher', 'supervisor'])
                && DB::table('learner_practice_partners')->where('learner_profile_id', $learner->id)
                    ->where('user_id', $recipient->id)->exists();
        }

        if ($learner->organization_id === null || ! $recipient->hasAnyRole(self::SCHOOL_STAFF_ROLES)) {
            return false;
        }

        return OrganizationUser::where('organization_id', $learner->organization_id)
            ->where('user_id', $recipient->id)
            ->where('status', 'active')
            ->whereIn('role', self::SCHOOL_STAFF_ROLES)
            ->exists();
    }

    /** @return Collection<int, array{id:int,name:string,email:string,relation:string,role:string}> */
    private function familyCandidates(LearnerProfile $learner, User $excluding, string $search): Collection
    {
        return User::query()
            ->whereIn('id', DB::table('learner_practice_partners')->where('learner_profile_id', $learner->id)->pluck('user_id'))
            ->when($learner->user_id !== null, fn ($query) => $query->where('id', '!=', $learner->user_id))
            ->where('status', 'active')
            ->whereNotNull('email_verified_at')
            ->role(['parent', 'teacher', 'supervisor'])
            ->when($search !== '', fn ($q) => $q->where(fn ($q2) => $q2->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->limit(20)
            ->get()
            ->map(fn (User $user) => $this->present($user, 'family', 'guardian'));
    }

    /** @return Collection<int, array{id:int,name:string,email:string,relation:string,role:string}> */
    private function schoolCandidates(LearnerProfile $learner, User $excluding, string $search): Collection
    {
        if ($learner->organization_id === null) {
            return collect();
        }

        $staffIds = OrganizationUser::where('organization_id', $learner->organization_id)
            ->where('status', 'active')
            ->whereIn('role', self::SCHOOL_STAFF_ROLES)
            ->pluck('user_id');

        return User::query()
            ->whereIn('id', $staffIds)
            ->when($learner->user_id !== null, fn ($query) => $query->where('id', '!=', $learner->user_id))
            ->where('status', 'active')
            ->role(self::SCHOOL_STAFF_ROLES)
            ->when($search !== '', fn ($q) => $q->where(fn ($q2) => $q2->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->limit(20)
            ->get()
            ->map(fn (User $user) => $this->present($user, 'school', (string) $user->getRoleNames()->first()));
    }

    /** @return array{id:int,name:string,email:string,relation:string,role:string} */
    private function present(User $user, string $relation, string $role): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'relation' => $relation,
            'role' => $role,
        ];
    }
}

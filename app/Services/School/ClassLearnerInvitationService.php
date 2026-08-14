<?php

namespace App\Services\School;

use App\Models\ClassEnrollment;
use App\Models\ClassLearnerInvitation;
use App\Models\LearnerProfile;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClassLearnerInvitationService
{
    public function __construct(private ClassCourseEnrollmentService $courseEnrollments) {}

    public function findByToken(string $plainToken): ClassLearnerInvitation
    {
        return ClassLearnerInvitation::with(['schoolClass', 'organization'])
            ->where('token_hash', hash('sha256', $plainToken))
            ->firstOrFail();
    }

    public function accept(string $plainToken, User $user): ClassLearnerInvitation
    {
        $invitation = $this->findByToken($plainToken);

        if ($invitation->accepted_at !== null) {
            abort_unless((int) $invitation->accepted_by_user_id === (int) $user->id, 409, 'This invitation has already been used.');

            return $invitation;
        }

        abort_if($invitation->expires_at->isPast(), 410, 'This invitation has expired.');

        if (strcasecmp($invitation->email, $user->email) !== 0) {
            throw ValidationException::withMessages([
                'email' => 'Sign in or register with the email address that received this invitation.',
            ]);
        }

        return DB::transaction(function () use ($invitation, $user) {
            $profile = LearnerProfile::where('user_id', $user->id)->first();
            if ($profile && $profile->organization_id !== null && (int) $profile->organization_id !== (int) $invitation->organization_id) {
                abort(409, 'This learner profile already belongs to another school.');
            }

            $fillsSeat = ! $profile || $profile->organization_id === null;
            if (! $profile) {
                $profile = LearnerProfile::create([
                    'user_id' => $user->id,
                    'organization_id' => $invitation->organization_id,
                    'display_name' => $invitation->name,
                ]);
            } else {
                $profile->update([
                    'organization_id' => $invitation->organization_id,
                    'display_name' => $profile->display_name ?: $invitation->name,
                ]);
            }

            $user->assignRole('student');
            OrganizationUser::updateOrCreate(
                ['organization_id' => $invitation->organization_id, 'user_id' => $user->id],
                ['role' => 'student', 'status' => 'active'],
            );
            ClassEnrollment::firstOrCreate([
                'school_class_id' => $invitation->school_class_id,
                'learner_profile_id' => $profile->id,
            ]);
            if ($fillsSeat && $allocation = $invitation->organization->seatAllocations()->latest()->first()) {
                $allocation->increment('active_filled');
            }
            $this->courseEnrollments->syncLearner($invitation->schoolClass, $profile);

            $invitation->update([
                'accepted_by_user_id' => $user->id,
                'accepted_at' => now(),
            ]);

            return $invitation->fresh(['schoolClass', 'organization']);
        });
    }
}

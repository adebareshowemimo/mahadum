<?php

namespace App\Services\School;

use App\Models\OrganizationUser;
use App\Models\TeacherInvitation;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class TeacherInvitationService
{
    public function find(string $token): TeacherInvitation
    {
        abort_unless(strlen($token) === 64, 404);

        return TeacherInvitation::with('organization')->where('token_hash', hash('sha256', $token))->firstOrFail();
    }

    public function accept(string $token, User $user): TeacherInvitation
    {
        $invitation = $this->find($token);

        return DB::transaction(function () use ($invitation, $user) {
            $invitation = TeacherInvitation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->status === 'active' && $user->hasVerifiedEmail(), 403, 'Verify your email before accepting a teaching invitation.');
            abort_unless(strcasecmp($invitation->email, $user->email) === 0, 403, 'Use the email address that received this invitation.');
            if ($invitation->accepted_at) {
                abort_unless((int) $invitation->accepted_by_user_id === (int) $user->id, 409);

                return $invitation->load('organization');
            }
            abort_if($invitation->revoked_at || $invitation->expires_at->isPast(), 410, 'This invitation is no longer available.');
            abort_unless($invitation->organization->status === 'active', 409, 'This school is not active.');
            // The invitation issuer must still have authority when it is accepted.
            $issuer = User::findOrFail($invitation->invited_by_user_id);
            abort_unless($issuer->status === 'active' && $issuer->can('schools.classes.manage') && ($issuer->hasRole('super_admin') || ($issuer->hasRole('school_admin')
                && OrganizationUser::where('organization_id', $invitation->organization_id)->where('user_id', $issuer->id)
                    ->where('role', 'school_admin')->where('status', 'active')->exists())), 403, 'The school must issue a new invitation.');
            $membership = OrganizationUser::where('organization_id', $invitation->organization_id)->where('user_id', $user->id)->lockForUpdate()->first();
            abort_if($membership && $membership->status !== 'active', 409, 'Ask the school to reactivate your membership first.');
            abort_if($membership && $membership->role !== 'teacher', 409, 'The school must review your existing membership before adding teaching access.');
            OrganizationUser::firstOrCreate(['organization_id' => $invitation->organization_id, 'user_id' => $user->id], ['role' => 'teacher', 'status' => 'active']);
            $user->assignRole('teacher'); // accepted, verified, explicitly issued school grant
            $invitation->update(['accepted_by_user_id' => $user->id, 'accepted_at' => now()]);
            app(AuditLogger::class)->record('school.teacher_invitation_accepted', $invitation, [], ['user_id' => $user->id, 'role' => 'teacher'], $invitation->organization_id);

            return $invitation->load('organization');
        });
    }
}

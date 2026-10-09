<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\LearnerProfile;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\TeacherInvitation;
use App\Services\AuditLogger;
use App\Services\School\TeacherCsvImporter;
use App\Services\School\TeacherInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SchoolDirectoryController extends Controller
{
    use ResolvesOrganization;

    private function authorizeDirectory(Request $request, Organization $organization): void
    {
        abort_unless($request->user()->status === 'active', 403);
        $this->authorizeOrg($request->user(), $organization);
        abort_unless($request->user()->hasRole('super_admin') || ($request->user()->hasRole('school_admin')
            && OrganizationUser::where('organization_id', $organization->id)->where('user_id', $request->user()->id)
                ->where('role', 'school_admin')->where('status', 'active')->exists()), 403);
    }

    public function students(Request $request, Organization $organization): JsonResponse
    {
        $this->authorizeDirectory($request, $organization);
        $rows = LearnerProfile::where('organization_id', $organization->id)->with(['user:id,email',
            'classEnrollments' => fn ($query) => $query->whereHas('schoolClass', fn ($class) => $class->where('organization_id', $organization->id))->with('schoolClass'),
        ])
            ->orderBy('display_name')->get()->map(fn ($learner) => [
                'id' => $learner->id, 'name' => $learner->display_name, 'email' => $learner->user?->email,
                'level' => $learner->roster_level_position !== null ? 'L'.$learner->roster_level_position : null,
                'classes' => $learner->classEnrollments->unique('school_class_id')->map(fn ($entry) => ['id' => $entry->schoolClass->id, 'name' => $entry->schoolClass->name])->values(),
            ]);

        return response()->json(['data' => $rows]);
    }

    public function teachers(Request $request, Organization $organization): JsonResponse
    {
        $this->authorizeDirectory($request, $organization);
        $rows = OrganizationUser::where('organization_id', $organization->id)->where('role', 'teacher')->with('user')->get()
            ->map(fn ($membership) => ['id' => $membership->user_id, 'name' => $membership->user->name,
                'email' => $membership->user->email, 'status' => $membership->status, 'account_status' => $membership->user->status]);
        $pending = TeacherInvitation::where('organization_id', $organization->id)->whereNull('accepted_at')->whereNull('revoked_at')->latest()->get()
            ->map(fn ($invitation) => ['id' => $invitation->id, 'name' => $invitation->name, 'email' => $invitation->email,
                'status' => $invitation->expires_at->isPast() ? 'expired' : 'pending', 'expires_at' => $invitation->expires_at]);

        return response()->json(['data' => ['teachers' => $rows, 'invitations' => $pending]]);
    }

    public function invite(Request $request, Organization $organization): JsonResponse
    {
        $this->authorizeDirectory($request, $organization);
        abort_unless($organization->status === 'active', 409, 'Activate the school before inviting teachers.');
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255']]);
        $invitation = app(TeacherInvitationService::class)->issue($organization, $request->user(), $data['name'], $data['email']);

        return response()->json(['data' => ['id' => $invitation->id, 'email' => $invitation->email,
            'delivery_status' => in_array(config('mail.default'), ['log', 'array'], true) ? 'not_configured' : 'queued']], 201);
    }

    public function importTeachers(Request $request, Organization $organization, TeacherCsvImporter $importer): JsonResponse
    {
        $this->authorizeDirectory($request, $organization);
        abort_unless($organization->status === 'active', 409, 'Activate the school before inviting teachers.');
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'], 'preview' => ['required', 'boolean']]);

        return response()->json(['data' => $importer->run($organization, $request->user(), $request->file('file'), $request->boolean('preview'))]);
    }

    public function revoke(Request $request, Organization $organization, TeacherInvitation $invitation): JsonResponse
    {
        $this->authorizeDirectory($request, $organization);
        abort_unless((int) $invitation->organization_id === (int) $organization->id, 404);
        DB::transaction(function () use ($invitation) {
            $locked = TeacherInvitation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->accepted_at !== null, 409, 'This invitation has already been accepted.');
            $locked->update(['revoked_at' => now()]);
        });
        app(AuditLogger::class)->record('school.teacher_invitation_revoked', $invitation, [], ['revoked' => true], $organization->id);

        return response()->json(['data' => ['status' => 'revoked']]);
    }

    public function showInvitation(string $token, TeacherInvitationService $service): JsonResponse
    {
        $invitation = $service->find($token);

        return response()->json(['data' => ['name' => $invitation->name, 'email' => $invitation->email,
            'organization_name' => $invitation->organization->name, 'expires_at' => $invitation->expires_at,
            'status' => $invitation->accepted_at ? 'accepted' : ($invitation->revoked_at ? 'revoked' : ($invitation->expires_at->isPast() ? 'expired' : 'pending'))]]);
    }

    public function acceptInvitation(Request $request, string $token, TeacherInvitationService $service): JsonResponse
    {
        $invitation = $service->accept($token, $request->user());

        return response()->json(['data' => ['organization_id' => $invitation->organization_id, 'status' => 'accepted']]);
    }
}

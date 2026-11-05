<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\Family;
use App\Models\LearnerProfile;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\SchoolJoinLink;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SchoolJoinLinkController extends Controller
{
    use ResolvesOrganization;

    public function show(Request $request, Organization $organization): JsonResponse
    {
        $this->authorizeManager($request, $organization);
        $link = SchoolJoinLink::where('organization_id', $organization->id)->first();

        return response()->json(['data' => $link ? $this->managedData($link) : null]);
    }

    public function rotate(Request $request, Organization $organization, AuditLogger $audit): JsonResponse
    {
        $this->authorizeManager($request, $organization);
        abort_unless($organization->type === 'school' && $organization->status === 'active', 422, 'School must be active.');
        $token = Str::random(48);
        $link = DB::transaction(function () use ($organization, $request, $token) {
            Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();

            return SchoolJoinLink::updateOrCreate(['organization_id' => $organization->id], [
                'token_hash' => hash('sha256', $token),
                'token_ciphertext' => $token,
                'expires_at' => now()->addDays(30),
                'revoked_at' => null,
                'created_by_user_id' => $request->user()->id,
            ]);
        });
        $audit->record('school.join_link_rotated', $link, [], ['expires_at' => $link->expires_at], $organization->id);

        return response()->json(['data' => $this->managedData($link)]);
    }

    public function revoke(Request $request, Organization $organization, AuditLogger $audit): JsonResponse
    {
        $this->authorizeManager($request, $organization);
        $link = SchoolJoinLink::where('organization_id', $organization->id)->firstOrFail();
        $link->update(['revoked_at' => now()]);
        $audit->record('school.join_link_revoked', $link, [], ['revoked_at' => $link->revoked_at], $organization->id);

        return response()->json(['data' => $this->managedData($link)]);
    }

    public function preview(string $token): JsonResponse
    {
        $link = $this->validLink($token);

        return response()->json(['data' => ['school' => $link->organization->name, 'expires_at' => $link->expires_at]]);
    }

    public function join(Request $request, string $token, AuditLogger $audit): JsonResponse
    {
        $values = $request->validate(['learner_id' => ['nullable', 'integer']]);
        $link = $this->validLink($token);
        $user = $request->user();
        abort_unless($user->status === 'active', 403, 'Your account is not active.');
        abort_unless($user->hasAnyRole(['parent', 'student']), 403, 'Only learners and parents can join a school.');

        $result = DB::transaction(function () use ($link, $user, $values, $token) {
            $school = Organization::whereKey($link->organization_id)->lockForUpdate()->firstOrFail();
            $link = SchoolJoinLink::whereKey($link->id)->lockForUpdate()->firstOrFail();
            abort_if(! hash_equals($link->token_hash, hash('sha256', $token)) || $link->revoked_at !== null || $link->expires_at->isPast() || $school->status !== 'active', 410, 'This school join link is no longer available.');
            if ($user->hasRole('parent')) {
                abort_unless(isset($values['learner_id']), 422, 'Choose the child who will join this school.');
                $familyId = Family::where('owner_user_id', $user->id)->value('id');
                abort_unless($familyId !== null, 403, 'Set up your family before joining a school.');
                $learner = LearnerProfile::where('family_id', $familyId)
                    ->whereNull('user_id')->lockForUpdate()->findOrFail($values['learner_id']);
            } else {
                abort_if(isset($values['learner_id']), 422, 'Your account joins with its own learner profile.');
                $learner = LearnerProfile::where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            }
            abort_if($learner->organization_id !== null && (int) $learner->organization_id !== (int) $school->id,
                409, 'This learner already belongs to another school.');
            if ($learner->organization_id === null) {
                $allocation = $school->seatAllocations()->where('expires_at', '>', now())
                    ->whereColumn('active_filled', '<', 'total_purchased')->lockForUpdate()->first();
                abort_unless($allocation !== null, 422, 'This school has no available learner seats. Ask the school administrator.');
                $learner->update(['organization_id' => $school->id]);
                $allocation->increment('active_filled');
            }
            if ($learner->user_id) {
                OrganizationUser::updateOrCreate(
                    ['organization_id' => $school->id, 'user_id' => $user->id],
                    ['role' => 'student', 'status' => 'active'],
                );
            }

            return ['learner_id' => $learner->id, 'school' => $school->name, 'organization_id' => $school->id];
        });
        $audit->record('school.joined_by_link', $user, [], $result, $result['organization_id']);

        return response()->json(['data' => $result]);
    }

    private function validLink(string $token): SchoolJoinLink
    {
        $link = SchoolJoinLink::with('organization')->where('token_hash', hash('sha256', $token))->firstOrFail();
        abort_if($link->revoked_at !== null || $link->expires_at->isPast() || $link->organization->status !== 'active',
            410, 'This school join link is no longer available.');

        return $link;
    }

    private function authorizeManager(Request $request, Organization $organization): void
    {
        $this->authorizeOrg($request->user(), $organization);
        abort_unless($request->user()->status === 'active'
            && $request->user()->hasAnyRole(['school_admin', 'super_admin']), 403);
    }

    private function managedData(SchoolJoinLink $link): array
    {
        $active = $link->revoked_at === null && $link->expires_at->isFuture();

        return [
            'active' => $active,
            'expires_at' => $link->expires_at,
            'url' => $active ? rtrim(config('app.frontend_url', config('app.url')), '/').'/school-join/'.$link->token_ciphertext : null,
        ];
    }
}

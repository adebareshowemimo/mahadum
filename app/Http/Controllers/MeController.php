<?php

namespace App\Http\Controllers;

use App\Http\Resources\FamilyResource;
use App\Http\Resources\LearnerProfileResource;
use App\Models\LearnerProfile;
use App\Models\OrganizationUser;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Billing\EntitlementResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function show(Request $request, EntitlementResolver $entitlements): JsonResponse
    {
        $user = $request->user()->load([
            'ownedFamilies.learnerProfiles.targetLanguage',
            'ownedFamilies.learnerProfiles.profilePhoto',
            'learnerProfile.targetLanguage',
            'learnerProfile.profilePhoto',
        ]);

        // Active personal subscription (drives premium entitlements). School
        // tooling is gated separately by role + org membership.
        $subscription = Subscription::with('plan')
            ->where('subscriber_type', User::class)
            ->where('subscriber_id', $user->id)
            ->whereIn('status', ['active', 'grace'])
            ->latest()
            ->first();

        return response()->json(['data' => [
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified' => $user->hasVerifiedEmail(),
                'roles' => $user->getRoleNames(),
            ],
            'families' => FamilyResource::collection($user->ownedFamilies),
            'learner_profiles' => $user->learnerProfile
                ? [new LearnerProfileResource($user->learnerProfile)]
                : [],
            'organizations' => OrganizationUser::where('user_id', $user->id)->with('organization')->get()->map(fn ($m) => [
                'id' => $m->organization_id,
                'name' => $m->organization?->name,
                'role' => $m->role,
            ])->values(),
            'active_organization_id' => app()->bound('currentTenantId') ? app('currentTenantId') : null,
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'method' => $subscription->method,
                'plan_code' => $subscription->plan->code,
                'plan_name' => $subscription->plan->name,
                'renews_at' => $subscription->renews_at,
            ] : null,
            'entitlements' => $entitlements->fromPlan($subscription?->plan),
        ]]);
    }

    /**
     * Create (or return) the signed-in adult's own learner identity. Parents
     * use this to learn alongside their children without impersonating a child.
     */
    public function ensureLearnerProfile(Request $request, AuditLogger $audit): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['parent', 'student']), 403, 'This account cannot create a personal learner profile.');

        $learner = LearnerProfile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'display_name' => $user->name,
                'date_of_birth' => $user->date_of_birth,
                'age_band' => 'adult',
            ],
        );

        if ($learner->wasRecentlyCreated) {
            $audit->record('learner.self_profile_created', $learner, [], [
                'user_id' => $user->id,
                'display_name' => $learner->display_name,
            ]);
        }

        return (new LearnerProfileResource($learner->load(['targetLanguage', 'profilePhoto'])))
            ->response()
            ->setStatusCode($learner->wasRecentlyCreated ? 201 : 200);
    }
}

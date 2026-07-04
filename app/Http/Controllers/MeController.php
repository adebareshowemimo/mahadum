<?php

namespace App\Http\Controllers;

use App\Http\Resources\FamilyResource;
use App\Models\OrganizationUser;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load([
            'ownedFamilies.learnerProfiles.targetLanguage',
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
            'entitlements' => $this->entitlements($subscription?->plan),
        ]]);
    }

    /**
     * Capability flags derived from the active plan (or Free defaults). Mirrors
     * the approved subscription matrix: family features live in the Family tier;
     * Free is full-learning + ads.
     */
    private function entitlements(?Plan $plan): array
    {
        // Free (no active plan): full learning, ads on, single profile.
        if ($plan === null) {
            return [
                'tier' => 'free',
                'tier_name' => 'Free',
                'ads' => true,
                'offline_download' => false,
                'unlimited_hearts' => false,
                'family_dashboard' => false,
                'teacher_analytics' => false,
                'max_profiles' => 1,
            ];
        }

        $features = $plan->features ?? [];

        return [
            'tier' => $plan->code,
            'tier_name' => $plan->name,
            // Any paid plan removes ads unless its feature map says otherwise.
            'ads' => (bool) ($features['ads'] ?? false),
            'offline_download' => (bool) ($features['offline_download'] ?? false),
            'unlimited_hearts' => (bool) ($features['unlimited_hearts'] ?? false),
            'family_dashboard' => (bool) ($features['family_dashboard'] ?? false),
            'teacher_analytics' => (bool) ($features['teacher_analytics'] ?? false),
            'max_profiles' => $plan->max_profiles ?? 1,
        ];
    }
}

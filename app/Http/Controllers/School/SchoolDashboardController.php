<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\LearnerProfile;
use App\Models\Organization;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolDashboardController extends Controller
{
    use ResolvesOrganization;

    public function show(Request $request, Organization $organization): JsonResponse
    {
        $this->authorizeOrg($request->user(), $organization);

        $allocations = $organization->seatAllocations()->get();
        $studentIds = LearnerProfile::where('organization_id', $organization->id)->pluck('id');

        $subscription = Subscription::where('subscriber_type', Organization::class)
            ->where('subscriber_id', $organization->id)
            ->latest('started_at')
            ->first();

        $lastPaidAt = $organization->invoices()->where('status', 'paid')->latest('paid_at')->value('paid_at');

        return response()->json(['data' => [
            'organization' => ['id' => $organization->id, 'name' => $organization->name, 'status' => $organization->status],
            'classes' => $organization->schoolClasses()->count(),
            'students' => $studentIds->count(),
            'seats' => [
                'purchased' => (int) $allocations->sum('total_purchased'),
                'filled' => (int) $allocations->sum('active_filled'),
            ],
            'invoices' => [
                'unpaid' => $organization->invoices()->where('status', 'unpaid')->count(),
                'unpaid_minor' => (int) $organization->invoices()->where('status', 'unpaid')->sum('amount_minor'),
            ],
            'subscription' => [
                'status' => $subscription?->status,
                'last_payment_at' => $lastPaidAt,
            ],
        ]]);
    }
}

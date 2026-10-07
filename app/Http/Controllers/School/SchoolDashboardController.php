<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\LearnerProfile;
use App\Models\Organization;
use App\Models\Subscription;
use App\Services\Learning\LearningAnalytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolDashboardController extends Controller
{
    use ResolvesOrganization;

    public function show(Request $request, Organization $organization, LearningAnalytics $analytics): JsonResponse
    {
        $this->authorizeOrg($request->user(), $organization);

        $allocations = $organization->seatAllocations()->get();
        $studentIds = LearnerProfile::where('organization_id', $organization->id)->pluck('id');

        $subscription = Subscription::where('subscriber_type', Organization::class)
            ->where('subscriber_id', $organization->id)
            ->latest('started_at')
            ->first();

        $lastPaidAt = $organization->invoices()->where('status', 'paid')->latest('paid_at')->value('paid_at');
        $learners = LearnerProfile::whereIn('id', $studentIds)->get(['id', 'display_name']);
        $byLearner = $analytics->byLearner($studentIds);
        $rank = fn ($rows) => $rows->filter(fn ($row) => $row['lesson_targets'] > 0 || $row['quiz_scored'] > 0)
            ->sortBy([['completion_rate', 'desc'], ['avg_quiz_score', 'desc'], ['id', 'asc']])->take(5)->values();
        $topStudents = $rank($learners->map(fn ($learner) => [
            'id' => $learner->id, 'name' => $learner->display_name,
            ...$analytics->summarize(collect([$byLearner[$learner->id]])),
        ]));
        $topClasses = $rank($organization->schoolClasses()->with('enrollments')->get()->map(fn ($class) => [
            'id' => $class->id, 'name' => $class->name,
            ...$analytics->summarize($byLearner->only($class->enrollments->pluck('learner_profile_id')->all())),
        ]));

        return response()->json(['data' => [
            'organization' => ['id' => $organization->id, 'name' => $organization->name, 'status' => $organization->status],
            'classes' => $organization->schoolClasses()->count(),
            'students' => $studentIds->count(),
            'learning' => [...$analytics->summarize($byLearner), 'top_students' => $topStudents, 'top_classes' => $topClasses],
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

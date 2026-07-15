<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\AwardClassBadgeRequest;
use App\Models\Badge;
use App\Models\LearnerBadge;
use App\Models\LearnerProfile;
use App\Models\SchoolClass;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;

/**
 * Manual badge awarding — a teacher recognizing a student in their own class,
 * alongside BadgeService's automatic conditions (streaks, first lesson, etc).
 * Idempotent, same as BadgeService: awarding an already-earned badge is a no-op.
 */
class ClassBadgeController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function award(AwardClassBadgeRequest $request, SchoolClass $class, LearnerProfile $learner): JsonResponse
    {
        abort_unless($class->teacher_user_id === $request->user()->id, 403, 'Only this class\'s teacher can award badges.');

        $enrolled = $class->enrollments()->where('learner_profile_id', $learner->id)->exists();
        abort_unless($enrolled, 403, 'This learner is not enrolled in that class.');

        $badge = Badge::where('code', $request->string('badge_code'))->firstOrFail();

        $existing = LearnerBadge::where('learner_profile_id', $learner->id)->where('badge_id', $badge->id)->first();

        $learnerBadge = $existing ?? LearnerBadge::create([
            'learner_profile_id' => $learner->id,
            'badge_id' => $badge->id,
            'earned_at' => now(),
        ]);

        if (! $existing) {
            $this->audit->record(
                'badge.awarded_manually',
                $learnerBadge,
                [],
                ['learner_id' => $learner->id, 'badge_code' => $badge->code, 'class_id' => $class->id],
                $class->organization_id,
            );
        }

        return response()->json(['data' => [
            'code' => $badge->code,
            'name' => $badge->name,
            'earned_at' => $learnerBadge->earned_at,
            'already_earned' => (bool) $existing,
        ]], $existing ? 200 : 201);
    }
}

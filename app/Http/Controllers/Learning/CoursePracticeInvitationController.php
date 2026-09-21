<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Concerns\ResolvesLearner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\StoreCoursePracticeInvitationRequest;
use App\Models\CoursePracticeInvitation;
use App\Models\LearnerPathNode;
use App\Models\LearnerProfile;
use App\Models\Lesson;
use App\Models\User;
use App\Notifications\CoursePracticeInvitationNotification;
use App\Services\AuditLogger;
use App\Services\Learning\LessonAccess;
use App\Services\Learning\PracticeRecipientEligibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CoursePracticeInvitationController extends Controller
{
    use ResolvesLearner;

    public function store(
        StoreCoursePracticeInvitationRequest $request,
        AuditLogger $audit,
        LessonAccess $access,
        PracticeRecipientEligibility $eligibility,
    ): JsonResponse {
        $learner = $this->learner($request->integer('learner_id'));
        Gate::authorize('update', $learner);

        $lesson = $this->currentLesson($learner, $access);
        abort_if($lesson === null, 422, 'This learner has no active lesson to practice yet.');

        $recipient = User::findOrFail($request->integer('recipient_user_id'));
        abort_unless($eligibility->isEligible($learner, $request->user(), $recipient), 422, 'Invite someone from this learner’s family or school.');

        $plainToken = Str::random(64);
        $invitation = CoursePracticeInvitation::create([
            'learner_profile_id' => $learner->id,
            'lesson_id' => $lesson->id,
            'inviter_user_id' => $request->user()->id,
            'recipient_user_id' => $recipient->id,
            'token_hash' => hash('sha256', $plainToken),
            'channel' => 'email',
            'expires_at' => now()->addHours(48),
        ]);
        $invitation->setRelation('inviter', $request->user());
        $invitation->setRelation('lesson', $lesson);
        $recipient->notify(new CoursePracticeInvitationNotification($invitation, $plainToken));
        $audit->record('course_practice.invited', $invitation, [], [
            'lesson_id' => $lesson->id,
            'recipient_user_id' => $recipient->id,
            'expires_at' => $invitation->expires_at->toISOString(),
        ], $learner->organization_id);

        return response()->json(['data' => [
            'sent' => true,
            'expires_at' => $invitation->expires_at->toISOString(),
        ]], 201);
    }

    public function show(Request $request, string $token): JsonResponse
    {
        $invitation = $this->resolveForRecipient($request, $token);
        if ($invitation->opened_at === null) {
            $invitation->update(['opened_at' => now()]);
        }

        return response()->json(['data' => $this->safePayload($invitation)]);
    }

    public function accept(Request $request, string $token, AuditLogger $audit): JsonResponse
    {
        $invitation = $this->resolveForRecipient($request, $token);
        if ($invitation->accepted_at === null) {
            $acceptedAt = now();
            $invitation->update(['accepted_at' => $acceptedAt, 'opened_at' => $invitation->opened_at ?? $acceptedAt]);
            $audit->record('course_practice.accepted', $invitation, [], ['accepted_at' => $acceptedAt->toISOString()]);
        }

        return response()->json(['data' => $this->safePayload($invitation->fresh())]);
    }

    /** First unlocked, not-yet-completed lesson in the learner's path — same shape as PathController::show(). */
    private function currentLesson(LearnerProfile $learner, LessonAccess $access): ?Lesson
    {
        $tier = $access->tier($learner);
        $nodes = LearnerPathNode::whereHas('enrollment', fn ($q) => $q->where('learner_profile_id', $learner->id))
            ->whereHas('lesson.courseLevel')
            ->with(['lesson.courseLevel'])
            ->orderBy('position')
            ->get();

        foreach ($nodes as $node) {
            if ($node->state === 'completed') {
                continue;
            }

            $permission = $access->content($learner, $node->lesson, $tier);
            if ($permission['allowed']) {
                return $node->lesson;
            }
        }

        return null;
    }

    private function resolveForRecipient(Request $request, string $token): CoursePracticeInvitation
    {
        $invitation = CoursePracticeInvitation::with(['lesson.courseLevel.course', 'inviter'])
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();
        abort_unless((int) $invitation->recipient_user_id === (int) $request->user()->id, 403, 'This invitation belongs to another account.');
        abort_if($invitation->expires_at->isPast(), 410, 'This invitation has expired.');

        return $invitation;
    }

    /** @return array<string, mixed> */
    private function safePayload(CoursePracticeInvitation $invitation): array
    {
        return [
            'inviter_name' => $invitation->inviter->name,
            'lesson_id' => $invitation->lesson_id,
            'lesson_title' => $invitation->lesson->title,
            'course_title' => $invitation->lesson->courseLevel->course->title ?? null,
            'expires_at' => $invitation->expires_at->toISOString(),
            'accepted' => $invitation->accepted_at !== null,
        ];
    }
}

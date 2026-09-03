<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Concerns\ResolvesLearner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\StoreTonePracticeInvitationRequest;
use App\Models\LearnerProfile;
use App\Models\LessonComponent;
use App\Models\OrganizationUser;
use App\Models\TonePracticeInvitation;
use App\Models\User;
use App\Notifications\TonePracticeInvitationNotification;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class TonePracticeInvitationController extends Controller
{
    use ResolvesLearner;

    public function store(StoreTonePracticeInvitationRequest $request, AuditLogger $audit): JsonResponse
    {
        $learner = $this->learner($request->integer('learner_id'));
        Gate::authorize('update', $learner);

        $component = LessonComponent::with(['lesson', 'speakingPrompt'])->findOrFail($request->integer('component_id'));
        abort_unless($component->type === 'speaking' && $component->speakingPrompt !== null, 422, 'Choose a speaking activity.');
        abort_if($component->lesson->published_at === null, 422, 'This activity is not published.');

        $recipient = User::whereRaw('LOWER(email) = ?', [Str::lower($request->string('recipient_email')->value())])->firstOrFail();
        abort_unless($recipient->status === 'active', 422, 'The recipient account is not active.');
        abort_unless($recipient->hasAnyRole(['parent', 'teacher', 'supervisor', 'school_admin']), 422, 'Invite a registered parent, guardian, or teacher.');
        $this->authorizeTeacherRecipient($learner, $recipient);

        $plainToken = Str::random(64);
        $invitation = TonePracticeInvitation::create([
            'learner_profile_id' => $learner->id,
            'lesson_component_id' => $component->id,
            'inviter_user_id' => $request->user()->id,
            'recipient_user_id' => $recipient->id,
            'token_hash' => hash('sha256', $plainToken),
            'channel' => 'email',
            'expires_at' => now()->addHours(48),
        ]);
        $invitation->setRelation('inviter', $request->user());
        $recipient->notify(new TonePracticeInvitationNotification($invitation, $plainToken));
        $audit->record('tone_practice.invited', $invitation, [], ['expires_at' => $invitation->expires_at->toISOString()], $learner->organization_id);

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
            $audit->record('tone_practice.accepted', $invitation, [], ['accepted_at' => $acceptedAt->toISOString()]);
        }

        return response()->json(['data' => $this->safePayload($invitation->fresh())]);
    }

    private function resolveForRecipient(Request $request, string $token): TonePracticeInvitation
    {
        $invitation = TonePracticeInvitation::with(['component.lesson', 'component.speakingPrompt', 'inviter'])
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();
        abort_unless((int) $invitation->recipient_user_id === (int) $request->user()->id, 403, 'This invitation belongs to another account.');
        abort_if($invitation->expires_at->isPast(), 410, 'This invitation has expired.');

        return $invitation;
    }

    /** @return array<string, mixed> */
    private function safePayload(TonePracticeInvitation $invitation): array
    {
        return [
            'inviter_name' => $invitation->inviter->name,
            'lesson_title' => $invitation->component->lesson->title,
            'practice_text' => $invitation->component->speakingPrompt?->target_text
                ?: $invitation->component->speakingPrompt?->prompt_text,
            'expires_at' => $invitation->expires_at->toISOString(),
            'accepted' => $invitation->accepted_at !== null,
        ];
    }

    private function authorizeTeacherRecipient(LearnerProfile $learner, User $recipient): void
    {
        if ($recipient->hasRole('parent')) {
            return;
        }

        abort_if($learner->organization_id === null, 422, 'A teacher invitation requires a school learner.');
        $sharesOrganization = OrganizationUser::where('user_id', $recipient->id)
            ->where('organization_id', $learner->organization_id)
            ->where('status', 'active')
            ->exists();
        abort_unless($sharesOrganization, 422, 'The teacher must belong to the learner’s school.');
    }
}

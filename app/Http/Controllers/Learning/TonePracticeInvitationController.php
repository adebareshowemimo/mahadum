<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Concerns\ResolvesLearner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\StoreTonePracticeInvitationRequest;
use App\Models\LessonComponent;
use App\Models\TonePracticeInvitation;
use App\Models\User;
use App\Notifications\TonePracticeInvitationNotification;
use App\Services\AuditLogger;
use App\Services\Learning\LessonAccess;
use App\Services\Learning\PracticeRecipientEligibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TonePracticeInvitationController extends Controller
{
    use ResolvesLearner;

    public function store(StoreTonePracticeInvitationRequest $request, AuditLogger $audit): JsonResponse
    {
        $learner = $this->learner($request->integer('learner_id'));
        Gate::authorize('update', $learner);

        $component = LessonComponent::with(['lesson', 'speakingPrompt', 'video'])->findOrFail($request->integer('component_id'));
        abort_unless(($component->type === 'speaking' && $component->speakingPrompt !== null)
            || ($component->type === 'video' && $component->video !== null), 422, 'Choose a video or speaking activity.');
        abort_if($component->lesson->published_at === null, 422, 'This activity is not published.');

        app(LessonAccess::class)->authorize($learner, $component->lesson);

        $recipient = User::whereRaw('LOWER(email) = ?', [Str::lower(trim($request->string('recipient_email')->value()))])->first();
        abort_unless($recipient && app(PracticeRecipientEligibility::class)->isEligible($learner, $request->user(), $recipient), 422, 'Choose an active adult from this learner’s family or school practice contacts.');

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
        $invitation = TonePracticeInvitation::with(['component.lesson', 'component.speakingPrompt', 'component.video.sourceAsset', 'inviter'])
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();
        abort_unless((int) $invitation->recipient_user_id === (int) $request->user()->id, 403, 'This invitation belongs to another account.');
        abort_if($invitation->expires_at->isPast(), 410, 'This invitation has expired.');

        return $invitation;
    }

    /** @return array<string, mixed> */
    private function safePayload(TonePracticeInvitation $invitation): array
    {
        $video = $invitation->component->type === 'video' ? $invitation->component->video
            : $invitation->component->lesson->components()->where('type', 'video')
                ->where('position', '<', $invitation->component->position)->orderByDesc('position')
                ->with('video.sourceAsset')->first()?->video;
        $videoUrl = $video?->sourceAsset
            ? Storage::disk('public')->url($video->sourceAsset->url)
            : $video?->external_url;

        return [
            'video_url' => $videoUrl,
            'inviter_name' => $invitation->inviter->name,
            'lesson_title' => $invitation->component->lesson->title,
            'practice_text' => $invitation->component->speakingPrompt?->target_text
                ?: ($invitation->component->speakingPrompt?->prompt_text
                    ?: 'Watch the language video, then take turns repeating the words and matching their tones.'),
            'expires_at' => $invitation->expires_at->toISOString(),
            'accepted' => $invitation->accepted_at !== null,
        ];
    }
}

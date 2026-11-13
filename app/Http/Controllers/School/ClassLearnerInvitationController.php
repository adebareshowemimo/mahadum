<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\InviteClassLearnerRequest;
use App\Models\ClassLearnerInvitation;
use App\Models\SchoolClass;
use App\Models\User;
use App\Notifications\ClassLearnerInvited;
use App\Services\AuditLogger;
use App\Services\School\ClassLearnerInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class ClassLearnerInvitationController extends Controller
{
    public function __construct(
        private ClassLearnerInvitationService $invitations,
        private AuditLogger $audit,
    ) {}

    public function index(SchoolClass $class): JsonResponse
    {
        $invitations = ClassLearnerInvitation::where('school_class_id', $class->id)
            ->whereNull('accepted_at')->whereNull('cancelled_at')
            ->latest('created_at')->get()->map(fn (ClassLearnerInvitation $invitation) => [
                'id' => $invitation->id, 'name' => $invitation->name, 'email' => $invitation->email,
                'sent_at' => $invitation->created_at, 'expires_at' => $invitation->expires_at,
                'status' => $invitation->expires_at->isPast() ? 'expired' : 'pending',
            ]);

        return response()->json(['data' => $invitations]);
    }

    public function store(InviteClassLearnerRequest $request, SchoolClass $class): JsonResponse
    {
        $plainToken = Str::random(64);
        $email = Str::lower($request->string('email')->trim()->toString());

        ClassLearnerInvitation::where('school_class_id', $class->id)
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->whereNull('cancelled_at')->update(['cancelled_at' => now()]);

        $invitation = ClassLearnerInvitation::create([
            'school_class_id' => $class->id,
            'organization_id' => $class->organization_id,
            'invited_by_user_id' => $request->user()->id,
            'name' => $request->string('name')->trim()->toString(),
            'email' => $email,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(7),
        ]);

        Notification::route('mail', $email)->notify(new ClassLearnerInvited($invitation, $plainToken));

        $this->audit->record(
            'class.learner_invited',
            $invitation,
            [],
            ['class_id' => $class->id, 'email' => $email],
            $class->organization_id,
        );

        return response()->json(['data' => [
            'id' => $invitation->id,
            'name' => $invitation->name,
            'email' => $invitation->email,
            'expires_at' => $invitation->expires_at,
            'delivery_status' => in_array(config('mail.default'), ['log', 'array'], true) ? 'not_configured' : 'queued',
        ]], 201);
    }

    public function resend(Request $request, SchoolClass $class, ClassLearnerInvitation $invitation): JsonResponse
    {
        abort_unless($invitation->school_class_id === $class->id, 404);
        abort_if($invitation->accepted_at !== null || $invitation->cancelled_at !== null, 422,
            'Only a pending invitation can be resent.');
        $old = $invitation->only(['id', 'email', 'expires_at']);
        $invitation->update(['cancelled_at' => now()]);
        $plainToken = Str::random(64);
        $replacement = ClassLearnerInvitation::create([
            'school_class_id' => $class->id, 'organization_id' => $class->organization_id,
            'invited_by_user_id' => $request->user()->id, 'name' => $invitation->name,
            'email' => $invitation->email, 'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(7),
        ]);
        Notification::route('mail', $replacement->email)->notify(new ClassLearnerInvited($replacement, $plainToken));
        $this->audit->record('class.learner_invitation_resent', $replacement, $old,
            ['id' => $replacement->id, 'email' => $replacement->email], $class->organization_id);

        return response()->json(['data' => ['id' => $replacement->id, 'name' => $replacement->name,
            'email' => $replacement->email, 'sent_at' => $replacement->created_at,
            'expires_at' => $replacement->expires_at, 'status' => 'pending']]);
    }

    public function cancel(SchoolClass $class, ClassLearnerInvitation $invitation): JsonResponse
    {
        abort_unless($invitation->school_class_id === $class->id, 404);
        abort_if($invitation->accepted_at !== null, 422, 'An accepted invitation cannot be cancelled.');
        if ($invitation->cancelled_at === null) {
            $invitation->update(['cancelled_at' => now()]);
            $this->audit->record('class.learner_invitation_cancelled', $invitation, ['cancelled_at' => null],
                ['cancelled_at' => $invitation->cancelled_at], $class->organization_id);
        }

        return response()->json(['data' => ['status' => 'cancelled']]);
    }

    public function show(string $token): JsonResponse
    {
        $invitation = $this->invitations->findByToken($token);
        $existing = User::where('email', $invitation->email)->first(['first_name', 'last_name']);

        return response()->json(['data' => [
            'name' => $invitation->name,
            'email' => $invitation->email,
            'class_name' => $invitation->schoolClass->name,
            'organization_name' => $invitation->organization->name,
            'expires_at' => $invitation->expires_at,
            'status' => $invitation->accepted_at ? 'accepted' : ($invitation->expires_at->isPast() ? 'expired' : 'pending'),
            'existing_user' => $existing !== null,
            'existing_account_name' => $existing?->name,
        ]]);
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        $invitation = $this->invitations->accept($token, $request->user());

        return response()->json(['data' => [
            'class_id' => $invitation->school_class_id,
            'class_name' => $invitation->schoolClass->name,
            'organization_name' => $invitation->organization->name,
            'status' => 'accepted',
        ]]);
    }
}

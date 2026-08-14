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

    public function store(InviteClassLearnerRequest $request, SchoolClass $class): JsonResponse
    {
        $plainToken = Str::random(64);
        $email = Str::lower($request->string('email')->trim()->toString());

        ClassLearnerInvitation::where('school_class_id', $class->id)
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->delete();

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

    public function show(string $token): JsonResponse
    {
        $invitation = $this->invitations->findByToken($token);

        return response()->json(['data' => [
            'name' => $invitation->name,
            'email' => $invitation->email,
            'class_name' => $invitation->schoolClass->name,
            'organization_name' => $invitation->organization->name,
            'expires_at' => $invitation->expires_at,
            'status' => $invitation->accepted_at ? 'accepted' : ($invitation->expires_at->isPast() ? 'expired' : 'pending'),
            'existing_user' => User::where('email', $invitation->email)->exists(),
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

<?php

namespace App\Http\Controllers\Family;

use App\Http\Controllers\Concerns\ResolvesFamily;
use App\Http\Controllers\Controller;
use App\Models\LearnerProfile;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PracticePartnerController extends Controller
{
    use ResolvesFamily;

    public function index(Request $request, LearnerProfile $learner): JsonResponse
    {
        $this->ownChild($request, $learner);
        $users = User::whereIn('id', DB::table('learner_practice_partners')->where('learner_profile_id', $learner->id)->pluck('user_id'))
            ->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'email']);

        return response()->json(['data' => $users->map(fn (User $user) => [
            'id' => $user->id, 'name' => $user->name, 'email' => $user->email,
        ])->values()]);
    }

    public function store(Request $request, LearnerProfile $learner, AuditLogger $audit): JsonResponse
    {
        $this->ownChild($request, $learner);
        $values = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $recipient = User::whereRaw('LOWER(email) = ?', [Str::lower(trim($values['email']))])->first();
        abort_unless($recipient && $recipient->status === 'active' && $recipient->hasVerifiedEmail()
            && $recipient->hasAnyRole(['parent', 'teacher', 'supervisor']), 422,
            'Choose a registered, verified parent, guardian or teacher.');
        DB::table('learner_practice_partners')->updateOrInsert(
            ['learner_profile_id' => $learner->id, 'user_id' => $recipient->id],
            ['approved_by_user_id' => $request->user()->id, 'updated_at' => now(), 'created_at' => now()],
        );
        $audit->record('family.practice_partner_approved', $learner, [], ['partner_user_id' => $recipient->id]);

        return response()->json(['data' => ['id' => $recipient->id, 'name' => $recipient->name, 'email' => $recipient->email]], 201);
    }

    public function destroy(Request $request, LearnerProfile $learner, User $partner, AuditLogger $audit): JsonResponse
    {
        $this->ownChild($request, $learner);
        DB::table('learner_practice_partners')->where('learner_profile_id', $learner->id)->where('user_id', $partner->id)->delete();
        $audit->record('family.practice_partner_removed', $learner, ['partner_user_id' => $partner->id], []);

        return response()->json(null, 204);
    }

    private function ownChild(Request $request, LearnerProfile $learner): void
    {
        $family = $this->family($request->user());
        abort_unless((int) $learner->family_id === (int) $family->id && $learner->user_id !== $request->user()->id, 403);
    }
}

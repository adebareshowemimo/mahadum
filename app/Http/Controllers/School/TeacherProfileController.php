<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\TeacherProfile;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole('teacher'), 403);

        return response()->json(['data' => TeacherProfile::firstOrCreate(['user_id' => $request->user()->id])
            ->only(['years_experience', 'native_language', 'state', 'dialects'])]);
    }

    public function update(Request $request, AuditLogger $audit): JsonResponse
    {
        abort_unless($request->user()->hasRole('teacher'), 403);
        $values = $request->validate([
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:80'],
            'native_language' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'dialects' => ['nullable', 'array', 'max:20'],
            'dialects.*' => ['required', 'string', 'max:100'],
        ]);
        $profile = TeacherProfile::firstOrCreate(['user_id' => $request->user()->id]);
        $before = $profile->only(array_keys($values));
        $profile->update($values);
        $audit->record('teacher.profile_updated', $profile, $before, $values);

        return response()->json(['data' => $profile->only(['years_experience', 'native_language', 'state', 'dialects'])]);
    }
}

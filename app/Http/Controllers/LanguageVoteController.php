<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LanguageVoteController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'language' => ['required', Rule::in(config('language-votes.languages'))],
            'other_language' => ['required_if:language,Other', 'nullable', 'string', 'max:60', 'not_regex:/^\s*$/u'],
        ]);
        $inserted = DB::table('language_votes')->insertOrIgnore([
            'user_id' => $request->user()->id,
            'language' => $data['language'],
            'other_language' => $data['language'] === 'Other' ? trim($data['other_language']) : null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        abort_unless($inserted, 409, 'Your language vote has already been recorded. Each account gets one vote.');

        return response()->json(['message' => 'Your vote has been counted. Thank you!'], 201);
    }
}

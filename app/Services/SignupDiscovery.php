<?php

namespace App\Services;

use App\Models\Language;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SignupDiscovery
{
    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'preferred_language' => ['nullable', Rule::in(['igbo', 'yoruba', 'hausa', 'english'])],
            'language_vote' => ['nullable', Rule::in(config('language-votes.languages'))],
            'other_language' => ['required_if:language_vote,Other', 'nullable', 'string', 'max:60'],
            'utm_source' => ['nullable', 'string', 'max:255'],
            'utm_medium' => ['nullable', 'string', 'max:255'],
            'utm_campaign' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function record(User $user, Request $request): void
    {
        $user->forceFill($request->only(['preferred_language', 'utm_source', 'utm_medium', 'utm_campaign']))->save();
        $preferred = $request->input('preferred_language');
        if (is_string($preferred)) {
            $codes = ['igbo' => 'ig', 'yoruba' => 'yo', 'hausa' => 'ha', 'english' => 'en'];
            $language = Language::query()->whereIn('code', [$preferred, $codes[$preferred]])->where('is_active', true)->first();
            if ($language) {
                $user->learnerProfile()->update(['target_language_id' => $language->id]);
            }
        }
        if ($request->filled('language_vote')) {
            DB::table('language_votes')->insert([
                'user_id' => $user->id, 'language' => $request->input('language_vote'),
                'other_language' => $request->input('language_vote') === 'Other' ? trim($request->string('other_language')->toString()) : null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('discovery_events')->insert(['user_id' => $user->id, 'name' => 'sign_up', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function firstLesson(?int $userId): void
    {
        if ($userId) {
            DB::table('discovery_events')->insertOrIgnore(['user_id' => $userId, 'name' => 'first_lesson_completed', 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}

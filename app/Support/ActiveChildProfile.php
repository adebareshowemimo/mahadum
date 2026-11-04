<?php

namespace App\Support;

use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class ActiveChildProfile
{
    public static function id(Request $request): ?int
    {
        $token = $request->user()?->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $id = $token->getAttribute('active_child_profile_id');

            return $id ? (int) $id : null;
        }

        return $request->hasSession() ? $request->session()->get('active_child_profile_id') : null;
    }

    public static function set(Request $request, ?int $id): void
    {
        $token = $request->user()?->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->setAttribute('active_child_profile_id', $id);
            $token->save();
        } elseif ($request->hasSession()) {
            $request->session()->put('active_child_profile_id', $id);
        }
    }
}

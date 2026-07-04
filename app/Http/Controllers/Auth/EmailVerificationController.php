<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    /**
     * Verify an email from the signed link. No bearer token is required — the
     * URL signature plus the email hash prove the request is genuine. The route
     * is `signed`, so a tampered or expired link is rejected before reaching here.
     */
    public function verify(Request $request, string $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        abort_unless(
            hash_equals($hash, sha1($user->getEmailForVerification())),
            403,
            'Invalid verification link.',
        );

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return response()->json(['data' => ['verified' => true]]);
    }

    /** Resend the verification email to the authenticated, still-unverified user. */
    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['data' => ['verified' => true]]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['data' => ['message' => 'Verification link sent.']], 202);
    }
}

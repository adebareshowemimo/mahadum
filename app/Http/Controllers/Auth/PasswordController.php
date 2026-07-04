<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

class PasswordController extends Controller
{
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        // Always return 202 — don't leak whether the email exists.
        Password::sendResetLink($request->only('email'));

        return response()->json(['data' => ['message' => 'If that email exists, a reset link has been sent.']], 202);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill(['password' => $password])->save();
                $user->tokens()->delete(); // revoke all tokens on password change
            }
        );

        if ($status !== Password::PasswordReset) {
            return response()->json([
                'error' => ['code' => 'reset_failed', 'message' => __($status), 'status' => 422],
            ], 422);
        }

        return response()->json(['data' => ['message' => __($status)]]);
    }
}

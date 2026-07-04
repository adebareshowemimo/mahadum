<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\GoogleAuthRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use App\Notifications\NewDeviceAlert;
use App\Services\Referral\ReferralService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;

class AuthController extends Controller
{
    private const TOKEN_TTL_DAYS = 30;

    public function register(RegisterRequest $request, ReferralService $referrals): JsonResponse
    {
        $role = $request->accountType() === 'learner' ? 'student' : 'parent';

        $user = DB::transaction(function () use ($request, $role) {
            $user = User::create([
                'first_name' => $request->string('first_name'),
                'last_name' => $request->string('last_name'),
                'email' => $request->string('email'),
                'username' => $request->input('username'),
                'password' => $request->string('password'), // hashed by cast
                'locale' => $request->header('Accept-Language', 'en'),
            ]);

            $user->assignRole($role);

            if ($role === 'parent') {
                $family = Family::create([
                    'owner_user_id' => $user->id,
                    'name' => $request->input('family_name', $request->string('first_name')."'s Family"),
                ]);

                FamilyMember::create([
                    'family_id' => $family->id,
                    'user_id' => $user->id,
                    'relationship' => 'parent',
                    'is_account_owner' => true,
                ]);
            }

            return $user;
        });

        // Attribute the sign-up to a referral code (if supplied) — fraud guards inside.
        $referrals->attribute($user, $request->input('referral_code'), $request->header('X-Device-Id'));

        // Sends the verification email via the framework's default listener.
        event(new Registered($user));

        return $this->tokenResponse($user, $request->string('device_name'), 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $login = $request->string('login');

        $user = User::where('email', $login)->orWhere('username', $login)->first();

        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            return $this->error('invalid_credentials', 'The provided credentials are incorrect.', 401);
        }

        if ($user->status !== 'active') {
            return $this->error('account_disabled', 'This account is not active.', 403);
        }

        $this->alertOnNewDevice($user, $request);

        return $this->tokenResponse($user, $request->string('device_name'));
    }

    /**
     * Send a security alert when a login comes from a device fingerprint we
     * haven't seen for this user. Requires the X-Device-Id header (skipped
     * otherwise) and at least one already-known device, so a user's first device
     * — and clients that don't fingerprint — never trigger a false alarm.
     */
    private function alertOnNewDevice(User $user, Request $request): void
    {
        $fingerprint = $request->header('X-Device-Id');
        if (! $fingerprint) {
            return;
        }

        $devices = $user->devices();
        if ($devices->count() === 0 || $devices->where('device_fingerprint', $fingerprint)->exists()) {
            return;
        }

        $user->notify(new NewDeviceAlert($request->ip(), $request->userAgent()));
    }

    public function google(GoogleAuthRequest $request): JsonResponse
    {
        $driver = Socialite::driver('google');
        if (! $driver instanceof AbstractProvider) {
            return $this->error('google_unavailable', 'Google login is not available.', 500);
        }

        try {
            $googleUser = $driver->stateless()->userFromToken($request->string('id_token'));
        } catch (\Throwable $e) {
            return $this->error('invalid_google_token', 'Could not verify the Google token.', 401);
        }

        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if (! $user) {
            $user = DB::transaction(function () use ($googleUser) {
                [$first, $last] = $this->splitName($googleUser->getName());
                $user = User::create([
                    'first_name' => $first,
                    'last_name' => $last,
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'email_verified_at' => now(),
                ]);
                $user->assignRole('parent');

                $family = Family::create([
                    'owner_user_id' => $user->id,
                    'name' => $user->name."'s Family",
                ]);
                FamilyMember::create([
                    'family_id' => $family->id,
                    'user_id' => $user->id,
                    'relationship' => 'parent',
                    'is_account_owner' => true,
                ]);

                return $user;
            });
        } elseif (! $user->google_id) {
            $user->update(['google_id' => $googleUser->getId()]);
        }

        return $this->tokenResponse($user, $request->string('device_name'));
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $current = $user->currentAccessToken();
        $name = $current->name;
        $abilities = $current->abilities ?: $user->getRoleNames()->all();

        $current->delete(); // revoke the old token

        $token = $user->createToken($name, $abilities, now()->addDays(self::TOKEN_TTL_DAYS));

        return response()->json(['data' => [
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at,
        ]]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }

    private function tokenResponse(User $user, string $deviceName, int $status = 200): JsonResponse
    {
        $abilities = $user->getRoleNames()->all();
        $token = $user->createToken($deviceName, $abilities, now()->addDays(self::TOKEN_TTL_DAYS));

        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json(['data' => [
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at,
            'abilities' => $abilities,
            'user' => new UserResource($user),
        ]], $status);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => compact('code', 'message') + ['status' => $status]], $status);
    }

    /** Split a single display name (e.g. from Google) into [first, last]. */
    private function splitName(?string $full): array
    {
        $parts = preg_split('/\s+/', trim((string) $full)) ?: [];
        $first = array_shift($parts) ?: 'New';
        $last = $parts ? implode(' ', $parts) : 'User';

        return [$first, $last];
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\GoogleAuthRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\LearnerProfile;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Notifications\NewDeviceAlert;
use App\Services\AuditLogger;
use App\Services\Referral\ReferralService;
use App\Services\School\ClassLearnerInvitationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;

class AuthController extends Controller
{
    private const TOKEN_TTL_DAYS = 30;

    public function register(
        RegisterRequest $request,
        ReferralService $referrals,
        ClassLearnerInvitationService $classInvitations,
        AuditLogger $audit,
    ): JsonResponse {
        $invitationToken = $request->string('class_invitation_token')->toString();
        $invitation = $invitationToken !== '' ? $classInvitations->findByToken($invitationToken) : null;
        abort_if($invitation?->accepted_at !== null, 409, 'This invitation has already been used.');
        abort_if($invitation?->expires_at?->isPast(), 410, 'This invitation has expired.');
        if ($invitation && strcasecmp($invitation->email, $request->string('email')->toString()) !== 0) {
            throw ValidationException::withMessages([
                'email' => 'Register with the email address that received this invitation.',
            ]);
        }

        $accountType = $invitation ? 'learner' : $request->accountType();

        [$user, $organization] = DB::transaction(function () use ($request, $accountType, $invitationToken, $classInvitations) {
            $user = User::create([
                'first_name' => $request->string('first_name'),
                'last_name' => $request->string('last_name'),
                'email' => $request->string('email'),
                'phone' => $request->string('phone'),
                'date_of_birth' => $request->input('date_of_birth'),
                'username' => $request->input('username'),
                'password' => $request->string('password'), // hashed by cast
                'locale' => $request->header('Accept-Language', 'en'),
            ]);

            $organization = $this->provisionAccount(
                $user,
                $accountType,
                $request->input('organization_name'),
                $request->input('family_name'),
            );

            if ($invitationToken !== '') {
                $classInvitations->accept($invitationToken, $user);
            }

            return [$user, $organization];
        });

        if ($organization) {
            $audit->record(
                'organization.self_registered',
                $organization,
                [],
                ['name' => $organization->name, 'type' => $organization->type, 'status' => $organization->status],
                $organization->id,
            );
        }

        // Attribute the sign-up to a referral code (if supplied) — fraud guards inside.
        $referrals->attribute($user, $request->input('referral_code'), $request->header('X-Device-Id'));

        // Sends the verification email via the framework's default listener.
        event(new Registered($user));

        return $this->tokenResponse($user, $request->string('device_name'), 201);
    }

    public function login(LoginRequest $request, ClassLearnerInvitationService $classInvitations): JsonResponse
    {
        $login = $request->string('login');

        $user = User::where('email', $login)->orWhere('username', $login)->first();

        // A null password means an OAuth-only account (see the google() flow):
        // it must never be reachable by password, and never reach Hash::check.
        if (! $user || $user->password === null || ! Hash::check($request->string('password'), $user->password)) {
            return $this->error('invalid_credentials', 'The provided credentials are incorrect.', 401);
        }

        if ($user->status !== 'active') {
            return $this->error('account_disabled', 'This account is not active.', 403);
        }

        if ($request->filled('class_invitation_token')) {
            $classInvitations->accept($request->string('class_invitation_token')->toString(), $user);
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

    public function google(GoogleAuthRequest $request, AuditLogger $audit, ReferralService $referrals): JsonResponse
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

        $organization = null;
        $created = false;
        if (! $user) {
            [$user, $organization] = DB::transaction(function () use ($googleUser, $request) {
                [$first, $last] = $this->splitName($googleUser->getName());
                $user = User::create([
                    'first_name' => $first,
                    'last_name' => $last,
                    'email' => $googleUser->getEmail(),
                    'phone' => $request->input('phone'),
                    'date_of_birth' => $request->input('date_of_birth'),
                    'google_id' => $googleUser->getId(),
                    'email_verified_at' => now(),
                ]);
                $organization = $this->provisionAccount(
                    $user,
                    $request->input('account_type', 'family'),
                    $request->input('organization_name'),
                );

                return [$user, $organization];
            });
            $created = true;
        } elseif (! $user->google_id) {
            $user->update(['google_id' => $googleUser->getId()]);
        }

        if ($organization) {
            $audit->record(
                'organization.self_registered',
                $organization,
                [],
                ['name' => $organization->name, 'type' => $organization->type, 'status' => $organization->status],
                $organization->id,
            );
        }

        // Only a brand-new Google account can be attributed. Existing accounts
        // that later link Google must never gain a referral retroactively.
        if ($created) {
            $referrals->attribute($user, $request->input('referral_code'), $request->header('X-Device-Id'));
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

    private function provisionAccount(
        User $user,
        string $accountType,
        ?string $organizationName = null,
        ?string $familyName = null,
    ): ?Organization {
        if (in_array($accountType, ['individual', 'learner'], true)) {
            $user->assignRole('student');
            LearnerProfile::create([
                'user_id' => $user->id,
                'display_name' => $user->name,
            ]);

            return null;
        }

        if (in_array($accountType, ['educator_school', 'institution'], true)) {
            $name = trim((string) $organizationName);
            $organization = Organization::create([
                'name' => $name,
                'type' => $accountType === 'institution' ? 'institution' : 'school',
                'slug' => $this->uniqueOrganizationSlug($name),
                'contact_email' => $user->email,
                'status' => 'pending',
            ]);

            $user->update(['organization_id' => $organization->id]);
            $user->assignRole('school_admin');
            OrganizationUser::create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'role' => 'school_admin',
                'status' => 'active',
            ]);

            return $organization;
        }

        $user->assignRole('parent');
        $family = Family::create([
            'owner_user_id' => $user->id,
            'name' => $familyName ?: $user->first_name."'s Family",
        ]);
        FamilyMember::create([
            'family_id' => $family->id,
            'user_id' => $user->id,
            'relationship' => 'parent',
            'is_account_owner' => true,
        ]);

        return null;
    }

    private function uniqueOrganizationSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'organization';
        $slug = $base;
        $suffix = 1;

        while (Organization::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }
}

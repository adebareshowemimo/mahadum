<?php

namespace Tests\Feature;

use App\Models\Referral;
use App\Models\User;
use App\Services\Referral\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Mockery;
use Tests\TestCase;

/**
 * Covers POST auth/google. Socialite is mocked at the provider boundary — the
 * point under test is our account resolution (create vs link vs reuse), not
 * Google's token verification.
 */
class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_google_signup_preserves_referral_attribution(): void
    {
        $this->seedRbac();
        $referrer = $this->userWithRole('parent');
        $code = app(ReferralService::class)->codeFor($referrer)->code;
        $this->fakeGoogleUser('google-referred', 'referred-google@example.com', 'Referred Person');

        $this->postJson('/api/v1/auth/google', [
            'id_token' => 'valid-token',
            'device_name' => 'browser',
            'account_type' => 'family',
            'phone' => '+2348012345678',
            'referral_code' => $code,
        ], ['X-Device-Id' => 'google-referral-device'])->assertOk();

        $referred = User::where('email', 'referred-google@example.com')->firstOrFail();
        $this->assertDatabaseHas('referrals', [
            'referred_user_id' => $referred->id,
            'device_fingerprint' => 'google-referral-device',
            'status' => 'pending',
        ]);
        $this->assertSame($code, Referral::where('referred_user_id', $referred->id)->firstOrFail()->referralCode->code);
    }

    /** Stub the Google provider so userFromToken() returns a fixed identity. */
    private function fakeGoogleUser(string $id, string $email, string $name): void
    {
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn($id);
        $googleUser->shouldReceive('getEmail')->andReturn($email);
        $googleUser->shouldReceive('getName')->andReturn($name);

        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('userFromToken')->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    private function failingGoogleProvider(): void
    {
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('userFromToken')->andThrow(new \RuntimeException('bad token'));

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_first_google_login_creates_a_verified_parent_with_a_family(): void
    {
        $this->seedRbac();
        $this->fakeGoogleUser('google-123', 'chidi@test.local', 'Chidi Okafor');

        $res = $this->postJson('/api/v1/auth/google', [
            'id_token' => 'stub-token',
            'device_name' => 'Pixel',
        ]);

        $res->assertOk()->assertJsonStructure(['data' => ['token']]);

        $user = User::where('email', 'chidi@test.local')->firstOrFail();
        $this->assertSame('google-123', $user->google_id);
        $this->assertSame('Chidi', $user->first_name);
        $this->assertSame('Okafor', $user->last_name);
        $this->assertNotNull($user->email_verified_at, 'Google identities arrive pre-verified.');
        $this->assertTrue($user->hasRole('parent'));
        $this->assertDatabaseHas('families', ['owner_user_id' => $user->id]);
    }

    public function test_google_login_links_an_existing_password_account_by_email(): void
    {
        $this->seedRbac();
        $existing = User::factory()->create(['email' => 'ada@test.local', 'google_id' => null]);
        $this->fakeGoogleUser('google-456', 'ada@test.local', 'Ada Eze');

        $this->postJson('/api/v1/auth/google', [
            'id_token' => 'stub-token',
            'device_name' => 'Pixel',
        ])->assertOk();

        $existing->refresh();
        $this->assertSame('google-456', $existing->google_id, 'Should link, not create a duplicate account.');
        $this->assertSame(1, User::where('email', 'ada@test.local')->count());
    }

    public function test_first_google_signup_preserves_the_selected_institution_type(): void
    {
        $this->seedRbac();
        $this->fakeGoogleUser('google-institution', 'director@test.local', 'Ngozi Eze');

        $this->postJson('/api/v1/auth/google', [
            'id_token' => 'stub-token',
            'device_name' => 'Pixel',
            'account_type' => 'institution',
            'organization_name' => 'Nigerian Heritage Institute',
            'phone' => '+2348012345700',
            'date_of_birth' => '1988-06-12',
        ])->assertOk()->assertJsonPath('data.abilities', ['school_admin']);

        $user = User::where('email', 'director@test.local')->firstOrFail();
        $this->assertTrue($user->hasRole('school_admin'));
        $this->assertSame('+2348012345700', $user->phone);
        $this->assertSame('1988-06-12', $user->date_of_birth?->toDateString());
        $this->assertDatabaseHas('organizations', [
            'name' => 'Nigerian Heritage Institute',
            'type' => 'institution',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('organization_user', [
            'user_id' => $user->id,
            'role' => 'school_admin',
        ]);
    }

    public function test_google_signup_requires_a_phone_when_an_account_type_is_selected(): void
    {
        $this->postJson('/api/v1/auth/google', [
            'id_token' => 'stub-token',
            'device_name' => 'Pixel',
            'account_type' => 'family',
        ])->assertStatus(422)->assertJsonValidationErrors('phone');
    }

    public function test_repeat_google_login_reuses_the_same_account(): void
    {
        $this->seedRbac();
        $this->fakeGoogleUser('google-123', 'chidi@test.local', 'Chidi Okafor');

        $this->postJson('/api/v1/auth/google', ['id_token' => 't', 'device_name' => 'Pixel'])->assertOk();
        $this->postJson('/api/v1/auth/google', ['id_token' => 't', 'device_name' => 'Pixel'])->assertOk();

        $this->assertSame(1, User::where('google_id', 'google-123')->count());
    }

    public function test_an_unverifiable_token_is_rejected(): void
    {
        $this->seedRbac();
        $this->failingGoogleProvider();

        $this->postJson('/api/v1/auth/google', [
            'id_token' => 'garbage',
            'device_name' => 'Pixel',
        ])->assertStatus(401)->assertJsonPath('error.code', 'invalid_google_token');

        $this->assertSame(0, User::count());
    }

    public function test_a_google_only_account_cannot_be_logged_into_by_password(): void
    {
        $this->seedRbac();
        $this->fakeGoogleUser('google-123', 'chidi@test.local', 'Chidi Okafor');
        $this->postJson('/api/v1/auth/google', ['id_token' => 't', 'device_name' => 'Pixel'])->assertOk();

        $user = User::where('email', 'chidi@test.local')->firstOrFail();
        $this->assertNull($user->password, 'An OAuth account is created without a password.');

        // An empty/absent password must not authenticate the null-password row.
        $this->postJson('/api/v1/auth/login', [
            'login' => 'chidi@test.local',
            'password' => '',
            'device_name' => 'Pixel',
        ])->assertStatus(422);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'chidi@test.local',
            'password' => 'AnyPassword123!',
            'device_name' => 'Pixel',
        ])->assertStatus(401)->assertJsonPath('error.code', 'invalid_credentials');
    }

    public function test_id_token_and_device_name_are_required(): void
    {
        $this->postJson('/api/v1/auth/google', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id_token', 'device_name']);
    }
}

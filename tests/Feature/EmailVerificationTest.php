<?php

namespace Tests\Feature;

use App\Http\Middleware\RequireVerifiedEmail;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_a_verification_email(): void
    {
        Notification::fake();
        $this->seedRbac();

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Vee', 'last_name' => 'Rify', 'email' => 'verify@test.local', 'phone' => '+2348012340001',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!', 'device_name' => 'd',
        ])->assertCreated();

        $user = User::where('email', 'verify@test.local')->first();
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_signed_link_verifies_the_email(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id, 'hash' => sha1($user->getEmailForVerification()),
        ]);
        $parts = parse_url($url);
        $relative = $parts['path'].'?'.$parts['query'];

        $this->getJson($relative)->assertOk()->assertJsonPath('data.verified', true);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_tampered_hash_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id, 'hash' => sha1('someone-elses-email@test.local'),
        ]);
        $parts = parse_url($url);

        $this->getJson($parts['path'].'?'.$parts['query'])->assertStatus(403);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_resend_to_authenticated_unverified_user(): void
    {
        Notification::fake();
        $user = $this->actingAsUser(User::factory()->unverified()->create());

        $this->postJson('/api/v1/email/verification-notification')->assertStatus(202);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_unverified_accounts_cannot_use_application_routes_even_as_admin(): void
    {
        $this->seedRbac();
        $user = $this->userWithRole('super_admin', ['email_verified_at' => null]);
        $this->actingAsUser($user);

        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.user.email_verified', false);
        $this->getJson('/api/v1/courses')->assertForbidden()->assertJsonPath('error.code', 'email_not_verified');
        $this->postJson('/api/v1/me/learner-profile')->assertForbidden()->assertJsonPath('error.code', 'email_not_verified');
        // Clients without an Accept header receive the same API error, not a broken redirect.
        $this->get('/api/v1/courses')->assertForbidden()->assertJsonPath('error.code', 'email_not_verified');
    }

    public function test_registration_token_is_restricted_until_the_signed_link_is_used(): void
    {
        Notification::fake();
        $this->seedRbac();
        $result = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Vee', 'last_name' => 'Rify', 'email' => 'flow@test.local', 'phone' => '+2348012340002',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!', 'device_name' => 'd',
        ])->assertCreated();
        $user = User::where('email', 'flow@test.local')->firstOrFail();
        $this->withToken($result->json('data.token'))->getJson('/api/v1/courses')->assertForbidden();
        $this->postJson('/api/v1/email/verification-notification')->assertAccepted();
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id, 'hash' => sha1($user->getEmailForVerification()),
        ]);
        $this->getJson($url)->assertOk();
        // Resolve a fresh user instance, as the next HTTP request does in production.
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/courses')->assertOk();
    }

    public function test_unverified_user_can_refresh_and_revoke_a_real_token(): void
    {
        $user = User::factory()->unverified()->create();
        $token = $user->createToken('verification')->plainTextToken;
        $result = $this->withToken($token)->postJson('/api/v1/auth/refresh')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($result->json('data.token'))->deleteJson('/api/v1/auth/token')->assertNoContent();
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_browser_link_returns_to_the_verification_page(): void
    {
        config(['app.frontend_url' => 'https://mahadum.test']);
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id, 'hash' => sha1($user->getEmailForVerification()),
        ]);
        $this->get($url)->assertRedirect('https://mahadum.test/verify-email?verified=1');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_expired_link_does_not_unlock_application_access(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->subMinute(), [
            'id' => $user->id, 'hash' => sha1($user->getEmailForVerification()),
        ]);
        $this->getJson($url)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_expired_browser_link_returns_to_resend_recovery(): void
    {
        config(['app.frontend_url' => 'https://mahadum.test']);
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->subMinute(), [
            'id' => $user->id, 'hash' => sha1($user->getEmailForVerification()),
        ]);
        $this->get($url)->assertRedirect('https://mahadum.test/verify-email?expired=1');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_all_authenticated_api_routes_require_verification_except_session_recovery(): void
    {
        $recovery = ['api/v1/me', 'api/v1/auth/refresh', 'api/v1/auth/token', 'api/v1/email/verification-notification'];
        foreach (app('router')->getRoutes() as $route) {
            $middleware = $route->gatherMiddleware();
            if (in_array('auth:sanctum', $middleware, true) && ! in_array($route->uri(), $recovery, true)) {
                $this->assertContains(RequireVerifiedEmail::class, $middleware, $route->uri());
            }
        }
    }
}

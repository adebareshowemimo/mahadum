<?php

namespace Tests\Feature;

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
            'first_name' => 'Vee', 'last_name' => 'Rify', 'email' => 'verify@test.local',
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
}

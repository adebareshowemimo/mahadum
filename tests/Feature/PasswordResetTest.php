<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Covers POST auth/password/forgot + auth/password/reset. The forgot endpoint
 * deliberately answers 202 for unknown emails (no account enumeration), and a
 * successful reset must revoke every existing token — a stolen session should
 * not survive the password change that was meant to end it.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_sends_a_reset_link_to_a_known_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'ada@test.local']);

        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'ada@test.local'])
            ->assertStatus(202);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_forgot_does_not_leak_whether_an_email_exists(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'nobody@test.local'])
            ->assertStatus(202)
            ->assertJsonPath('data.message', 'If that email exists, a reset link has been sent.');

        Notification::assertNothingSent();
    }

    public function test_reset_changes_the_password_and_revokes_all_tokens(): void
    {
        $user = User::factory()->create([
            'email' => 'ada@test.local',
            'password' => Hash::make('OldPassword123!'),
        ]);
        $user->createToken('old-device');
        $this->assertSame(1, $user->tokens()->count());

        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/password/reset', [
            'token' => $token,
            'email' => 'ada@test.local',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
        $this->assertSame(0, $user->tokens()->count(), 'Existing tokens must be revoked on password reset.');
    }

    public function test_reset_rejects_an_invalid_token(): void
    {
        User::factory()->create(['email' => 'ada@test.local', 'password' => Hash::make('OldPassword123!')]);

        $this->postJson('/api/v1/auth/password/reset', [
            'token' => 'not-a-real-token',
            'email' => 'ada@test.local',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertStatus(422)->assertJsonPath('error.code', 'reset_failed');
    }

    public function test_reset_requires_a_confirmed_password(): void
    {
        $user = User::factory()->create(['email' => 'ada@test.local']);

        $this->postJson('/api/v1/auth/password/reset', [
            'token' => Password::createToken($user),
            'email' => 'ada@test.local',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'Mismatched123!',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }
}

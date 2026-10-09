<?php

namespace Tests\Feature;

use App\Models\Chore;
use App\Models\ChoreSubmission;
use App\Models\FamilyAlertPreference;
use App\Models\WebPushSubscription;
use App\Notifications\FamilyActivityAlert;
use App\Services\Family\FamilyAlertService;
use App\Services\Family\WalletService;
use App\Services\Messaging\WebPushService;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\WebPush;
use Mockery;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class Batch4NotificationsTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_cached_family_membership_does_not_send_inactivity_alert_for_a_moved_learner(): void
    {
        $this->seedRbac();
        Notification::fake();
        $parent = $this->userWithRole('parent');
        $learner = $this->parentWithChild($parent);
        $learner->update(['created_at' => now()->subDays(8)]);
        $family = $learner->family->load('learnerProfiles', 'owner');
        FamilyAlertPreference::create(['family_id' => $family->id, 'inactive_days' => 7, 'review_alerts' => false]);
        $other = $this->parentWithChild($this->userWithRole('parent'));
        $learner->update(['family_id' => $other->family_id]);
        app(FamilyAlertService::class)->evaluate($family);
        Notification::assertNothingSent();
    }

    public function test_cached_active_owner_cannot_receive_alerts_after_suspension(): void
    {
        $this->seedRbac();
        Notification::fake();
        $parent = $this->userWithRole('parent');
        $learner = $this->parentWithChild($parent);
        $family = $learner->family->load('owner');
        FamilyAlertPreference::create(['family_id' => $family->id, 'low_balance_coins' => 0]);
        $parent->update(['status' => 'suspended']);
        app(FamilyAlertService::class)->evaluate($family);
        Notification::assertNothingSent();
    }

    // Public test-only key pair. Never use these keys for a deployed service.
    private const PUBLIC_KEY = 'BAE5Jwen-j4MhOC5Clgczi7twm-Jm6EhbzLZlFZwxW8BTin45VqSS2A08gyIRM6ZW1rjkSAF0i_7qaWadKa-vjI';

    private const PRIVATE_KEY = 'WxOizfibcausPbNfTJc5-_MPoSRkIYquQ8pPJfMnolc';

    private function configured(): void
    {
        config(['services.messaging.live' => true, 'services.messaging.web_push.subject' => 'mailto:admin@example.com',
            'services.messaging.web_push.public_key' => self::PUBLIC_KEY, 'services.messaging.web_push.private_key' => self::PRIVATE_KEY]);
    }

    private function subscription(): array
    {
        return ['endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint', 'keys' => ['p256dh' => self::PUBLIC_KEY, 'auth' => rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=')]];
    }

    public function test_push_is_disabled_without_configuration_and_requires_verified_eligible_account(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent')->fresh());
        $this->getJson('/api/v1/me/push')->assertOk()->assertJsonPath('data.enabled', false)->assertJsonPath('data.public_key', null);
        $this->postJson('/api/v1/me/push', $this->subscription())->assertConflict();
        $this->configured();
        $this->actingAsUser($this->userWithRole('student', ['date_of_birth' => null])->fresh());
        $this->postJson('/api/v1/me/push', $this->subscription())->assertForbidden();
        $parent->update(['email_verified_at' => null]);
        $this->actingAsUser($parent);
        $this->postJson('/api/v1/me/push', $this->subscription())->assertForbidden();
    }

    public function test_push_subscription_is_encrypted_user_owned_and_rejects_arbitrary_endpoints(): void
    {
        $this->seedRbac();
        $this->configured();
        $parent = $this->actingAsUser($this->userWithRole('parent')->fresh());
        $input = $this->subscription();
        $this->postJson('/api/v1/me/push', $input)->assertCreated();
        $this->assertNotSame($input['endpoint'], DB::table('web_push_subscriptions')->value('endpoint'));
        $this->assertSame($input['endpoint'], WebPushSubscription::sole()->endpoint);
        $this->actingAsUser($this->userWithRole('parent')->fresh());
        $this->postJson('/api/v1/me/push', $input)->assertConflict();
        $this->deleteJson('/api/v1/me/push', ['endpoint' => $input['endpoint']])->assertNoContent();
        $this->assertDatabaseCount('web_push_subscriptions', 1);
        foreach (['http://127.0.0.1/private', 'https://169.254.169.254/latest/meta-data', 'https://fcm.googleapis.com.evil.test/push', 'https://fcm.googleapis.com:443/push', 'https://user:password@fcm.googleapis.com/push'] as $endpoint) {
            $this->postJson('/api/v1/me/push', [...$input, 'endpoint' => $endpoint])->assertUnprocessable();
        }
        $this->actingAsUser($parent);
        $this->deleteJson('/api/v1/me/push', ['endpoint' => $input['endpoint']])->assertNoContent();
        $this->assertDatabaseCount('web_push_subscriptions', 0);
    }

    public function test_browser_push_uses_generic_payload_and_removes_expired_endpoints_without_native_tokens(): void
    {
        $this->seedRbac();
        $this->configured();
        $parent = $this->actingAsUser($this->userWithRole('parent')->fresh());
        $input = $this->subscription();
        $this->postJson('/api/v1/me/push', $input)->assertCreated();
        $client = Mockery::mock(WebPush::class);
        $client->shouldReceive('sendOneNotification')->once()->withArgs(fn ($subscription, $payload) => $subscription->getEndpoint() === $input['endpoint']
            && ! str_contains($payload, 'private name') && json_decode($payload, true)['data']['url'] === '/notifications')
            ->andReturn(new MessageSentReport(new Request('POST', $input['endpoint']), new Response(410), false));
        $service = Mockery::mock(WebPushService::class)->makePartial();
        $service->shouldReceive('client')->once()->andReturn($client);
        $this->app->instance(WebPushService::class, $service);
        $parent->notify(new FamilyActivityAlert('review_needed', 'private name', 'private details', '/reviews'));
        $this->assertDatabaseCount('web_push_subscriptions', 0);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_token_refresh_preserves_browser_opt_in_and_logout_revokes_it(): void
    {
        $this->seedRbac();
        $this->configured();
        $parent = $this->userWithRole('parent')->fresh();
        $token = $parent->createToken('browser')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/v1/me/push', $this->subscription())->assertCreated();
        $next = $this->postJson('/api/v1/auth/refresh')->assertOk()->json('data.token');
        $this->assertDatabaseCount('web_push_subscriptions', 1);
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$next)->deleteJson('/api/v1/auth/token')->assertNoContent();
        $this->assertDatabaseCount('web_push_subscriptions', 0);
    }

    public function test_review_alert_waits_for_submitted_work_and_parent_approval_still_controls_rewards(): void
    {
        $this->seedRbac();
        Notification::fake();
        $parent = $this->userWithRole('parent');
        $child = $this->parentWithChild($parent);
        FamilyAlertPreference::create(['family_id' => $child->family_id, 'review_alerts' => true]);
        $chore = Chore::create(['family_id' => $child->family_id, 'created_by_user_id' => $parent->id, 'assignee_learner_profile_id' => $child->id, 'title' => 'Read', 'coin_reward' => 20, 'status' => 'pending_review']);
        $submission = ChoreSubmission::create(['chore_id' => $chore->id, 'evidence_type' => 'checkbox']);
        $service = app(FamilyAlertService::class);
        $service->evaluate($child->family);
        Notification::assertNothingSent();
        $submission->update(['submitted_at' => now()]);
        $service->evaluate($child->family);
        $service->evaluate($child->family);
        Notification::assertSentToTimes($parent, FamilyActivityAlert::class, 1);
        $this->assertDatabaseCount('coin_transactions', 0);
        $chore->update(['status' => 'completed']);
        $submission->update(['decision' => 'approved']);
        $service->evaluate($child->family);
        $this->assertNull(DB::table('family_alert_states')->where('key', 'review:chore:'.$chore->id)->value('episode'));
    }

    public function test_family_alert_fans_out_to_database_email_and_one_text_channel_with_http_fakes(): void
    {
        $this->seedRbac();
        $parent = $this->userWithRole('parent', ['phone' => '08031234567'])->fresh();
        config(['services.messaging.live' => true, 'services.messaging.text_channel' => 'sms', 'services.messaging.sms.base_url' => 'https://sms.test', 'services.messaging.sms.token' => 'fake', 'services.messaging.whatsapp.base_url' => 'https://wa.test', 'services.messaging.whatsapp.token' => 'fake']);
        Http::fake();
        $parent->notify(new FamilyActivityAlert('low_balance', 'Low coins', 'Check your wallet.', '/wallet'));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'sms.test') && $r['to'] === '08031234567');
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'wa.test'));
        $this->assertDatabaseCount('notifications', 1);
        config(['services.messaging.text_channel' => 'whatsapp']);
        Http::fake();
        $parent->notify(new FamilyActivityAlert('low_balance', 'Low coins', 'Check your wallet.', '/wallet'));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'wa.test'));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'sms.test'));
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_notification_dispatch_failure_does_not_turn_a_completed_transfer_into_a_retryable_error(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $child = $this->parentWithChild($parent);
        $wallets = app(WalletService::class);
        $wallets->credit($wallets->walletFor($child->family), 100, 'test');
        $service = Mockery::mock(FamilyAlertService::class);
        $service->shouldReceive('evaluate')->once()->andThrow(new \RuntimeException('Test queue unavailable'));
        $this->app->instance(FamilyAlertService::class, $service);
        foreach (range(1, 2) as $unused) {
            $this->postJson('/api/v1/wallet/transfer', ['to_learner_id' => $child->id, 'coins' => 30], ['Idempotency-Key' => 'queue-failure'])
                ->assertOk()->assertJsonPath('data.family_balance', 70)->assertJsonPath('data.learner_balance', 30);
        }
        $this->assertSame(30, $wallets->walletFor($child)->coin_balance);
    }
}

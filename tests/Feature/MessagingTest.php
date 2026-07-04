<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Payout;
use App\Models\User;
use App\Notifications\PayoutApproved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use RefreshDatabase;

    private function goLive(string $textChannel = 'sms'): void
    {
        config([
            'services.messaging.live' => true,
            'services.messaging.text_channel' => $textChannel,
            'services.messaging.sms.base_url' => 'https://sms.test',
            'services.messaging.sms.token' => 'sms_token',
            'services.messaging.whatsapp.base_url' => 'https://wa.test',
            'services.messaging.whatsapp.token' => 'wa_token',
            'services.messaging.push.fcm_url' => 'https://fcm.test/send',
            'services.messaging.push.key' => 'fcm_key',
        ]);
    }

    private function approvedPayoutFor(User $user): Payout
    {
        $payout = new Payout(['amount_minor' => 500000, 'method' => 'bank', 'status' => 'approved', 'requested_at' => now()]);
        $payout->beneficiary()->associate($user);
        $payout->save();

        return $payout;
    }

    public function test_sms_is_sent_when_live(): void
    {
        $this->goLive('sms');
        Http::fake(['sms.test/*' => Http::response(['ok' => true])]);
        $user = User::factory()->create(['phone' => '08031234567']);

        $user->notify(new PayoutApproved($this->approvedPayoutFor($user)));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sms.test/send')
            && $request['to'] === '08031234567'
            && str_contains($request['message'], '5,000.00'));
    }

    public function test_whatsapp_is_used_when_text_channel_is_whatsapp(): void
    {
        $this->goLive('whatsapp');
        Http::fake(['wa.test/*' => Http::response(['ok' => true])]);
        $user = User::factory()->create(['phone' => '08031234567']);

        $user->notify(new PayoutApproved($this->approvedPayoutFor($user)));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'wa.test/messages') && $request['to'] === '08031234567');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'sms.test'));
    }

    public function test_push_is_sent_to_device_tokens(): void
    {
        $this->goLive('none'); // isolate push from any text channel
        Http::fake(['fcm.test/*' => Http::response(['success' => 1])]);
        $user = User::factory()->create();
        Device::create(['user_id' => $user->id, 'device_fingerprint' => 'fp', 'platform' => 'android', 'push_token' => 'tok-123']);

        $user->notify(new PayoutApproved($this->approvedPayoutFor($user)));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'fcm.test/send')
            && in_array('tok-123', $request['registration_ids'], true));
    }

    public function test_off_live_sends_no_messages_but_still_records_in_app(): void
    {
        Http::fake();
        $user = User::factory()->create(['phone' => '08031234567']);
        Device::create(['user_id' => $user->id, 'device_fingerprint' => 'fp', 'platform' => 'android', 'push_token' => 'tok-123']);

        $user->notify(new PayoutApproved($this->approvedPayoutFor($user)));

        Http::assertNothingSent();
        $this->assertDatabaseCount('notifications', 1);
    }
}

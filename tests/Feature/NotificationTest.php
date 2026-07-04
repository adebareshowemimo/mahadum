<?php

namespace Tests\Feature;

use App\Models\Payout;
use App\Models\Plan;
use App\Notifications\PayoutApproved;
use App\Notifications\SubscriptionActivated;
use App\Services\Billing\PaymentService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_activation_sends_a_receipt(): void
    {
        Notification::fake();
        $this->seedRbac();
        $this->seed(PlanSeeder::class);

        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $plan = Plan::where('code', 'premium_individual')->first();
        $subId = $this->postJson('/api/v1/subscriptions', ['plan_id' => $plan->id, 'method' => 'card'], [
            'Idempotency-Key' => 'rcpt-1',
        ])->json('data.subscription_id');

        app(PaymentService::class)->process('paystack', 'rcpt-evt', "sub_$subId", 'success', $plan->price_minor, []);

        Notification::assertSentTo($parent, SubscriptionActivated::class);
    }

    public function test_payout_approval_notifies_the_beneficiary(): void
    {
        Notification::fake();
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('super_admin'));
        $beneficiary = $this->userWithRole('parent');

        $payout = new Payout(['amount_minor' => 600000, 'method' => 'bank', 'status' => 'requested', 'requested_at' => now()]);
        $payout->beneficiary()->associate($beneficiary);
        $payout->save();

        $this->postJson("/api/v1/admin/payouts/{$payout->id}/approve")->assertOk();

        Notification::assertSentTo($beneficiary, PayoutApproved::class);
    }

    public function test_user_can_list_and_mark_notifications_read(): void
    {
        $this->seedRbac();
        $user = $this->actingAsUser($this->userWithRole('parent'));

        $payout = new Payout(['amount_minor' => 500000, 'method' => 'bank', 'status' => 'approved', 'requested_at' => now()]);
        $payout->beneficiary()->associate($user);
        $payout->save();
        $user->notify(new PayoutApproved($payout));

        $list = $this->getJson('/api/v1/me/notifications')
            ->assertOk()
            ->assertJsonPath('unread', 1)
            ->assertJsonPath('data.0.type', 'payout_approved');

        $id = $list->json('data.0.id');

        $this->postJson("/api/v1/me/notifications/{$id}/read")->assertOk();
        $this->getJson('/api/v1/me/notifications')->assertJsonPath('unread', 0);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\EmailLog;
use App\Models\Plan;
use App\Models\PromoCode;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletFundingTransaction;
use App\Notifications\NewDeviceAlert;
use App\Notifications\PaymentFailed;
use App\Notifications\PromoRedeemed;
use App\Notifications\WelcomeEmail;
use App\Services\Billing\PaymentService;
use App\Services\Billing\PromoService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_outbound_email_is_recorded_in_the_email_log(): void
    {
        Mail::raw('Hello there', function ($m) {
            $m->to('learner@example.test')->subject('Welcome aboard');
        });

        $this->assertDatabaseHas('email_logs', [
            'to_email' => 'learner@example.test',
            'subject' => 'Welcome aboard',
            'status' => 'sent',
            'type' => 'transactional',
        ]);

        $log = EmailLog::first();
        $this->assertNotNull($log?->message_id);
        $this->assertNotNull($log?->sent_at);
    }

    public function test_source_and_type_headers_are_captured(): void
    {
        $user = User::factory()->create();

        Mail::raw('Your receipt', function ($m) use ($user) {
            $m->to('buyer@example.test')->subject('Receipt');
            $headers = $m->getSymfonyMessage()->getHeaders();
            $headers->addTextHeader('X-Mahadum-Source', 'purchase_receipt');
            $headers->addTextHeader('X-Mahadum-Type', 'transactional');
            $headers->addTextHeader('X-Mahadum-User-Id', (string) $user->id);
        });

        $this->assertDatabaseHas('email_logs', [
            'to_email' => 'buyer@example.test',
            'source' => 'purchase_receipt',
            'type' => 'transactional',
            'user_id' => $user->id,
        ]);
    }

    public function test_one_row_per_recipient(): void
    {
        Mail::raw('Broadcast', function ($m) {
            $m->to(['a@example.test', 'b@example.test'])->subject('Hi all');
        });

        $this->assertSame(2, EmailLog::count());
    }

    public function test_email_renders_in_the_branded_template(): void
    {
        $message = (new MailMessage)
            ->subject('Test')
            ->greeting('Hi')
            ->line('Body copy')
            ->action('Go', 'https://example.test');

        $html = (string) app(Markdown::class)->render('notifications::email', $message->data());

        // Brand identity from the one global "Gilded Adire" theme.
        $this->assertStringContainsString('MAHADUM.360', $html);
        $this->assertStringContainsString('Learn the language', $html);
        $this->assertStringContainsString('#c7952b', strtolower($html)); // gold accent inlined
    }

    public function test_verifying_email_sends_the_welcome_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        event(new Verified($user));

        Notification::assertSentTo($user, WelcomeEmail::class);
    }

    public function test_welcome_email_is_tagged_and_logged_with_its_source(): void
    {
        $user = User::factory()->create(['email' => 'newbie@example.test']);

        $user->notify(new WelcomeEmail); // QUEUE=sync + MAIL_MAILER=array → sends inline

        $this->assertDatabaseHas('email_logs', [
            'to_email' => 'newbie@example.test',
            'source' => 'welcome',
            'type' => 'transactional',
            'user_id' => $user->id,
        ]);
    }

    public function test_wallet_funding_success_sends_a_receipt(): void
    {
        $user = User::factory()->create(['email' => 'topup@example.test']);
        $wallet = Wallet::create(['owner_type' => User::class, 'owner_id' => $user->id, 'currency' => 'NGN']);
        $funding = WalletFundingTransaction::create([
            'wallet_id' => $wallet->id,
            'gateway' => 'paystack',
            'amount_minor' => 50000,
            'status' => 'pending',
            'gateway_ref' => 'ref_topup_1',
        ]);

        app(PaymentService::class)->process('paystack', 'evt_topup_1', $funding->gateway_ref, 'success', 50000, []);

        $this->assertDatabaseHas('email_logs', [
            'to_email' => 'topup@example.test',
            'source' => 'wallet_funded',
            'type' => 'transactional',
            'user_id' => $user->id,
        ]);
    }

    public function test_login_from_a_new_device_sends_a_security_alert(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => bcrypt('Password123!'), 'status' => 'active']);
        // They already have one known device, so a *different* one is genuinely new.
        Device::create(['user_id' => $user->id, 'device_fingerprint' => 'known-device']);

        $this->postJson('/api/v1/auth/login',
            ['login' => $user->email, 'password' => 'Password123!', 'device_name' => 'iPhone'],
            ['X-Device-Id' => 'brand-new-device'],
        )->assertOk();

        Notification::assertSentTo($user, NewDeviceAlert::class);
    }

    public function test_login_from_a_known_device_does_not_alert(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => bcrypt('Password123!'), 'status' => 'active']);
        Device::create(['user_id' => $user->id, 'device_fingerprint' => 'known-device']);

        $this->postJson('/api/v1/auth/login',
            ['login' => $user->email, 'password' => 'Password123!', 'device_name' => 'iPhone'],
            ['X-Device-Id' => 'known-device'],
        )->assertOk();

        Notification::assertNotSentTo($user, NewDeviceAlert::class);
    }

    public function test_first_ever_device_does_not_alert(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => bcrypt('Password123!'), 'status' => 'active']);

        $this->postJson('/api/v1/auth/login',
            ['login' => $user->email, 'password' => 'Password123!', 'device_name' => 'iPhone'],
            ['X-Device-Id' => 'first-device'],
        )->assertOk();

        Notification::assertNotSentTo($user, NewDeviceAlert::class);
    }

    public function test_redeeming_a_promo_emails_the_user(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $plan = Plan::create(['code' => 'fam', 'name' => 'Family', 'price_minor' => 500000, 'interval' => 'month']);
        $promo = PromoCode::create(['code' => 'WELCOME20', 'discount_type' => 'percent', 'value' => 20]);
        $sub = new Subscription(['plan_id' => $plan->id, 'status' => 'active', 'method' => 'card']);
        $sub->subscriber()->associate($user);
        $sub->save();

        app(PromoService::class)->redeem($promo, $user, $sub);

        Notification::assertSentTo($user, PromoRedeemed::class);
        $this->assertDatabaseHas('promo_redemptions', ['promo_code_id' => $promo->id, 'user_id' => $user->id]);
    }

    public function test_failed_charge_emails_the_payer(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $plan = Plan::create(['code' => 'fam', 'name' => 'Family', 'price_minor' => 500000, 'interval' => 'month']);
        $sub = new Subscription(['plan_id' => $plan->id, 'status' => 'active', 'method' => 'card']);
        $sub->subscriber()->associate($user);
        $sub->save();

        $outcome = app(PaymentService::class)->process('paystack', 'evt_fail_1', "sub_{$sub->id}", 'failed', null, []);

        $this->assertSame('failed_notified', $outcome);
        Notification::assertSentTo($user, PaymentFailed::class);
    }

    public function test_mail_preview_command_writes_branded_html(): void
    {
        $out = storage_path('app/mail-previews/test-sample.html');
        File::delete($out);

        $this->artisan('mail:preview', ['--out' => $out])->assertSuccessful();

        $this->assertFileExists($out);
        $this->assertStringContainsString('MAHADUM.360', File::get($out));

        File::delete($out);
    }
}

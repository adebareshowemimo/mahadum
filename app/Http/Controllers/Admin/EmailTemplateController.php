<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateEmailTemplateRequest;
use App\Models\AssignmentSubmission;
use App\Models\Chore;
use App\Models\EmailTemplateOverride;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Payout;
use App\Models\Plan;
use App\Models\PromoCode;
use App\Models\Subscription;
use App\Models\SupportTicket;
use App\Models\TelcoSubscription;
use App\Models\User;
use App\Notifications\AssignmentApproved;
use App\Notifications\ChoreApproved;
use App\Notifications\InvoiceReceipt;
use App\Notifications\NewDeviceAlert;
use App\Notifications\OrganizationSeatAssigned;
use App\Notifications\PaymentFailed;
use App\Notifications\PayoutApproved;
use App\Notifications\PromoRedeemed;
use App\Notifications\SubscriptionActivated;
use App\Notifications\SubscriptionRenewalReminder;
use App\Notifications\SupportReply;
use App\Notifications\TelcoBillingReceipt;
use App\Notifications\WalletFunded;
use App\Notifications\WelcomeEmail;
use App\Services\AuditLogger;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * CRUD registry + renderer for every transactional email
 * (`emails.templates.view` / `emails.templates.manage`, super-admin-only).
 * Every entry's `build` closure constructs the real Notification class against
 * in-memory fixture models (never persisted) and calls its real `toMail()` —
 * which itself applies any saved override (App\Notifications\Concerns\
 * CustomizableMail) — so preview is byte-for-byte what a recipient gets, with
 * zero content duplicated here. `verify_email`/`password_reset` are Laravel's
 * own framework notifications and aren't customizable — listed read-only.
 */
class EmailTemplateController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(): JsonResponse
    {
        $customizedKeys = EmailTemplateOverride::pluck('key')->all();

        $templates = collect($this->builders())->map(fn ($_, $key) => [
            'key' => $key,
            'label' => $this->meta($key)['label'],
            'category' => $this->meta($key)['category'],
            'trigger' => $this->meta($key)['trigger'],
            'customizable' => $this->isCustomizable($key),
            'customized' => in_array($key, $customizedKeys, true),
        ])->values();

        return response()->json(['data' => $templates]);
    }

    public function show(string $key): JsonResponse
    {
        abort_unless(isset($this->builders()[$key]), 404, 'Unknown email template.');
        $meta = $this->meta($key);
        $customizable = $this->isCustomizable($key);

        $override = $customizable ? EmailTemplateOverride::where('key', $key)->first() : null;

        return response()->json(['data' => [
            'key' => $key,
            'label' => $meta['label'],
            'category' => $meta['category'],
            'trigger' => $meta['trigger'],
            'customizable' => $customizable,
            'placeholders' => $customizable ? $meta['placeholders'] : [],
            'default' => $customizable ? $meta['default'] : null,
            'override' => $override ? [
                'subject' => $override->subject,
                'greeting' => $override->greeting,
                'body' => $override->body,
                'action_text' => $override->action_text,
                'action_url' => $override->action_url,
                'updated_at' => $override->updated_at?->toIso8601String(),
            ] : null,
        ]]);
    }

    public function update(UpdateEmailTemplateRequest $request, string $key): JsonResponse
    {
        abort_unless(isset($this->builders()[$key]), 404, 'Unknown email template.');
        abort_unless($this->isCustomizable($key), 422, 'This template is framework-managed and cannot be customized.');

        $before = EmailTemplateOverride::where('key', $key)->first()?->only(['subject', 'greeting', 'body', 'action_text', 'action_url']) ?? [];

        $data = $request->validated();
        $data['updated_by'] = $request->user()?->id;

        EmailTemplateOverride::updateOrCreate(['key' => $key], $data);

        $this->audit->record('email_template.customized', null, $before, $request->validated());

        return $this->show($key);
    }

    public function destroy(string $key): JsonResponse
    {
        abort_unless(isset($this->builders()[$key]), 404, 'Unknown email template.');

        $override = EmailTemplateOverride::where('key', $key)->first();
        if ($override) {
            $this->audit->record('email_template.reset', null, $override->only(['subject', 'greeting', 'body', 'action_text', 'action_url']), []);
            $override->delete();
        }

        return $this->show($key);
    }

    public function preview(string $key): JsonResponse
    {
        $build = $this->builders()[$key] ?? null;
        abort_unless($build !== null, 404, 'Unknown email template.');

        $mail = $build();

        return response()->json(['data' => [
            'key' => $key,
            'subject' => $mail->subject,
            'html' => (string) $mail->render(),
        ]]);
    }

    private function isCustomizable(string $key): bool
    {
        return array_key_exists($key, (array) config('email_templates'));
    }

    /**
     * @return array{label: string, category: string, trigger: string, placeholders: array<string,string>, default: array<string,mixed>}
     */
    private function meta(string $key): array
    {
        $fromConfig = config("email_templates.{$key}");
        if ($fromConfig !== null) {
            return $fromConfig;
        }

        // Framework-only entries, not in config('email_templates') (not customizable).
        return match ($key) {
            'verify_email' => ['label' => 'Verify email', 'category' => 'Auth', 'trigger' => 'User registers', 'placeholders' => [], 'default' => []],
            'password_reset' => ['label' => 'Password reset', 'category' => 'Auth', 'trigger' => 'User requests a password reset', 'placeholders' => [], 'default' => []],
            default => ['label' => $key, 'category' => 'Other', 'trigger' => '', 'placeholders' => [], 'default' => []],
        };
    }

    /**
     * @return array<string, callable(): MailMessage>
     */
    private function builders(): array
    {
        $user = (new User)->forceFill(['id' => 999999, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'preview@mahadum360.com']);

        $plan = (new Plan)->forceFill(['id' => 1, 'name' => 'Family Plus', 'price_minor' => 250_000, 'interval' => 'monthly']);

        $subscription = (new Subscription)->forceFill(['id' => 1, 'renews_at' => now()->addDays(5)]);
        $subscription->setRelation('plan', $plan);

        $telco = (new TelcoSubscription)->forceFill(['id' => 1, 'daily_amount_minor' => 5_000, 'operator' => 'MTN']);
        $telco->setRelation('subscription', $subscription);

        $payout = (new Payout)->forceFill(['id' => 1, 'amount_minor' => 1_500_000, 'method' => 'bank_transfer']);

        $promo = (new PromoCode)->forceFill(['id' => 1, 'code' => 'WELCOME25']);

        $ticket = (new SupportTicket)->forceFill(['id' => 1, 'subject' => 'Trouble downloading a lesson']);

        $organization = (new Organization)->forceFill(['id' => 1, 'name' => 'Bright Future Academy']);

        $chore = (new Chore)->forceFill(['id' => 1, 'title' => 'Practice 10 Yoruba greetings']);

        $submission = new AssignmentSubmission;

        $invoice = (new Invoice)->forceFill(['id' => 1, 'amount_minor' => 4_500_000]);
        $invoice->setRelation('organization', $organization);

        return [
            'welcome' => fn () => (new WelcomeEmail)->toMail($user),
            'verify_email' => fn () => (new VerifyEmail)->toMail($user),
            'password_reset' => fn () => (new ResetPassword('preview-token'))->toMail($user),
            'login_alert' => fn () => (new NewDeviceAlert('102.89.23.14', 'Chrome on Windows'))->toMail($user),
            'wallet_funded' => fn () => (new WalletFunded(500_000))->toMail($user),
            'subscription_activated' => fn () => (new SubscriptionActivated($subscription))->toMail($user),
            'payment_failed' => fn () => (new PaymentFailed($subscription))->toMail($user),
            'subscription_renewal_reminder' => fn () => (new SubscriptionRenewalReminder($subscription))->toMail($user),
            'promo_redeemed' => fn () => (new PromoRedeemed($promo, $subscription))->toMail($user),
            'telco_billing_receipt' => fn () => (new TelcoBillingReceipt($telco))->toMail($user),
            'invoice_paid' => fn () => (new InvoiceReceipt($invoice))->toMail($user),
            'payout_approved' => fn () => (new PayoutApproved($payout))->toMail($user),
            'organization_seat_assigned' => fn () => (new OrganizationSeatAssigned($organization, 'school_admin'))->toMail($user),
            'chore_approved' => fn () => (new ChoreApproved($chore, 50))->toMail($user),
            'assignment_approved' => fn () => (new AssignmentApproved($submission, 30))->toMail($user),
            'support_reply' => fn () => (new SupportReply($ticket, 'Thanks for reaching out — try re-downloading the lesson from the offline tab; that should clear it up.'))->toMail($user),
        ];
    }
}

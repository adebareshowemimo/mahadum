<?php

namespace App\Providers;

use App\Services\EmailBranding;
use App\Services\MailConfiguration;
use App\Services\PaymentConfiguration;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Encrypted admin-managed integration overrides must be applied in web,
        // CLI and queue-worker processes. Environment values remain fallbacks.
        try {
            if (Schema::hasTable('settings')) {
                app(MailConfiguration::class)->apply();
                app(PaymentConfiguration::class)->apply();

                Queue::before(function (JobProcessing $event): void {
                    app(MailConfiguration::class)->apply();
                    app(PaymentConfiguration::class)->apply();
                    app('mail.manager')->purge('smtp');
                });
            }
        } catch (\Throwable) {
            // Deployment commands may bootstrap before the database exists;
            // environment configuration remains the safe fallback in that case.
        }

        // Default API limiter (per-token, fallback per-IP).
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip()));

        // Tighter limiter for auth endpoints (login/register/reset).
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // Password-reset emails link to the web SPA's reset screen, carrying the
        // token + email the POST /auth/password/reset endpoint expects.
        $resetUrl = fn ($notifiable, string $token) => sprintf(
            '%s/reset-password?token=%s&email=%s',
            rtrim((string) config('app.frontend_url'), '/'),
            $token,
            urlencode($notifiable->getEmailForPasswordReset()),
        );
        ResetPassword::createUrlUsing($resetUrl);

        // Framework-managed authentication messages still share the global
        // email header/footer even though their message copy is read-only.
        VerifyEmail::toMailUsing(fn ($notifiable, string $verificationUrl) => app(EmailBranding::class)->apply(
            (new MailMessage)
                ->subject(__('Verify your email address'))
                ->line(__('Please click the button below to verify your email address.'))
                ->action(__('Verify Email Address'), $verificationUrl)
                ->line(__('If you did not create an account, no further action is required.')),
        ));
        ResetPassword::toMailUsing(fn ($notifiable, string $token) => app(EmailBranding::class)->apply(
            (new MailMessage)
                ->subject(__('Reset your password'))
                ->line(__('You are receiving this email because we received a password reset request for your account.'))
                ->action(__('Reset Password'), $resetUrl($notifiable, $token))
                ->line(__('This password reset link will expire in :count minutes.', ['count' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire')]))
                ->line(__('If you did not request a password reset, no further action is required.')),
        ));

        // NB: App\Listeners\RecordSentEmail (MessageSent → email log, §7) is
        // auto-discovered from app/Listeners; no explicit registration needed.
    }
}

<?php

namespace App\Providers;

use App\Services\MailConfiguration;
use App\Services\PaymentConfiguration;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
        ResetPassword::createUrlUsing(fn ($notifiable, string $token) => sprintf(
            '%s/reset-password?token=%s&email=%s',
            rtrim((string) config('app.frontend_url'), '/'),
            $token,
            urlencode($notifiable->getEmailForPasswordReset()),
        ));

        // NB: App\Listeners\RecordSentEmail (MessageSent → email log, §7) is
        // auto-discovered from app/Listeners; no explicit registration needed.
    }
}

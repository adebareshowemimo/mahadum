<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MailConfiguration
{
    public function __construct(private IntegrationSettings $settings) {}

    /** Apply DB overrides to Laravel's runtime mail configuration. */
    public function apply(): void
    {
        config([
            'mail.default' => $this->settings->get('mail.mailer', config('mail.default')),
            'mail.mailers.smtp.url' => null,
            'mail.mailers.smtp.host' => $this->settings->get('mail.host', config('mail.mailers.smtp.host')),
            'mail.mailers.smtp.port' => (int) $this->settings->get('mail.port', config('mail.mailers.smtp.port')),
            'mail.mailers.smtp.scheme' => $this->nullable($this->settings->get('mail.scheme', config('mail.mailers.smtp.scheme'))),
            'mail.mailers.smtp.username' => $this->nullable($this->settings->get('mail.username', config('mail.mailers.smtp.username'))),
            'mail.mailers.smtp.password' => $this->nullable($this->settings->get('mail.password', config('mail.mailers.smtp.password'))),
            'mail.from.address' => $this->settings->get('mail.from_address', config('mail.from.address')),
            'mail.from.name' => $this->settings->get('mail.from_name', config('mail.from.name')),
        ]);
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $this->apply();
        $mailer = (string) config('mail.default');
        $username = config('mail.mailers.smtp.username');
        $password = config('mail.mailers.smtp.password');

        return [
            'mailer' => $mailer,
            'host' => (string) config('mail.mailers.smtp.host'),
            'port' => (int) config('mail.mailers.smtp.port'),
            'scheme' => config('mail.mailers.smtp.scheme'),
            'username' => $username,
            'password_set' => filled($password),
            'from_address' => (string) config('mail.from.address'),
            'from_name' => (string) config('mail.from.name'),
            'delivery_enabled' => ! in_array($mailer, ['log', 'array'], true),
            'queue_connection' => (string) config('queue.default'),
            'pending_jobs' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0,
            'failed_jobs' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
        ];
    }

    private function nullable(mixed $value): ?string
    {
        return blank($value) ? null : (string) $value;
    }
}

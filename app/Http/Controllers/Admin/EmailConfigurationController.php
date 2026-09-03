<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ConfigurationTestMail;
use App\Services\AuditLogger;
use App\Services\IntegrationSettings;
use App\Services\MailConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class EmailConfigurationController extends Controller
{
    public function __construct(
        private IntegrationSettings $settings,
        private MailConfiguration $mail,
        private AuditLogger $audit,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->mail->status()]);
    }

    public function update(Request $request): JsonResponse
    {
        $clean = $request->validate([
            'mailer' => ['required', 'in:smtp,log'],
            'host' => ['required_if:mailer,smtp', 'nullable', 'string', 'max:255'],
            'port' => ['required_if:mailer,smtp', 'nullable', 'integer', 'min:1', 'max:65535'],
            'scheme' => ['nullable', 'in:tls,ssl'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:1000'],
            'from_address' => ['required', 'email:rfc', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
        ]);

        $values = [
            'mail.mailer' => $clean['mailer'],
            'mail.host' => $clean['host'] ?? '',
            'mail.port' => $clean['port'] ?? 587,
            'mail.scheme' => $clean['scheme'] ?? '',
            'mail.username' => $clean['username'] ?? '',
            'mail.from_address' => $clean['from_address'],
            'mail.from_name' => $clean['from_name'],
        ];
        if (filled($clean['password'] ?? null)) {
            $values['mail.password'] = $clean['password'];
        }

        $before = $this->mail->status();
        $this->settings->set($values);
        $this->mail->apply();
        app('mail.manager')->purge('smtp');
        $after = $this->mail->status();

        $this->audit->record('email.configuration_updated', null, $this->auditSafe($before), $this->auditSafe($after));

        return response()->json(['data' => $after]);
    }

    public function test(Request $request): JsonResponse
    {
        $clean = $request->validate(['email' => ['required', 'email:rfc', 'max:255']]);
        $status = $this->mail->status();

        if (! $status['delivery_enabled']) {
            return response()->json(['message' => 'Email delivery is disabled. Save an SMTP configuration before testing.'], 422);
        }

        try {
            app('mail.manager')->purge('smtp');
            Mail::mailer((string) $status['mailer'])->to($clean['email'])->send(new ConfigurationTestMail);

            $this->audit->record('email.configuration_tested', null, [], ['success' => true]);

            return response()->json(['data' => ['ok' => true, 'message' => 'Test email sent successfully. Check the destination inbox and spam folder.']]);
        } catch (\Throwable $exception) {
            report($exception);
            $this->audit->record('email.configuration_tested', null, [], ['success' => false]);

            return response()->json([
                'message' => 'The SMTP server rejected the test: '.$exception->getMessage(),
            ], 422);
        }
    }

    /** @param array<string, mixed> $status */
    private function auditSafe(array $status): array
    {
        return collect($status)->except(['username'])->all();
    }
}

<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-managed presentation shared by transactional and campaign emails.
 * Content is kept separate from branding so a template can opt out without
 * losing its message, action, or campaign unsubscribe controls.
 */
class EmailBranding
{
    private const CACHE_KEY = 'email.branding';

    private const PREFIX = 'email_branding.';

    public function __construct(private EmailHtmlSanitizer $sanitizer) {}

    /** @return array{enabled: bool, header_enabled: bool, footer_enabled: bool, header_html: string, footer_html: string} */
    public function settings(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()
            ->where('key', 'like', self::PREFIX.'%')
            ->pluck('value', 'key')
            ->mapWithKeys(fn (?string $value, string $key) => [substr($key, strlen(self::PREFIX)) => (string) $value])
            ->all());

        return [
            'enabled' => $this->bool($stored['enabled'] ?? null, true),
            'header_enabled' => $this->bool($stored['header_enabled'] ?? null, true),
            'footer_enabled' => $this->bool($stored['footer_enabled'] ?? null, true),
            'header_html' => $stored['header_html'] ?? $this->defaultHeaderHtml(),
            'footer_html' => $stored['footer_html'] ?? $this->defaultFooterHtml(),
        ];
    }

    /** @param array{enabled: bool, header_enabled: bool, footer_enabled: bool, header_html: string, footer_html: string} $values */
    public function set(array $values): void
    {
        foreach ($values as $key => $value) {
            $stored = is_bool($value) ? ($value ? '1' : '0') : $this->sanitizer->sanitize($value);
            Setting::updateOrCreate(['key' => self::PREFIX.$key], ['value' => $stored]);
        }

        Cache::forget(self::CACHE_KEY);
    }

    /** @return array{show_header: bool, show_footer: bool, header_html: string, footer_html: string} */
    public function presentation(bool $includeBranding = true): array
    {
        $settings = $this->settings();
        $enabled = $includeBranding && $settings['enabled'];

        return [
            'show_header' => $enabled && $settings['header_enabled'],
            'show_footer' => $enabled && $settings['footer_enabled'],
            'header_html' => $this->sanitizer->sanitize($settings['header_html']),
            'footer_html' => $this->sanitizer->sanitize($settings['footer_html']),
        ];
    }

    public function apply(MailMessage $mail, bool $includeBranding = true): MailMessage
    {
        $html = '';
        if (filled($mail->greeting)) {
            $html .= '<h2>'.e((string) $mail->greeting).'</h2>';
        }

        foreach ($mail->introLines as $line) {
            $html .= '<p>'.nl2br(e((string) $line)).'</p>';
        }

        if (filled($mail->actionText) && filled($mail->actionUrl)) {
            $html .= sprintf(
                '<p><a href="%s" style="display:inline-block;background:#c7952b;color:#111827;padding:12px 20px;border-radius:8px;text-decoration:none;font-weight:700">%s</a></p>',
                e((string) $mail->actionUrl),
                e((string) $mail->actionText),
            );
        }

        foreach ($mail->outroLines as $line) {
            $html .= '<p>'.nl2br(e((string) $line)).'</p>';
        }

        if (filled($mail->salutation)) {
            $html .= '<p>'.nl2br(e((string) $mail->salutation)).'</p>';
        }

        return $mail->view('emails.custom-html', [
            'html' => $this->sanitizer->sanitize($html),
            'includeBranding' => $includeBranding,
        ]);
    }

    private function defaultHeaderHtml(): string
    {
        $logo = config('brand.logo_url')
            ? '<img src="'.e((string) config('brand.logo_url')).'" alt="'.e((string) config('brand.name')).'" style="max-height:52px;max-width:220px;">'
            : e((string) config('brand.name'));

        return '<a href="'.e((string) config('brand.url')).'" style="color:#d7ad45;text-decoration:none;font-size:24px;font-weight:800;letter-spacing:.04em;">'.$logo.'</a>';
    }

    private function defaultFooterHtml(): string
    {
        return e((string) config('brand.tagline')).'<br>© '.date('Y').' '.e((string) config('brand.name')).'. All rights reserved.';
    }

    private function bool(?string $value, bool $default): bool
    {
        return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}

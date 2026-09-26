<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\EmailBranding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailBrandingController extends Controller
{
    public function __construct(
        private EmailBranding $branding,
        private AuditLogger $audit,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->response()]);
    }

    public function update(Request $request): JsonResponse
    {
        $values = $request->validate([
            'enabled' => ['required', 'boolean'],
            'header_enabled' => ['required', 'boolean'],
            'footer_enabled' => ['required', 'boolean'],
            'header_html' => ['required', 'string', 'max:100000'],
            'footer_html' => ['required', 'string', 'max:100000'],
        ]);

        $before = $this->branding->settings();
        $this->branding->set($values);
        $this->audit->record('email_branding.updated', null, $before, $values);

        return response()->json(['data' => $this->response()]);
    }

    /** @return array<string, mixed> */
    private function response(): array
    {
        $settings = $this->branding->settings();

        return [
            ...$settings,
            'preview_html' => view('emails.custom-html', [
                'html' => '<h2>Your email heading</h2><p>This preview shows how transactional and campaign content appears with the saved branding.</p><p><a href="#" style="display:inline-block;background:#c7952b;color:#111827;padding:12px 20px;border-radius:8px;text-decoration:none;font-weight:700">Example action</a></p>',
                'includeBranding' => true,
            ])->render(),
        ];
    }
}

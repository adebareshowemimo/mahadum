<?php

namespace App\Http\Controllers;

use App\Http\Resources\LanguageResource;
use App\Models\Language;
use App\Services\Settings;
use Illuminate\Http\JsonResponse;

class ConfigController extends Controller
{
    public function __construct(private Settings $settings) {}

    /**
     * Launch-time bootstrap: force-update gate, feature flags, CDN base, languages.
     * Public — no auth required.
     */
    public function show(): JsonResponse
    {
        return response()->json(['data' => [
            'min_supported_version' => [
                'ios' => config('app_versions.min.ios'),
                'android' => config('app_versions.min.android'),
            ],
            // Age (years) below which a learner is a minor needing verifiable
            // parental consent — admin-configurable via system settings.
            // Drives the sign-up age gate on the web SPA.
            'digital_age' => $this->settings->get('compliance.minor_age', config('compliance.minor_age')),
            'feature_flags' => [
                'telco_billing' => $this->settings->get('feature.telco_billing'),
                'ai_pronunciation' => $this->settings->get('feature.ai_pronunciation'),
            ],
            'cdn_base' => config('platform.cdn_base'),
            'languages' => LanguageResource::collection(
                Language::where('is_active', true)->orderBy('position')->orderBy('name')->get()
            ),
        ]]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\AdvertPlacement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Public advert delivery — no auth required, since logged-out visitors and
 * free-tier users both see banner adverts. Premium users are gated out on the
 * frontend (entitlements.ads), not here: this endpoint just answers "what's
 * currently live for this position."
 */
class AdvertController extends Controller
{
    public function active(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'position' => ['required', Rule::in(['leaderboard', 'inline', 'profile_data_topup'])],
        ]);

        $advert = AdvertPlacement::currentFor($validated['position']);

        if (! $advert) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => [
            'id' => $advert->id,
            'image_url' => $this->resolveUrl((string) $advert->mediaAsset->url),
            'target_url' => $advert->target_url,
            'position' => $advert->position,
            'size' => $advert->size,
        ]]);
    }

    public function impression(AdvertPlacement $advertPlacement): JsonResponse
    {
        $advertPlacement->increment('impressions_count');

        return response()->json(null, 204);
    }

    public function click(AdvertPlacement $advertPlacement): JsonResponse
    {
        $advertPlacement->increment('clicks_count');

        return response()->json(null, 204);
    }

    /** URL for a stored path (already-absolute URLs pass through). */
    private function resolveUrl(string $url): string
    {
        return str_starts_with($url, 'http') ? $url : Storage::disk('public')->url($url);
    }
}

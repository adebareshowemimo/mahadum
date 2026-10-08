<?php

namespace App\Services\Ads;

use App\Models\AdImpression;
use App\Models\MediaAsset;
use App\Services\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Managed playback evidence, not third-party ad-network attestation. */
class ManagedVideoGateway implements AdGateway
{
    public function __construct(private Settings $settings) {}

    public function asset(): ?MediaAsset
    {
        if (! $this->settings->get('ads.managed_video_enabled')) {
            return null;
        }
        $asset = MediaAsset::find((int) $this->settings->get('ads.managed_video_asset_id'));
        if (! $asset || $asset->type !== 'video' || $this->duration($asset) < 1 || $this->duration($asset) > 300
            || str_contains($asset->url, '..') || str_starts_with($asset->url, '/') || str_contains($asset->url, '://')
            || ! Storage::disk('public')->exists($asset->url)) {
            return null;
        }

        return $asset;
    }

    public function available(string $placement): bool
    {
        return $placement === 'rewarded_heart' && $this->asset() !== null;
    }

    public function initialize(AdImpression $impression): array
    {
        $asset = $this->asset();
        abort_unless($asset !== null, 422, 'No rewarded video is available.');
        $impression->update([
            'managed_video' => [
                'asset_id' => $asset->id, 'fingerprint' => $this->fingerprint($asset),
                'duration' => $this->duration($asset), 'position' => 0,
                'reported_at' => now()->toISOString(),
            ],
            'expires_at' => now()->addMinutes(30),
        ]);

        return ['url' => Storage::disk('public')->url($asset->url), 'duration_seconds' => $this->duration($asset)];
    }

    private function duration(MediaAsset $asset): int
    {
        return (int) ($asset->duration_seconds ?? $this->settings->get('ads.managed_video_duration_seconds'));
    }

    private function fingerprint(MediaAsset $asset): string
    {
        return hash('sha256', $asset->id.'|'.$asset->url.'|'.$this->duration($asset).'|'.$asset->updated_at?->toISOString());
    }

    private function valid(AdImpression $impression): bool
    {
        $asset = $this->asset();
        $learner = $impression->learnerProfile;

        return $impression->placement === 'rewarded_heart' && $impression->coppa_passed
            && $impression->ad_ref !== null && $impression->expires_at?->isFuture()
            && $asset !== null && ($impression->managed_video['fingerprint'] ?? null) === $this->fingerprint($asset)
            && $learner?->date_of_birth !== null
            && Carbon::parse($learner->date_of_birth)->age >= (int) $this->settings->get('compliance.minor_age');
    }

    public function progress(AdImpression $impression, float $position): array
    {
        return DB::transaction(function () use ($impression, $position) {
            $locked = AdImpression::lockForUpdate()->findOrFail($impression->id);
            abort_unless($this->valid($locked) && $locked->consumed_at === null, 422, 'This video session is no longer available. Please start again.');
            $state = $locked->managed_video;
            $position = round($position, 3);
            $delta = $position - $state['position'];
            $elapsed = Carbon::parse($state['reported_at'])->diffInMilliseconds(now()) / 1000;
            // Small contiguous samples only: elapsed time alone earns nothing.
            abort_unless($delta >= 0 && $delta <= 5 && $delta <= $elapsed + 0.25 && $position <= $state['duration'], 422, 'Playback could not be verified. Please restart the video without seeking.');
            if ($delta > 0) {
                $state['position'] = $position;
                $state['reported_at'] = now()->toISOString();
                $locked->update(['managed_video' => $state]);
            }

            return ['position_seconds' => $state['position']];
        });
    }

    public function verifyReward(string $adRef): bool
    {
        $impression = AdImpression::whereAdRef($adRef)->first();
        if (! $impression || ! $this->valid($impression)) {
            return false;
        }
        $state = $impression->managed_video;

        return $state['position'] >= $state['duration'] - 0.25
            && $impression->created_at->diffInMilliseconds(now()) / 1000 >= $state['duration'] - 0.25;
    }
}

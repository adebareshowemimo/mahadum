<?php

namespace Database\Seeders;

use App\Models\AdvertPlacement;
use App\Models\MediaAsset;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class AdvertPlacementSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAdvert(
            name: 'Demo family learning leaderboard',
            position: 'leaderboard',
            size: '970x90',
            path: 'adverts/demo-family-learning-leaderboard.svg',
            targetUrl: '/pricing',
            svg: $this->leaderboardSvg(),
        );

        $this->seedAdvert(
            name: 'Demo culture lesson inline advert',
            position: 'inline',
            size: '300x250',
            path: 'adverts/demo-culture-lesson-inline.svg',
            targetUrl: '/learn',
            svg: $this->inlineSvg(),
        );
    }

    private function seedAdvert(
        string $name,
        string $position,
        string $size,
        string $path,
        string $targetUrl,
        string $svg,
    ): void {
        Storage::disk('public')->put($path, $svg);

        $mediaAsset = MediaAsset::updateOrCreate(
            ['url' => $path],
            ['type' => 'image'],
        );

        AdvertPlacement::updateOrCreate(
            ['name' => $name],
            [
                'position' => $position,
                'size' => $size,
                'media_asset_id' => $mediaAsset->id,
                'target_url' => $targetUrl,
                'is_active' => true,
                'activated_at' => now(),
                'starts_at' => null,
                'ends_at' => null,
            ],
        );
    }

    private function leaderboardSvg(): string
    {
        return <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="970" height="90" viewBox="0 0 970 90" role="img" aria-labelledby="title description">
  <title id="title">Learn Nigerian languages together</title>
  <desc id="description">Demo leaderboard advert for Mahadum360 family learning.</desc>
  <defs><linearGradient id="bg" x1="0" x2="1"><stop stop-color="#064e3b"/><stop offset="1" stop-color="#0f766e"/></linearGradient></defs>
  <rect width="970" height="90" rx="16" fill="url(#bg)"/>
  <circle cx="58" cy="45" r="28" fill="#fbbf24"/><text x="58" y="53" text-anchor="middle" font-family="Arial, sans-serif" font-size="24" font-weight="700" fill="#064e3b">M360</text>
  <text x="105" y="38" font-family="Arial, sans-serif" font-size="24" font-weight="700" fill="white">Learn Nigerian languages together</text>
  <text x="105" y="64" font-family="Arial, sans-serif" font-size="15" fill="#ccfbf1">Full learning for every generation — Yoruba, Igbo, Hausa and Pidgin.</text>
  <rect x="796" y="22" width="145" height="46" rx="23" fill="#fbbf24"/><text x="868.5" y="51" text-anchor="middle" font-family="Arial, sans-serif" font-size="16" font-weight="700" fill="#064e3b">Explore plans</text>
</svg>
SVG;
    }

    private function inlineSvg(): string
    {
        return <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="300" height="250" viewBox="0 0 300 250" role="img" aria-labelledby="title description">
  <title id="title">Live the culture</title>
  <desc id="description">Demo inline advert for a Mahadum360 culture lesson.</desc>
  <rect width="300" height="250" rx="20" fill="#fff7ed"/>
  <path d="M0 0h300v72H0z" fill="#9a3412"/><circle cx="150" cy="72" r="42" fill="#fbbf24"/>
  <text x="150" y="82" text-anchor="middle" font-family="Arial, sans-serif" font-size="25" font-weight="700" fill="#7c2d12">M360</text>
  <text x="150" y="139" text-anchor="middle" font-family="Arial, sans-serif" font-size="25" font-weight="700" fill="#7c2d12">Live the culture.</text>
  <text x="150" y="168" text-anchor="middle" font-family="Arial, sans-serif" font-size="14" fill="#7c2d12">Try a five-minute language lesson</text>
  <rect x="67" y="190" width="166" height="40" rx="20" fill="#0f766e"/><text x="150" y="216" text-anchor="middle" font-family="Arial, sans-serif" font-size="15" font-weight="700" fill="white">Start learning</text>
</svg>
SVG;
    }
}

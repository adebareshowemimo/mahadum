<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Encrypted DB-backed configuration for operational integrations. Values are
 * never exposed through the generic Settings API and are encrypted at rest
 * with APP_KEY. Environment configuration remains the fallback.
 */
class IntegrationSettings
{
    private const CACHE_KEY = 'integration.settings';

    private const PREFIX = 'integration.';

    public function get(string $key, mixed $fallback = null): mixed
    {
        $stored = $this->overrides()[$key] ?? null;
        if ($stored === null) {
            return $fallback;
        }

        try {
            $decoded = json_decode(Crypt::decryptString($stored), true, flags: JSON_THROW_ON_ERROR);

            return $decoded['value'] ?? $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->overrides());
    }

    /** @param array<string, mixed> $values */
    public function set(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(
                ['key' => self::PREFIX.$key],
                ['value' => Crypt::encryptString(json_encode(['value' => $value], JSON_THROW_ON_ERROR))],
            );
        }

        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, string> */
    private function overrides(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()
            ->where('key', 'like', self::PREFIX.'%')
            ->pluck('value', 'key')
            ->mapWithKeys(fn (?string $value, string $key) => [substr($key, strlen(self::PREFIX)) => (string) $value])
            ->all());
    }
}

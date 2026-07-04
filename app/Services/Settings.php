<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * DB-backed platform settings with a config-defined whitelist (config/settings.php).
 * Reads return the stored override cast to the schema type, else the schema default —
 * so callers get a typed, always-present value. Writes are limited to known keys and
 * bust a single cached map so reads stay cheap.
 */
class Settings
{
    private const CACHE_KEY = 'platform.settings';

    /**
     * Typed value for a key: DB override → schema default → provided fallback.
     */
    public function get(string $key, mixed $fallback = null): mixed
    {
        $overrides = $this->overrides();

        if (array_key_exists($key, $overrides)) {
            return $this->cast($key, $overrides[$key]);
        }

        $def = $this->definition($key);

        return $def['default'] ?? $fallback;
    }

    /**
     * Persist overrides for known keys only. Unknown keys are ignored.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed> the applied (key → typed value) map
     */
    public function set(array $values): array
    {
        $applied = [];

        foreach ($values as $key => $value) {
            if ($this->definition($key) === null) {
                continue; // not in the whitelist
            }
            $stored = $this->serialize($key, $value);
            Setting::updateOrCreate(['key' => $key], ['value' => $stored]);
            $applied[$key] = $this->cast($key, $stored);
        }

        Cache::forget(self::CACHE_KEY);

        return $applied;
    }

    /**
     * The whole whitelist with each setting's current typed value merged in,
     * grouped for display.
     *
     * @return array<int, array<string, mixed>>
     */
    public function describe(): array
    {
        $groups = [];

        foreach ((array) config('settings.groups') as $groupKey => $group) {
            $settings = [];
            foreach ($group['settings'] as $key => $def) {
                $settings[] = [
                    'key' => $key,
                    'label' => $def['label'],
                    'help' => $def['help'] ?? null,
                    'type' => $def['type'],
                    'min' => $def['min'] ?? null,
                    'max' => $def['max'] ?? null,
                    'value' => $this->get($key),
                ];
            }
            $groups[] = ['key' => $groupKey, 'label' => $group['label'], 'settings' => $settings];
        }

        return $groups;
    }

    /**
     * @return array<string, string|null> key → raw stored value
     */
    private function overrides(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => Setting::pluck('value', 'key')->all());
    }

    /**
     * The schema definition (label/type/min/max/default) for a whitelisted key,
     * or null if the key is not editable.
     *
     * @return array<string, mixed>|null
     */
    public function definition(string $key): ?array
    {
        foreach ((array) config('settings.groups') as $group) {
            if (isset($group['settings'][$key])) {
                return $group['settings'][$key];
            }
        }

        return null;
    }

    private function cast(string $key, mixed $raw): mixed
    {
        return match ($this->definition($key)['type'] ?? 'string') {
            'int' => (int) $raw,
            'bool' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            default => (string) $raw,
        };
    }

    private function serialize(string $key, mixed $value): string
    {
        return match ($this->definition($key)['type'] ?? 'string') {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            'int' => (string) (int) $value,
            default => (string) $value,
        };
    }
}

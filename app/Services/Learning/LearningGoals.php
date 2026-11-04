<?php

namespace App\Services\Learning;

use App\Services\Settings;

class LearningGoals
{
    public const KEYS = ['grandparents', 'travel', 'school', 'business'];

    public function __construct(private Settings $settings) {}

    /** @return array<int, array{key: string, label: string, message: string, keywords: array<int, string>}> */
    public function options(): array
    {
        $options = [];
        foreach (self::KEYS as $key) {
            if (! $this->settings->get("learning_goal.$key.enabled")) {
                continue;
            }
            $keywords = array_values(array_filter(array_map(
                fn ($word) => trim(mb_strtolower($word)),
                explode(',', (string) $this->settings->get("learning_goal.$key.keywords")),
            )));
            $options[] = [
                'key' => $key,
                'label' => (string) $this->settings->get("learning_goal.$key.label"),
                'message' => (string) $this->settings->get("learning_goal.$key.message"),
                'keywords' => $keywords,
            ];
        }

        return $options;
    }

    /** @return array{key: string, label: string, message: string, keywords: array<int, string>}|null */
    public function find(?string $key): ?array
    {
        foreach ($this->options() as $option) {
            if ($option['key'] === $key) {
                return $option;
            }
        }

        return null;
    }
}

<?php

namespace App\Services\Learning;

class VideoCoverage
{
    /** @return array<int, array{float, float}> */
    public static function merge(array $ranges): array
    {
        usort($ranges, fn ($a, $b) => $a[0] <=> $b[0]);
        $merged = [];
        foreach ($ranges as $range) {
            $start = round((float) $range[0], 3);
            $end = round((float) $range[1], 3);
            if ($end <= $start) {
                continue;
            }
            $last = count($merged) - 1;
            if ($last >= 0 && $start <= $merged[$last][1] + 0.05) {
                $merged[$last][1] = max($merged[$last][1], $end);
            } else {
                $merged[] = [$start, $end];
            }
        }

        return $merged;
    }

    public static function complete(array $ranges, float $duration): bool
    {
        $merged = self::merge($ranges);

        return $duration > 0 && count($merged) === 1
            && $merged[0][0] <= 0.25 && $merged[0][1] >= $duration - 0.25;
    }
}

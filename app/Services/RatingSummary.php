<?php

namespace App\Services;

/**
 * Summarizes a list of 1–5 scores. Pure logic for unit testing.
 */
class RatingSummary
{
    /**
     * @param  array<int, int>  $scores
     * @return array{count: int, average: float|null, distribution: array<int, int>, spread: int|null}
     */
    public static function of(array $scores): array
    {
        $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

        foreach ($scores as $score) {
            if (isset($distribution[$score])) {
                $distribution[$score]++;
            }
        }

        $valid = array_values(array_filter($scores, fn (int $s) => $s >= 1 && $s <= 5));
        $count = count($valid);

        return [
            'count' => $count,
            'average' => $count ? round(array_sum($valid) / $count, 1) : null,
            'distribution' => $distribution,
            'spread' => $count ? max($valid) - min($valid) : null,
        ];
    }
}

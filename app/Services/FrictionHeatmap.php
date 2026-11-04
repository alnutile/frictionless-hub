<?php

namespace App\Services;

/**
 * Builds a stage × dimension grid of friction counts and average pain.
 *
 * Pure logic over plain arrays so it can be unit tested without the framework.
 */
class FrictionHeatmap
{
    /**
     * @param  array<int, array{id: int, name: string}>  $stages
     * @param  array<int, array{id: int, name: string}>  $dimensions
     * @param  array<int, array{stage_id: int|null, pain: int|null, dimension_ids: array<int, int>}>  $points
     * @return array{
     *     rows: array<int, array{stage_id: int, stage: string, total: int, cells: array<int, array{dimension_id: int, count: int, avg_pain: float|null}>}>,
     *     max_count: int,
     *     unassigned: int
     * }
     */
    public static function build(array $stages, array $dimensions, array $points): array
    {
        $tally = [];
        $totals = [];
        $unassigned = 0;

        foreach ($points as $point) {
            $stageId = $point['stage_id'];

            if ($stageId === null) {
                $unassigned++;

                continue;
            }

            $totals[$stageId] = ($totals[$stageId] ?? 0) + 1;

            foreach (array_unique($point['dimension_ids']) as $dimensionId) {
                $cell = $tally[$stageId][$dimensionId] ?? ['count' => 0, 'pain_sum' => 0, 'pain_n' => 0];
                $cell['count']++;

                if ($point['pain'] !== null) {
                    $cell['pain_sum'] += $point['pain'];
                    $cell['pain_n']++;
                }

                $tally[$stageId][$dimensionId] = $cell;
            }
        }

        $maxCount = 0;
        $rows = [];

        foreach ($stages as $stage) {
            $cells = [];

            foreach ($dimensions as $dimension) {
                $cell = $tally[$stage['id']][$dimension['id']] ?? null;
                $count = $cell['count'] ?? 0;
                $maxCount = max($maxCount, $count);

                $cells[] = [
                    'dimension_id' => $dimension['id'],
                    'count' => $count,
                    'avg_pain' => ($cell !== null && $cell['pain_n'] > 0)
                        ? round($cell['pain_sum'] / $cell['pain_n'], 1)
                        : null,
                ];
            }

            $rows[] = [
                'stage_id' => $stage['id'],
                'stage' => $stage['name'],
                'total' => $totals[$stage['id']] ?? 0,
                'cells' => $cells,
            ];
        }

        return ['rows' => $rows, 'max_count' => $maxCount, 'unassigned' => $unassigned];
    }
}

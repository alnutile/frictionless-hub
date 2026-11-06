<?php

namespace App\Services;

/**
 * Builds a stage × dimension grid of friction counts and average pain.
 *
 * Every point is counted in exactly one row: its stage, or a "No stage" row at the
 * bottom. The row totals always add up to `total`, so the heatmap, the friction
 * list and the overview count agree.
 *
 * Pure logic over plain arrays so it can be unit tested without the framework.
 */
class FrictionHeatmap
{
    public const NO_STAGE_LABEL = 'No stage';

    /**
     * @param  array<int, array{id: int, name: string}>  $stages
     * @param  array<int, array{id: int, name: string}>  $dimensions
     * @param  array<int, array{stage_id: int|null, pain: int|null, dimension_ids: array<int, int>}>  $points
     * @return array{
     *     rows: array<int, array{stage_id: int|null, stage: string, total: int, cells: array<int, array{dimension_id: int, count: int, avg_pain: float|null}>}>,
     *     max_count: int,
     *     total: int,
     *     unassigned: int
     * }
     */
    public static function build(array $stages, array $dimensions, array $points): array
    {
        $knownStageIds = array_column($stages, 'id');
        $tally = [];
        $totals = [];

        foreach ($points as $point) {
            // A stage that no longer exists counts as "no stage" rather than disappearing.
            $rowKey = in_array($point['stage_id'], $knownStageIds, true) ? $point['stage_id'] : 'none';

            $totals[$rowKey] = ($totals[$rowKey] ?? 0) + 1;

            foreach (array_unique($point['dimension_ids']) as $dimensionId) {
                $cell = $tally[$rowKey][$dimensionId] ?? ['count' => 0, 'pain_sum' => 0, 'pain_n' => 0];
                $cell['count']++;

                if ($point['pain'] !== null) {
                    $cell['pain_sum'] += $point['pain'];
                    $cell['pain_n']++;
                }

                $tally[$rowKey][$dimensionId] = $cell;
            }
        }

        $rowSpecs = array_map(fn (array $stage) => ['key' => $stage['id'], 'stage_id' => $stage['id'], 'stage' => $stage['name']], $stages);

        if (($totals['none'] ?? 0) > 0) {
            $rowSpecs[] = ['key' => 'none', 'stage_id' => null, 'stage' => self::NO_STAGE_LABEL];
        }

        $maxCount = 0;
        $rows = [];

        foreach ($rowSpecs as $spec) {
            $cells = [];

            foreach ($dimensions as $dimension) {
                $cell = $tally[$spec['key']][$dimension['id']] ?? null;
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
                'stage_id' => $spec['stage_id'],
                'stage' => $spec['stage'],
                'total' => $totals[$spec['key']] ?? 0,
                'cells' => $cells,
            ];
        }

        return [
            'rows' => $rows,
            'max_count' => $maxCount,
            'total' => count($points),
            'unassigned' => $totals['none'] ?? 0,
        ];
    }
}

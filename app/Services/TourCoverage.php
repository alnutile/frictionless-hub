<?php

namespace App\Services;

/**
 * The listening tour's coverage matrix: work areas down the side, group values across.
 *
 * Totals check: within a work area, every category's counts should add up to the
 * number of people in that area, because each person has exactly one value per
 * category. When they don't, someone is missing a value, and that's reported.
 *
 * Pure logic over plain arrays so it can be unit tested without the framework.
 */
class TourCoverage
{
    public const NO_AREA_LABEL = 'No area';

    /**
     * @param  array<int, array{id: int, name: string}>  $areas
     * @param  array<int, array{id: int, name: string, values: array<int, array{id: int, name: string}>}>  $categories
     * @param  array<int, array{area_value_id: int|null, value_ids: array<int, int>}>  $participants  Only people who count toward coverage
     * @return array{
     *     rows: array<int, array{area_id: int|null, area: string, total: int, counts: array<int, int>}>,
     *     column_totals: array<int, int>,
     *     total: int,
     *     issues: array<int, array{area: string, category: string, missing: int}>,
     *     empty_areas: array<int, string>,
     *     empty_values: array<int, array{category: string, value: string}>,
     *     target: int|null,
     *     remaining: int|null
     * }
     */
    public static function build(array $areas, array $categories, array $participants, ?int $target = null): array
    {
        $areaIds = array_column($areas, 'id');
        $valueIds = [];
        foreach ($categories as $category) {
            foreach ($category['values'] as $value) {
                $valueIds[] = $value['id'];
            }
        }

        $rowTotals = [];
        $cellCounts = [];

        foreach ($participants as $participant) {
            $rowKey = in_array($participant['area_value_id'], $areaIds, true) ? $participant['area_value_id'] : 'none';
            $rowTotals[$rowKey] = ($rowTotals[$rowKey] ?? 0) + 1;

            foreach (array_unique($participant['value_ids']) as $valueId) {
                if (in_array($valueId, $valueIds, true)) {
                    $cellCounts[$rowKey][$valueId] = ($cellCounts[$rowKey][$valueId] ?? 0) + 1;
                }
            }
        }

        $rowSpecs = array_map(fn (array $a) => ['key' => $a['id'], 'area_id' => $a['id'], 'area' => $a['name']], $areas);
        if (($rowTotals['none'] ?? 0) > 0) {
            $rowSpecs[] = ['key' => 'none', 'area_id' => null, 'area' => self::NO_AREA_LABEL];
        }

        $rows = [];
        $columnTotals = array_fill_keys($valueIds, 0);
        $issues = [];

        foreach ($rowSpecs as $spec) {
            $total = $rowTotals[$spec['key']] ?? 0;
            $counts = [];

            foreach ($valueIds as $valueId) {
                $count = $cellCounts[$spec['key']][$valueId] ?? 0;
                $counts[$valueId] = $count;
                $columnTotals[$valueId] += $count;
            }

            foreach ($categories as $category) {
                $assigned = array_sum(array_map(fn (array $v) => $counts[$v['id']] ?? 0, $category['values']));

                if ($total > 0 && $assigned !== $total) {
                    $issues[] = ['area' => $spec['area'], 'category' => $category['name'], 'missing' => $total - $assigned];
                }
            }

            $rows[] = ['area_id' => $spec['area_id'], 'area' => $spec['area'], 'total' => $total, 'counts' => $counts];
        }

        $emptyValues = [];
        foreach ($categories as $category) {
            foreach ($category['values'] as $value) {
                if ($columnTotals[$value['id']] === 0) {
                    $emptyValues[] = ['category' => $category['name'], 'value' => $value['name']];
                }
            }
        }

        $total = count($participants);

        return [
            'rows' => $rows,
            'column_totals' => $columnTotals,
            'total' => $total,
            'issues' => $issues,
            'empty_areas' => array_values(array_map(
                fn (array $a) => $a['name'],
                array_filter($areas, fn (array $a) => ($rowTotals[$a['id']] ?? 0) === 0),
            )),
            'empty_values' => $emptyValues,
            'target' => $target,
            'remaining' => $target !== null ? max(0, $target - $total) : null,
        ];
    }
}

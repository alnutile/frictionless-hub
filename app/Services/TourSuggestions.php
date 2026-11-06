<?php

namespace App\Services;

/**
 * Reads what steps 1–4 captured and points the listening tour at it: the stages that
 * hurt most, who the friction affects, and the components people rated worst.
 *
 * Pure logic over plain arrays so it can be unit tested without the framework.
 */
class TourSuggestions
{
    /**
     * @param  array<int, array{title: string, stage: string|null, pain: int|null, affected: string|null}>  $frictionPoints
     * @param  array<int, array{label: string, average: float|null, count: int, spread: int|null}>  $ratedItems
     * @return array{
     *     has_data: bool,
     *     hot_stages: array<int, array{stage: string, points: int, pain_total: int, top: string}>,
     *     affected_groups: array<int, string>,
     *     worst_rated: array<int, array{label: string, average: float, count: int}>,
     *     disagreements: array<int, array{label: string, spread: int}>
     * }
     */
    public static function build(array $frictionPoints, array $ratedItems): array
    {
        $byStage = [];
        foreach ($frictionPoints as $point) {
            if ($point['stage'] === null) {
                continue;
            }

            $stage = $byStage[$point['stage']] ?? ['stage' => $point['stage'], 'points' => 0, 'pain_total' => 0, 'top' => '', 'top_pain' => -1];
            $stage['points']++;
            $stage['pain_total'] += $point['pain'] ?? 0;

            if (($point['pain'] ?? 0) > $stage['top_pain']) {
                $stage['top'] = $point['title'];
                $stage['top_pain'] = $point['pain'] ?? 0;
            }

            $byStage[$point['stage']] = $stage;
        }

        usort($byStage, fn (array $a, array $b) => [$b['pain_total'], $b['points']] <=> [$a['pain_total'], $a['points']]);
        $hotStages = array_map(
            fn (array $s) => ['stage' => $s['stage'], 'points' => $s['points'], 'pain_total' => $s['pain_total'], 'top' => $s['top']],
            array_slice($byStage, 0, 3),
        );

        $affected = [];
        foreach ($frictionPoints as $point) {
            if (($point['pain'] ?? 0) >= 4 && filled($point['affected'])) {
                $affected[trim((string) $point['affected'])] = true;
            }
        }

        $rated = array_values(array_filter($ratedItems, fn (array $i) => $i['average'] !== null));
        usort($rated, fn (array $a, array $b) => $b['average'] <=> $a['average']);
        $worst = array_map(
            fn (array $i) => ['label' => $i['label'], 'average' => (float) $i['average'], 'count' => $i['count']],
            array_slice(array_filter($rated, fn (array $i) => $i['average'] >= 3), 0, 3),
        );

        $disagreements = array_values(array_map(
            fn (array $i) => ['label' => $i['label'], 'spread' => (int) $i['spread']],
            array_filter($ratedItems, fn (array $i) => ($i['spread'] ?? 0) >= 3),
        ));

        return [
            'has_data' => $hotStages !== [] || $worst !== [],
            'hot_stages' => $hotStages,
            'affected_groups' => array_slice(array_keys($affected), 0, 5),
            'worst_rated' => array_values($worst),
            'disagreements' => $disagreements,
        ];
    }
}

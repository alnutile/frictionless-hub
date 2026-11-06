<?php

namespace App\Services;

/**
 * How much evidence sits under each theme: distinct notes and interviews that carry
 * any of the theme's codes, and how many of those interviews came from each work area.
 *
 * Pure logic over plain arrays so it can be unit tested without the framework.
 */
class ThemeWeights
{
    /**
     * @param  array<int, array{id: int, code_ids: array<int, int>}>  $themes
     * @param  array<int, array{id: int, interview_id: int, area: string|null, code_ids: array<int, int>}>  $notes
     * @return array<int, array{notes: int, interviews: int, areas: array<int, array{area: string, interviews: int}>}> Keyed by theme id
     */
    public static function build(array $themes, array $notes): array
    {
        $weights = [];

        foreach ($themes as $theme) {
            $noteIds = [];
            $interviewAreas = [];

            foreach ($notes as $note) {
                if (array_intersect($theme['code_ids'], $note['code_ids']) === []) {
                    continue;
                }

                $noteIds[$note['id']] = true;
                $interviewAreas[$note['interview_id']] = $note['area'] ?? 'No area';
            }

            $areaCounts = array_count_values($interviewAreas);
            arsort($areaCounts);

            $weights[$theme['id']] = [
                'notes' => count($noteIds),
                'interviews' => count($interviewAreas),
                'areas' => array_map(
                    fn (string $area, int $count) => ['area' => $area, 'interviews' => $count],
                    array_keys($areaCounts),
                    array_values($areaCounts),
                ),
            ];
        }

        return $weights;
    }
}

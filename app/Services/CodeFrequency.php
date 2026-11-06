<?php

namespace App\Services;

/**
 * How often each code has been applied, counted both by note and by distinct interview.
 * "Mentioned in 4 interviews" is the number that matters for themes, so sort on that first.
 *
 * Pure logic over plain arrays so it can be unit tested without the framework.
 */
class CodeFrequency
{
    /**
     * @param  array<int, array{id: int, name: string}>  $codes
     * @param  array<int, array{interview_id: int, code_ids: array<int, int>}>  $notes
     * @return array<int, array{id: int, name: string, notes: int, interviews: int}>
     */
    public static function build(array $codes, array $notes): array
    {
        $noteCounts = [];
        $interviewSets = [];

        foreach ($notes as $note) {
            foreach (array_unique($note['code_ids']) as $codeId) {
                $noteCounts[$codeId] = ($noteCounts[$codeId] ?? 0) + 1;
                $interviewSets[$codeId][$note['interview_id']] = true;
            }
        }

        $rows = array_map(fn (array $code) => [
            'id' => $code['id'],
            'name' => $code['name'],
            'notes' => $noteCounts[$code['id']] ?? 0,
            'interviews' => count($interviewSets[$code['id']] ?? []),
        ], $codes);

        usort($rows, fn (array $a, array $b) => [$b['interviews'], $b['notes'], $a['name']] <=> [$a['interviews'], $a['notes'], $b['name']]);

        return $rows;
    }
}

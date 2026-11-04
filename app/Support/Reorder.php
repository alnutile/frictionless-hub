<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Reorder
{
    /**
     * Swap an id with its neighbour in an ordered list. Unchanged if already at the edge.
     *
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    public static function swap(array $ids, int $id, string $direction): array
    {
        $ids = array_values($ids);
        $index = array_search($id, $ids, true);

        if ($index === false) {
            return $ids;
        }

        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if ($target < 0 || $target >= count($ids)) {
            return $ids;
        }

        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];

        return $ids;
    }

    /**
     * Persist positions 0..n for the given ordered ids.
     *
     * @param  class-string<Model>  $model
     * @param  array<int, int>  $ids
     */
    public static function persist(string $model, array $ids): void
    {
        DB::transaction(function () use ($model, $ids) {
            foreach ($ids as $position => $id) {
                $model::whereKey($id)->update(['position' => $position]);
            }
        });
    }
}

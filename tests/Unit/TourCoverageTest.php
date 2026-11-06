<?php

namespace Tests\Unit;

use App\Services\TourCoverage;
use PHPUnit\Framework\TestCase;

class TourCoverageTest extends TestCase
{
    private const AREAS = [['id' => 1, 'name' => 'Web'], ['id' => 2, 'name' => 'Mobile']];

    private const CATEGORIES = [
        ['id' => 10, 'name' => 'Experience', 'values' => [['id' => 11, 'name' => 'Junior'], ['id' => 12, 'name' => 'Senior']]],
        ['id' => 20, 'name' => 'Tenure', 'values' => [['id' => 21, 'name' => 'New'], ['id' => 22, 'name' => 'Long-time']]],
    ];

    public function test_counts_people_per_area_and_value_with_column_totals(): void
    {
        $result = TourCoverage::build(self::AREAS, self::CATEGORIES, [
            ['area_value_id' => 1, 'value_ids' => [11, 21]],
            ['area_value_id' => 1, 'value_ids' => [12, 22]],
            ['area_value_id' => 2, 'value_ids' => [12, 21]],
        ]);

        $this->assertSame(['area_id' => 1, 'area' => 'Web', 'total' => 2, 'counts' => [11 => 1, 12 => 1, 21 => 1, 22 => 1]], $result['rows'][0]);
        $this->assertSame([11 => 1, 12 => 2, 21 => 2, 22 => 1], $result['column_totals']);
        $this->assertSame(3, $result['total']);
        $this->assertSame([], $result['issues']);
    }

    public function test_totals_check_flags_people_missing_a_value_in_a_category(): void
    {
        $result = TourCoverage::build(self::AREAS, self::CATEGORIES, [
            ['area_value_id' => 1, 'value_ids' => [11, 21]],
            ['area_value_id' => 1, 'value_ids' => [12]],
        ]);

        $this->assertSame([['area' => 'Web', 'category' => 'Tenure', 'missing' => 1]], $result['issues']);
    }

    public function test_people_without_an_area_get_their_own_row(): void
    {
        $result = TourCoverage::build(self::AREAS, self::CATEGORIES, [
            ['area_value_id' => null, 'value_ids' => [11, 21]],
            ['area_value_id' => 99, 'value_ids' => [12, 22]],
        ]);

        $last = end($result['rows']);
        $this->assertSame([null, TourCoverage::NO_AREA_LABEL, 2], [$last['area_id'], $last['area'], $last['total']]);
        $this->assertSame($result['total'], array_sum(array_column($result['rows'], 'total')));
    }

    public function test_reports_empty_areas_empty_values_and_progress_to_target(): void
    {
        $result = TourCoverage::build(self::AREAS, self::CATEGORIES, [
            ['area_value_id' => 1, 'value_ids' => [12, 22]],
        ], target: 12);

        $this->assertSame(['Mobile'], $result['empty_areas']);
        $this->assertSame([
            ['category' => 'Experience', 'value' => 'Junior'],
            ['category' => 'Tenure', 'value' => 'New'],
        ], $result['empty_values']);
        $this->assertSame(11, $result['remaining']);
    }

    public function test_remaining_never_goes_below_zero_and_is_null_without_a_target(): void
    {
        $over = TourCoverage::build(self::AREAS, [], [['area_value_id' => 1, 'value_ids' => []], ['area_value_id' => 2, 'value_ids' => []]], target: 1);
        $none = TourCoverage::build(self::AREAS, [], []);

        $this->assertSame(0, $over['remaining']);
        $this->assertNull($none['remaining']);
    }
}

<?php

namespace Tests\Unit;

use App\Services\FrictionHeatmap;
use PHPUnit\Framework\TestCase;

class FrictionHeatmapTest extends TestCase
{
    private const STAGES = [['id' => 1, 'name' => 'Coding'], ['id' => 2, 'name' => 'Testing']];

    private const DIMENSIONS = [['id' => 10, 'name' => 'A'], ['id' => 20, 'name' => 'B']];

    public function test_counts_and_averages_pain_per_stage_and_dimension(): void
    {
        $result = FrictionHeatmap::build(self::STAGES, self::DIMENSIONS, [
            ['stage_id' => 1, 'pain' => 2, 'dimension_ids' => [10]],
            ['stage_id' => 1, 'pain' => 5, 'dimension_ids' => [10, 20]],
            ['stage_id' => 2, 'pain' => null, 'dimension_ids' => [20]],
        ]);

        $coding = $result['rows'][0];
        $this->assertSame(2, $coding['total']);
        $this->assertSame(['dimension_id' => 10, 'count' => 2, 'avg_pain' => 3.5], $coding['cells'][0]);
        $this->assertSame(['dimension_id' => 20, 'count' => 1, 'avg_pain' => 5.0], $coding['cells'][1]);

        $testing = $result['rows'][1];
        $this->assertSame(['dimension_id' => 20, 'count' => 1, 'avg_pain' => null], $testing['cells'][1]);
        $this->assertSame(2, $result['max_count']);
        $this->assertCount(2, $result['rows'], 'No "No stage" row when every point has a stage.');
    }

    public function test_points_without_a_stage_get_their_own_row_at_the_bottom(): void
    {
        $result = FrictionHeatmap::build(self::STAGES, self::DIMENSIONS, [
            ['stage_id' => null, 'pain' => 3, 'dimension_ids' => [10]],
            ['stage_id' => null, 'pain' => 5, 'dimension_ids' => [10]],
        ]);

        $last = end($result['rows']);
        $this->assertNull($last['stage_id']);
        $this->assertSame(FrictionHeatmap::NO_STAGE_LABEL, $last['stage']);
        $this->assertSame(2, $last['total']);
        $this->assertSame(['dimension_id' => 10, 'count' => 2, 'avg_pain' => 4.0], $last['cells'][0]);
        $this->assertSame(2, $result['unassigned']);
        $this->assertSame(2, $result['max_count']);
    }

    public function test_points_on_a_stage_that_no_longer_exists_count_as_no_stage(): void
    {
        $result = FrictionHeatmap::build(self::STAGES, self::DIMENSIONS, [
            ['stage_id' => 99, 'pain' => 1, 'dimension_ids' => []],
        ]);

        $this->assertSame(1, $result['unassigned']);
    }

    public function test_row_totals_always_add_up_to_the_number_of_points(): void
    {
        $points = [
            ['stage_id' => 1, 'pain' => 4, 'dimension_ids' => []],
            ['stage_id' => 2, 'pain' => null, 'dimension_ids' => [10, 20]],
            ['stage_id' => null, 'pain' => 2, 'dimension_ids' => []],
            ['stage_id' => 99, 'pain' => 2, 'dimension_ids' => [20]],
        ];

        $result = FrictionHeatmap::build(self::STAGES, self::DIMENSIONS, $points);

        $this->assertSame(4, $result['total']);
        $this->assertSame($result['total'], array_sum(array_column($result['rows'], 'total')));
    }

    public function test_duplicate_dimension_ids_are_counted_once(): void
    {
        $result = FrictionHeatmap::build(self::STAGES, self::DIMENSIONS, [
            ['stage_id' => 1, 'pain' => 1, 'dimension_ids' => [10, 10]],
        ]);

        $this->assertSame(1, $result['rows'][0]['cells'][0]['count']);
    }
}

<?php

namespace Tests\Unit;

use App\Services\ThemeWeights;
use PHPUnit\Framework\TestCase;

class ThemeWeightsTest extends TestCase
{
    public function test_counts_distinct_notes_and_interviews_under_a_themes_codes_by_area(): void
    {
        $weights = ThemeWeights::build(
            [['id' => 1, 'code_ids' => [10, 11]], ['id' => 2, 'code_ids' => [12]], ['id' => 3, 'code_ids' => []]],
            [
                ['id' => 100, 'interview_id' => 1, 'area' => 'Web', 'code_ids' => [10, 11]],
                ['id' => 101, 'interview_id' => 1, 'area' => 'Web', 'code_ids' => [11]],
                ['id' => 102, 'interview_id' => 2, 'area' => 'Mobile', 'code_ids' => [10]],
                ['id' => 103, 'interview_id' => 3, 'area' => 'Web', 'code_ids' => [10]],
                ['id' => 104, 'interview_id' => 4, 'area' => null, 'code_ids' => [12]],
                ['id' => 105, 'interview_id' => 2, 'area' => 'Mobile', 'code_ids' => []],
            ],
        );

        $this->assertSame(['notes' => 4, 'interviews' => 3, 'areas' => [
            ['area' => 'Web', 'interviews' => 2],
            ['area' => 'Mobile', 'interviews' => 1],
        ]], $weights[1]);
        $this->assertSame(['notes' => 1, 'interviews' => 1, 'areas' => [['area' => 'No area', 'interviews' => 1]]], $weights[2]);
        $this->assertSame(['notes' => 0, 'interviews' => 0, 'areas' => []], $weights[3]);
    }
}

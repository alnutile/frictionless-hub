<?php

namespace Tests\Unit;

use App\Services\RatingSummary;
use PHPUnit\Framework\TestCase;

class RatingSummaryTest extends TestCase
{
    public function test_summarizes_scores(): void
    {
        $this->assertSame([
            'count' => 3,
            'average' => 2.7,
            'distribution' => [1 => 1, 2 => 0, 3 => 1, 4 => 1, 5 => 0],
            'spread' => 3,
        ], RatingSummary::of([1, 3, 4]));
    }

    public function test_empty_and_out_of_range_scores(): void
    {
        $summary = RatingSummary::of([0, 7]);

        $this->assertSame(0, $summary['count']);
        $this->assertNull($summary['average']);
        $this->assertNull($summary['spread']);
    }
}

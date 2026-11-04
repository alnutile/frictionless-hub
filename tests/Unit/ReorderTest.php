<?php

namespace Tests\Unit;

use App\Support\Reorder;
use PHPUnit\Framework\TestCase;

class ReorderTest extends TestCase
{
    public function test_swaps_with_neighbour(): void
    {
        $this->assertSame([2, 1, 3], Reorder::swap([1, 2, 3], 2, 'up'));
        $this->assertSame([1, 3, 2], Reorder::swap([1, 2, 3], 2, 'down'));
    }

    public function test_edges_and_unknown_ids_are_unchanged(): void
    {
        $this->assertSame([1, 2, 3], Reorder::swap([1, 2, 3], 1, 'up'));
        $this->assertSame([1, 2, 3], Reorder::swap([1, 2, 3], 3, 'down'));
        $this->assertSame([1, 2, 3], Reorder::swap([1, 2, 3], 9, 'up'));
    }
}

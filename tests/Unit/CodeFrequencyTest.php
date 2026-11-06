<?php

namespace Tests\Unit;

use App\Services\CodeFrequency;
use PHPUnit\Framework\TestCase;

class CodeFrequencyTest extends TestCase
{
    public function test_counts_notes_and_distinct_interviews_and_sorts_by_interviews_first(): void
    {
        $result = CodeFrequency::build(
            [['id' => 1, 'name' => 'slow-ci'], ['id' => 2, 'name' => 'docs'], ['id' => 3, 'name' => 'unused']],
            [
                ['interview_id' => 10, 'code_ids' => [1]],
                ['interview_id' => 10, 'code_ids' => [1]],
                ['interview_id' => 10, 'code_ids' => [1, 1]],
                ['interview_id' => 11, 'code_ids' => [2]],
                ['interview_id' => 12, 'code_ids' => [2]],
            ],
        );

        $this->assertSame([
            ['id' => 2, 'name' => 'docs', 'notes' => 2, 'interviews' => 2],
            ['id' => 1, 'name' => 'slow-ci', 'notes' => 3, 'interviews' => 1],
            ['id' => 3, 'name' => 'unused', 'notes' => 0, 'interviews' => 0],
        ], $result);
    }
}

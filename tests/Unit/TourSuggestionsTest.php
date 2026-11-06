<?php

namespace Tests\Unit;

use App\Services\TourSuggestions;
use PHPUnit\Framework\TestCase;

class TourSuggestionsTest extends TestCase
{
    public function test_ranks_stages_by_total_pain_and_names_the_worst_point(): void
    {
        $result = TourSuggestions::build([
            ['title' => 'Slow CI', 'stage' => 'Building', 'pain' => 5, 'affected' => 'Backend devs'],
            ['title' => 'Flaky tests', 'stage' => 'Testing', 'pain' => 4, 'affected' => 'Mobile devs'],
            ['title' => 'Cache misses', 'stage' => 'Building', 'pain' => 2, 'affected' => null],
            ['title' => 'Unclear owner', 'stage' => null, 'pain' => 5, 'affected' => 'Seniors'],
        ], []);

        $this->assertSame(['stage' => 'Building', 'points' => 2, 'pain_total' => 7, 'top' => 'Slow CI'], $result['hot_stages'][0]);
        $this->assertSame('Testing', $result['hot_stages'][1]['stage']);
        $this->assertCount(2, $result['hot_stages'], 'Points without a stage are not ranked as a stage.');
        $this->assertSame(['Backend devs', 'Mobile devs', 'Seniors'], $result['affected_groups']);
        $this->assertTrue($result['has_data']);
    }

    public function test_surfaces_worst_rated_components_and_wide_disagreement(): void
    {
        $result = TourSuggestions::build([], [
            ['label' => 'Docs', 'average' => 4.4, 'count' => 5, 'spread' => 2],
            ['label' => 'Release', 'average' => 2.6, 'count' => 5, 'spread' => 4],
            ['label' => 'CI', 'average' => 3.4, 'count' => 5, 'spread' => 3],
            ['label' => 'Unrated', 'average' => null, 'count' => 0, 'spread' => null],
        ]);

        $this->assertSame(['Docs', 'CI'], array_column($result['worst_rated'], 'label'), 'Items averaging under 3 are not called out as worst.');
        $this->assertSame([['label' => 'Release', 'spread' => 4], ['label' => 'CI', 'spread' => 3]], $result['disagreements']);
    }

    public function test_reports_no_data_for_an_empty_initiative(): void
    {
        $result = TourSuggestions::build([], []);

        $this->assertFalse($result['has_data']);
        $this->assertSame([], $result['hot_stages']);
    }
}

<?php

use App\Models\Initiative;
use Database\Seeders\LarkspurScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Function-style tests in Feature and Browser run on the Laravel TestCase with a
| fresh database. Class-based PHPUnit tests declare their own traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Browser');

/*
 * Fail a stuck browser step after 10s instead of waiting forever.
 */
pest()->browser()->timeout(10_000);

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Seed the Larkspur benchmark scenario and return its initiative.
 */
function larkspur(): Initiative
{
    test()->seed(LarkspurScenarioSeeder::class);

    return Initiative::where('name', LarkspurScenarioSeeder::INITIATIVE_NAME)->firstOrFail();
}

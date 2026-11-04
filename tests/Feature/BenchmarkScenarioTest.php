<?php

namespace Tests\Feature;

use App\Models\Initiative;
use App\Models\User;
use App\Support\Walkthrough;
use Database\Seeders\LarkspurScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Seeds the Larkspur benchmark and walks every built activity in workbook order,
 * as the owner and as a viewer. New activities are covered automatically.
 */
class BenchmarkScenarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_built_activity_renders_for_owner_and_viewer(): void
    {
        $this->seed(LarkspurScenarioSeeder::class);
        $initiative = Initiative::firstWhere('name', LarkspurScenarioSeeder::INITIATIVE_NAME);
        $viewer = User::firstWhere('email', 'dana@'.LarkspurScenarioSeeder::EMAIL_DOMAIN);

        $built = array_filter(Walkthrough::activities(), fn (array $a) => $a['route'] !== null);
        $this->assertNotEmpty($built);

        foreach ([$initiative->owner, $viewer] as $user) {
            $this->actingAs($user)->get(route('initiatives.show', $initiative))->assertOk();

            foreach ($built as $activity) {
                $this->actingAs($user)
                    ->get(route($activity['route'], $initiative))
                    ->assertOk()
                    ->assertInertia(fn (Assert $page) => $page->where('guide.key', $activity['key']));
            }
        }
    }

    public function test_scenario_has_data_in_every_built_activity(): void
    {
        $this->seed(LarkspurScenarioSeeder::class);
        $initiative = Initiative::firstWhere('name', LarkspurScenarioSeeder::INITIATIVE_NAME);

        $this->actingAs($initiative->owner)
            ->get(route('initiatives.show', $initiative))
            ->assertInertia(function (Assert $page) {
                foreach (['workflow', 'friction', 'story-map', 'quick-rating'] as $key) {
                    $page->where("progress.{$key}.count", fn (int $count) => $count > 0);
                }
            });
    }

    public function test_reseeding_rebuilds_rather_than_duplicates(): void
    {
        $this->seed(LarkspurScenarioSeeder::class);
        $this->seed(LarkspurScenarioSeeder::class);

        $this->assertSame(1, Initiative::where('name', LarkspurScenarioSeeder::INITIATIVE_NAME)->count());
        $this->assertSame(8, User::where('email', 'like', '%@'.LarkspurScenarioSeeder::EMAIL_DOMAIN)->count());
    }
}

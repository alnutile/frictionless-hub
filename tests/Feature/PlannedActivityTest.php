<?php

namespace Tests\Feature;

use App\Models\Initiative;
use App\Models\User;
use App\Services\Guide;
use App\Support\Walkthrough;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlannedActivityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function plannedKeys(): array
    {
        $planned = array_filter(Walkthrough::activities(), fn (array $a) => $a['route'] === null);

        return collect($planned)->mapWithKeys(fn (array $a) => [$a['key'] => [$a['key']]])->all();
    }

    #[DataProvider('plannedKeys')]
    public function test_every_planned_step_has_a_preview_with_its_own_description(string $key): void
    {
        $initiative = Initiative::factory()->create();

        $this->assertStringNotContainsString('will be added when the screen ships', Guide::body($key), "Guide for [{$key}] is still a placeholder.");

        $this->actingAs(User::factory()->create())
            ->get(route('initiatives.planned', [$initiative, $key]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('initiatives/planned')
                ->where('activity.key', $key)
                ->where('html', fn (string $html) => str_contains($html, 'What it'))
                ->where('guide.key', $key));
    }

    public function test_a_built_step_redirects_to_its_screen(): void
    {
        $initiative = Initiative::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('initiatives.planned', [$initiative, 'friction']))
            ->assertRedirect(route('initiatives.friction', $initiative));
    }

    public function test_unknown_step_returns_not_found(): void
    {
        $initiative = Initiative::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('initiatives.planned', [$initiative, 'not-a-step']))
            ->assertNotFound();
    }

    public function test_lists_the_built_steps_you_can_work_on_first(): void
    {
        $initiative = Initiative::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('initiatives.planned', [$initiative, 'themes']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('builtBefore', fn ($built) => collect($built)->every(fn ($a) => $a['route'] !== null) && count($built) > 0)
                ->where('next.key', 'stakeholders'));
    }
}

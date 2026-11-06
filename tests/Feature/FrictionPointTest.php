<?php

namespace Tests\Feature;

use App\Enums\Frequency;
use App\Models\Dimension;
use App\Models\FrictionPoint;
use App\Models\Initiative;
use App\Models\User;
use App\Models\WorkflowStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FrictionPointTest extends TestCase
{
    use RefreshDatabase;

    private Initiative $initiative;

    protected function setUp(): void
    {
        parent::setUp();

        $this->initiative = Initiative::factory()->create();
        $this->initiative->seedDefaults();
    }

    public function test_owner_can_log_friction_with_stage_and_dimensions(): void
    {
        $stage = $this->initiative->stages()->where('name', 'Building')->first();
        $dimensionIds = $this->initiative->dimensions()->limit(2)->pluck('id')->all();

        $this->actingAs($this->initiative->owner)
            ->post(route('initiatives.friction.store', $this->initiative), [
                'title' => 'Slow CI builds',
                'workflow_stage_id' => $stage->id,
                'frequency' => 'daily',
                'pain' => 5,
                'affected' => 'All developers',
                'dimension_ids' => $dimensionIds,
            ])
            ->assertSessionHasNoErrors();

        $point = FrictionPoint::firstWhere('title', 'Slow CI builds');
        $this->assertSame($stage->id, $point->workflow_stage_id);
        $this->assertSame(Frequency::Daily, $point->frequency);
        $this->assertSame($this->initiative->owner_id, $point->created_by);
        $this->assertEqualsCanonicalizing($dimensionIds, $point->dimensions()->pluck('dimensions.id')->all());
    }

    public function test_rejects_pain_outside_one_to_five(): void
    {
        $this->actingAs($this->initiative->owner)
            ->post(route('initiatives.friction.store', $this->initiative), ['title' => 'X', 'pain' => 6])
            ->assertSessionHasErrors(['pain' => 'The pain field must be between 1 and 5.']);
    }

    public function test_rejects_stage_and_dimensions_from_another_initiative(): void
    {
        $foreignStage = WorkflowStage::factory()->create();
        $foreignDimension = Dimension::factory()->create();

        $this->actingAs($this->initiative->owner)
            ->post(route('initiatives.friction.store', $this->initiative), [
                'title' => 'Cross-wired',
                'workflow_stage_id' => $foreignStage->id,
                'dimension_ids' => [$foreignDimension->id],
            ])
            ->assertSessionHasErrors(['workflow_stage_id', 'dimension_ids.0']);

        $this->assertDatabaseMissing('friction_points', ['title' => 'Cross-wired']);
    }

    public function test_viewer_cannot_log_friction(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('initiatives.friction.store', $this->initiative), ['title' => 'Nope'])
            ->assertForbidden();
    }

    public function test_friction_point_from_another_initiative_returns_not_found(): void
    {
        $foreign = FrictionPoint::factory()->create();

        $this->actingAs($this->initiative->owner)
            ->delete(route('initiatives.friction.destroy', [$this->initiative, $foreign]))
            ->assertNotFound();

        $this->assertModelExists($foreign);
    }

    public function test_update_replaces_dimension_tags(): void
    {
        [$first, $second] = $this->initiative->dimensions()->limit(2)->get()->all();
        $point = FrictionPoint::factory()->for($this->initiative)->create();
        $point->dimensions()->sync([$first->id]);

        $this->actingAs($this->initiative->owner)
            ->patch(route('initiatives.friction.update', [$this->initiative, $point]), [
                'title' => $point->title,
                'dimension_ids' => [$second->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame([$second->id], $point->dimensions()->pluck('dimensions.id')->all());
    }

    public function test_friction_page_includes_heatmap_counts(): void
    {
        $stage = $this->initiative->stages()->first();
        $dimension = $this->initiative->dimensions()->first();

        foreach ([2, 4] as $pain) {
            $point = FrictionPoint::factory()->for($this->initiative)->create(['workflow_stage_id' => $stage->id, 'pain' => $pain]);
            $point->dimensions()->attach($dimension);
        }

        $this->actingAs(User::factory()->create())
            ->get(route('initiatives.friction', $this->initiative))
            ->assertInertia(fn (Assert $page) => $page
                ->component('initiatives/friction')
                ->has('frictionPoints', 2)
                ->where('heatmap.rows.0.total', 2)
                ->where('heatmap.rows.0.cells.0.count', 2)
                ->where('heatmap.rows.0.cells.0.avg_pain', 3));
    }

    public function test_dimension_names_must_be_unique_within_an_initiative(): void
    {
        $existing = $this->initiative->dimensions()->first();

        $this->actingAs($this->initiative->owner)
            ->post(route('initiatives.dimensions.store', $this->initiative), ['name' => $existing->name])
            ->assertSessionHasErrors(['name' => 'The name has already been taken.']);

        $other = Initiative::factory()->create();
        $this->actingAs($other->owner)
            ->post(route('initiatives.dimensions.store', $other), ['name' => $existing->name])
            ->assertSessionHasNoErrors();
    }

    public function test_overview_list_and_heatmap_counts_agree_when_some_points_have_no_stage(): void
    {
        $stage = $this->initiative->stages()->first();
        FrictionPoint::factory()->for($this->initiative)->count(2)->create(['workflow_stage_id' => $stage->id]);
        FrictionPoint::factory()->for($this->initiative)->count(3)->create(['workflow_stage_id' => null]);

        $viewer = User::factory()->create();

        $this->actingAs($viewer)
            ->get(route('initiatives.friction', $this->initiative))
            ->assertInertia(fn (Assert $page) => $page
                ->has('frictionPoints', 5)
                ->where('heatmap.total', 5)
                ->where('heatmap.unassigned', 3)
                ->where('heatmap.rows', fn ($rows) => collect($rows)->sum('total') === 5
                    && collect($rows)->last()['stage'] === 'No stage'
                    && collect($rows)->last()['total'] === 3));

        $this->actingAs($viewer)
            ->get(route('initiatives.show', $this->initiative))
            ->assertInertia(fn (Assert $page) => $page->where('progress.friction.count', 5));
    }
}

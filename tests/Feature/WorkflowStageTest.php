<?php

namespace Tests\Feature;

use App\Models\Initiative;
use App\Models\User;
use App\Models\WorkflowStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkflowStageTest extends TestCase
{
    use RefreshDatabase;

    private function initiativeWithStages(): Initiative
    {
        $initiative = Initiative::factory()->create();
        $initiative->seedDefaults();

        return $initiative;
    }

    public function test_workflow_page_lists_stages_in_order_with_tools(): void
    {
        $initiative = $this->initiativeWithStages();
        $initiative->stages()->first()->tools()->create(['name' => 'Issue tracker']);

        $this->actingAs(User::factory()->create())
            ->get(route('initiatives.workflow', $initiative))
            ->assertInertia(fn (Assert $page) => $page
                ->component('initiatives/workflow')
                ->where('canManage', false)
                ->has('stages', count(config('frictionless.default_stages')))
                ->where('stages.0.name', 'Planning')
                ->where('stages.0.tools.0.name', 'Issue tracker'));
    }

    public function test_new_stage_is_appended_to_the_end(): void
    {
        $initiative = $this->initiativeWithStages();

        $this->actingAs($initiative->owner)
            ->post(route('initiatives.stages.store', $initiative), ['name' => 'Onboarding'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Onboarding', $initiative->stages()->get()->last()->name);
    }

    public function test_moving_a_stage_swaps_it_with_its_neighbour(): void
    {
        $initiative = $this->initiativeWithStages();
        $coding = $initiative->stages()->where('name', 'Coding')->first();

        $this->actingAs($initiative->owner)
            ->post(route('initiatives.stages.move', [$initiative, $coding]), ['direction' => 'up']);

        $this->assertSame(['Coding', 'Planning', 'Testing'], $initiative->stages()->limit(3)->pluck('name')->all());
    }

    public function test_moving_the_first_stage_up_changes_nothing(): void
    {
        $initiative = $this->initiativeWithStages();
        $planning = $initiative->stages()->first();

        $this->actingAs($initiative->owner)
            ->post(route('initiatives.stages.move', [$initiative, $planning]), ['direction' => 'up']);

        $this->assertSame(config('frictionless.default_stages'), $initiative->stages()->pluck('name')->all());
    }

    public function test_stage_from_another_initiative_returns_not_found(): void
    {
        $initiative = $this->initiativeWithStages();
        $foreignStage = WorkflowStage::factory()->create();

        $this->actingAs($initiative->owner)
            ->patch(route('initiatives.stages.update', [$initiative, $foreignStage]), ['name' => 'Hijacked'])
            ->assertNotFound();

        $this->actingAs($initiative->owner)
            ->post(route('initiatives.tools.store', [$initiative, $foreignStage]), ['name' => 'Sneaky'])
            ->assertNotFound();

        $this->assertNotSame('Hijacked', $foreignStage->fresh()->name);
    }

    public function test_viewer_cannot_add_tools(): void
    {
        $initiative = $this->initiativeWithStages();
        $stage = $initiative->stages()->first();

        $this->actingAs(User::factory()->create())
            ->post(route('initiatives.tools.store', [$initiative, $stage]), ['name' => 'Editor'])
            ->assertForbidden();

        $this->assertSame(0, $stage->tools()->count());
    }

    public function test_owner_can_add_and_remove_tools(): void
    {
        $initiative = $this->initiativeWithStages();
        $stage = $initiative->stages()->first();

        $this->actingAs($initiative->owner)
            ->post(route('initiatives.tools.store', [$initiative, $stage]), ['name' => 'Code assistant', 'is_ai' => true]);

        $tool = $stage->tools()->first();
        $this->assertTrue($tool->is_ai);

        $this->actingAs($initiative->owner)->delete(route('initiatives.tools.destroy', [$initiative, $stage, $tool]));

        $this->assertModelMissing($tool);
    }
}

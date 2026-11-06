<?php

use App\Models\Initiative;
use App\Models\User;

/*
 * A facilitator works through the Discover phase in workbook order,
 * starting from an empty initiative.
 */

beforeEach(function () {
    $this->facilitator = User::factory()->facilitator()->create();
    $this->actingAs($this->facilitator);
});

it('creates an initiative and sees the ladder', function () {
    visit(route('initiatives.index'))
        ->press('New initiative')
        ->fill('name', 'Checkout DevEx')
        ->fill('scope', 'Checkout org')
        ->press('Create')
        ->assertSee('Initiative created.')
        ->assertSee('Checkout DevEx')
        ->assertSee('Map the workflow')
        ->assertSee('current phase')
        ->assertNoJavaScriptErrors();

    expect(Initiative::firstWhere('name', 'Checkout DevEx')->stages()->count())->toBe(7);
});

it('maps tools onto a workflow stage and adds a stage', function () {
    $initiative = Initiative::factory()->for($this->facilitator, 'owner')->create();
    $initiative->seedDefaults();
    $planning = $initiative->stages()->firstWhere('name', 'Planning');

    visit(route('initiatives.workflow', $initiative))
        ->type("@tool-input-{$planning->id}", 'Issue tracker')
        ->keys("@tool-input-{$planning->id}", 'Enter')
        ->assertSee('Issue tracker')
        ->fill('new-stage', 'Onboarding')
        ->press('Add stage')
        ->assertSee('Stage added.')
        ->assertSee('Onboarding')
        ->assertNoJavaScriptErrors();

    expect($planning->tools()->pluck('name')->all())->toBe(['Issue tracker']);
});

it('logs a friction point and sees it on the heatmap', function () {
    $initiative = Initiative::factory()->for($this->facilitator, 'owner')->create();
    $initiative->seedDefaults();
    $building = $initiative->stages()->firstWhere('name', 'Building');
    $flow = $initiative->dimensions()->firstWhere('name', 'Flow state');

    visit(route('initiatives.friction', $initiative))
        ->assertSee('No friction points logged yet.')
        ->click('@friction-open')
        ->fill('title', 'Builds take 40 minutes')
        ->select('stage', (string) $building->id)
        ->select('frequency', 'daily')
        ->click('@pain-5')
        ->click("@dimension-{$flow->id}")
        ->click('@friction-submit')
        ->assertSee('Friction point logged.')
        ->assertSee('Builds take 40 minutes')
        ->assertSee('pain 5')
        ->assertNoJavaScriptErrors();

    $point = $initiative->frictionPoints()->first();
    expect($point->workflow_stage_id)->toBe($building->id)
        ->and($point->pain)->toBe(5)
        ->and($point->dimensions()->pluck('dimensions.id')->all())->toBe([$flow->id]);
});

it('builds a story map and toggles a layer', function () {
    $initiative = Initiative::factory()->for($this->facilitator, 'owner')->create();

    $page = visit(route('initiatives.story-map', $initiative))
        ->type('@story-new-activity', 'Ship it')
        ->keys('@story-new-activity', 'Enter')
        ->assertSee('Ship it');

    $activity = $initiative->storyMapItems()->firstWhere('title', 'Ship it');

    $page->type("@story-new-task-{$activity->id}", 'Release')
        ->keys("@story-new-task-{$activity->id}", 'Enter')
        ->assertSee('Release');

    $task = $initiative->storyMapItems()->firstWhere('title', 'Release');

    $page->select("@story-new-card-{$task->id}-kind", 'friction')
        ->type("@story-new-card-{$task->id}", 'Three manual sign-offs')
        ->keys("@story-new-card-{$task->id}", 'Enter')
        ->assertSee('Three manual sign-offs')
        ->click('@layer-friction')
        ->assertDontSee('Three manual sign-offs')
        ->click('@layer-friction')
        ->assertSee('Three manual sign-offs')
        ->assertNoJavaScriptErrors();
});

it('sets up a rating grid and rates an item', function () {
    $initiative = Initiative::factory()->for($this->facilitator, 'owner')->create();

    $page = visit(route('initiatives.ratings', $initiative))
        ->type('@rating-new-group', 'Shipping')
        ->press('Add group')
        ->assertSee('Shipping');

    $group = $initiative->ratingGroups()->first();

    $page->type("@rating-new-item-{$group->id}", 'CI reliability')
        ->keys("@rating-new-item-{$group->id}", 'Enter')
        ->assertSee('CI reliability');

    $item = $group->items()->first();

    $page->click("@rate-{$item->id}-4")
        ->assertSeeIn("@average-{$item->id}", '4')
        ->assertNoJavaScriptErrors();

    expect($item->ratings()->pluck('score')->all())->toBe([4]);
});

it('keeps friction without a stage visible and lets you assign one later', function () {
    $initiative = Initiative::factory()->for($this->facilitator, 'owner')->create();
    $initiative->seedDefaults();
    $testing = $initiative->stages()->firstWhere('name', 'Testing');

    $page = visit(route('initiatives.friction', $initiative))
        ->click('@friction-open')
        ->fill('title', 'Not sure where this belongs')
        ->click('@pain-3')
        ->click('@friction-submit')
        ->assertSee('Friction point logged.')
        ->assertSeeIn('@heatmap-row-none', 'No stage')
        ->assertSeeIn('@heatmap-total', '1')
        ->assertSeeIn('@unassigned-banner', '1 friction point has no stage yet.');

    $point = $initiative->frictionPoints()->first();

    $page->click("@assign-stage-{$point->id}")
        ->select('stage', (string) $testing->id)
        ->click('@friction-submit')
        ->assertSee('Friction point updated.')
        ->assertNotPresent('@heatmap-row-none')
        ->assertNotPresent('@unassigned-banner')
        ->assertSeeIn('@heatmap-total', '1')
        ->assertNoJavaScriptErrors();

    expect($point->fresh()->workflow_stage_id)->toBe($testing->id);
});

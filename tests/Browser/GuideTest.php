<?php

use App\Models\GuideEntry;
use App\Models\User;
use Database\Seeders\LarkspurScenarioSeeder;

it('opens the guide, walks to the next step, and stays open', function () {
    $initiative = larkspur();
    $this->actingAs($initiative->owner);

    visit(route('initiatives.workflow', $initiative))
        ->click('@guide-toggle')
        ->assertSeeIn('@guide-panel', 'Step 1 of 18')
        ->assertSeeIn('@guide-panel', 'How to use it')
        ->click('@guide-next')
        ->assertPathIs("/initiatives/{$initiative->id}/friction")
        ->assertSeeIn('@guide-panel', 'Step 2 of 18')
        ->assertSeeIn('@guide-panel', 'Reading the heatmap')
        ->assertNoJavaScriptErrors();
});

it('saves notes for the initiative from the guide', function () {
    $initiative = larkspur();
    $this->actingAs($initiative->owner);

    visit(route('initiatives.friction', $initiative))
        ->click('@guide-toggle')
        ->type('@guide-notes', 'Hedgehog CI is the first thing to raise with leadership.')
        ->press('Save notes')
        ->assertSee('Saved');

    expect($initiative->activityNotes()->where('activity_key', 'friction')->value('body'))
        ->toBe('Hedgehog CI is the first thing to raise with leadership.');
});

it('lets an admin edit the guide text', function () {
    $initiative = larkspur();
    $this->actingAs(User::factory()->admin()->create());

    visit(route('initiatives.workflow', $initiative))
        ->click('@guide-toggle')
        ->press('Edit guide')
        ->clear('@guide-editor')
        ->type('@guide-editor', 'Our **house rules** for mapping.')
        ->press('Save guide')
        ->assertSeeIn('@guide-panel', 'house rules')
        ->assertSeeIn('@guide-panel', 'Edited by an admin');

    expect(GuideEntry::firstWhere('key', 'workflow')->body)->toBe('Our **house rules** for mapping.');
});

it('shows read-only notes and no edit controls to viewers', function () {
    $initiative = larkspur();
    $this->actingAs(User::firstWhere('email', 'dana@'.LarkspurScenarioSeeder::EMAIL_DOMAIN));

    visit(route('initiatives.friction', $initiative))
        ->click('@guide-toggle')
        ->assertSeeIn('@guide-panel', 'No notes yet.')
        ->assertDontSee('Edit guide')
        ->assertNotPresent('@friction-open')
        ->assertNoJavaScriptErrors();
});

it('works on a phone-sized screen', function () {
    $initiative = larkspur();
    $this->actingAs($initiative->owner);

    visit(route('initiatives.friction', $initiative))
        ->on()->mobile()
        ->click('@guide-toggle')
        ->assertSeeIn('@guide-panel', 'Step 2 of 18')
        ->assertNoJavaScriptErrors();
});

it('opens planned steps as a roadmap preview instead of a dead end', function () {
    $initiative = larkspur();
    $this->actingAs($initiative->owner);

    visit(route('initiatives.show', $initiative))
        ->assertSeeIn('@phase-quick_wins', 'PLANNED')
        ->click('@ladder-interviews')
        ->assertPathIs("/initiatives/{$initiative->id}/planned/interviews")
        ->assertSeeIn('@planned-activity', 'Step 6 · Discover')
        ->assertSeeIn('@planned-activity', 'What it will do')
        ->assertSeeIn('@planned-activity', 'You can work on these steps now')
        ->assertNoJavaScriptErrors();
});

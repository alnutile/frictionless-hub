<?php

use App\Models\Initiative;
use App\Models\User;

it('plans a tour from an empty initiative and fills in an invite', function () {
    $facilitator = User::factory()->facilitator()->create(['name' => 'Fran Facilitator']);
    $this->actingAs($facilitator);
    $initiative = Initiative::factory()->for($facilitator, 'owner')->create(['name' => 'Checkout DevEx']);
    $initiative->seedDefaults();

    $page = visit(route('initiatives.listening-tour', $initiative))
        ->assertSeeIn('@tour-suggestions', 'Nothing has been captured in the earlier steps yet')
        ->click('@tour-tab-setup')
        ->type('@area-new-value', 'Checkout web')
        ->keys('@area-new-value', 'Enter')
        ->assertSee('Checkout web')
        ->type('@group-new', 'Experience')
        ->keys('@group-new', 'Enter')
        ->assertSee('Experience');

    $experience = $initiative->segmentCategories()->firstWhere('name', 'Experience');

    $page->type("@group-{$experience->id}-new-value", 'Senior')
        ->keys("@group-{$experience->id}-new-value", 'Enter')
        ->assertSee('Senior');

    $area = $initiative->areaCategory()->values()->first();
    $senior = $experience->values()->first();
    $initiative->messageTemplates()->create(['name' => 'Short invite', 'channel' => 'chat', 'body' => 'Hi {{first_name}}, from {{facilitator}} because {{reason}}.']);

    visit(route('initiatives.listening-tour', $initiative))
        ->click('@tour-tab-participants')
        ->click('@participant-open')
        ->fill('name', 'Pat Example')
        ->select('area_value_id', (string) $area->id)
        ->select("group-{$experience->id}", (string) $senior->id)
        ->fill('invite_reason', 'you own the checkout flow')
        ->click('@participant-submit')
        ->assertSee('Participant added.')
        ->assertSee('Pat Example');

    $pat = $initiative->participants()->firstWhere('name', 'Pat Example');

    visit(route('initiatives.listening-tour', $initiative))
        ->assertSeeIn('@coverage-total', '1')
        ->assertNotPresent('@tour-gaps')
        ->click('@tour-tab-participants')
        ->click("@invite-{$pat->id}")
        ->assertSeeIn('@invite-preview', 'Hi Pat, from Fran Facilitator because you own the checkout flow.')
        ->assertNoJavaScriptErrors();
});

it('shows Larkspur gaps and the totals check, and hides names from viewers', function () {
    $initiative = larkspur();

    $this->actingAs($initiative->owner);
    visit(route('initiatives.listening-tour', $initiative))
        ->assertSeeIn('@tour-suggestions', 'Stages that hurt most')
        ->assertSeeIn('@tour-gaps', 'Totals check: 1 person in Mobile has no Time at Larkspur value.')
        ->assertSeeIn('@coverage-row-none', 'No area')
        ->assertNoJavaScriptErrors();

    $this->actingAs(User::firstWhere('email', 'dana@larkspur.test'));
    visit(route('initiatives.listening-tour', $initiative))
        ->assertNotPresent('@tour-tab-participants')
        ->assertDontSee('Riya Desai')
        ->assertSee('Participant names and notes are only visible to the people running this initiative.');
});

<?php

use App\Models\Code;
use App\Models\Initiative;

it('groups leftover codes into a theme, sends it to the friction log and opens its evidence', function () {
    $initiative = larkspur();
    $this->actingAs($initiative->owner);
    $envSetup = Code::firstWhere('name', 'env-setup');
    $aiAssistants = Code::firstWhere('name', 'ai-assistants');

    visit(route('initiatives.themes', $initiative))
        ->assertSeeIn('@coverage-summary', '7 of 10 codes are in a theme')
        ->assertSeeIn('@unthemed-codes', 'env-setup')
        ->click('@theme-new')
        ->type('@theme-name', 'Local tooling fights back')
        ->click("@theme-code-{$envSetup->id}")
        ->click("@theme-code-{$aiAssistants->id}")
        ->click('@theme-save')
        ->assertSee('Theme created.')
        ->assertSeeIn('@coverage-summary', '9 of 10 codes are in a theme');

    $theme = $initiative->themes()->firstWhere('name', 'Local tooling fights back');

    visit(route('initiatives.themes', $initiative))
        ->assertSeeIn("@theme-weight-{$theme->id}", '1 interview')
        ->click("@theme-promote-{$theme->id}")
        ->assertSee('Added to the friction log.')
        ->assertSeeIn("@theme-friction-{$theme->id}", 'In friction log')
        ->click("@theme-evidence-{$theme->id}")
        ->assertSeeIn('@theme-evidence', 'Local tooling fights back')
        ->assertSee('Riya Desai')
        ->assertNoJavaScriptErrors();
});

it('teaches from step 6 when there are no codes yet', function () {
    $initiative = Initiative::factory()->create();
    $this->actingAs($initiative->owner);

    visit(route('initiatives.themes', $initiative))
        ->assertSeeIn('@themes-empty', 'Themes are built from interview codes')
        ->assertNoJavaScriptErrors();
});

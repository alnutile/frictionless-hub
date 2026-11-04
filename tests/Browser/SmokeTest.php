<?php

use App\Models\User;
use App\Support\Walkthrough;
use Database\Seeders\LarkspurScenarioSeeder;

/*
 * Every page loads without JavaScript errors or console noise.
 * New walkthrough activities are picked up automatically from the registry.
 */

it('loads the public pages cleanly', function () {
    visit(['/', '/login', '/register'])->assertNoSmoke();
});

it('loads every built activity cleanly for the initiative owner', function () {
    $initiative = larkspur();
    $this->actingAs($initiative->owner);

    $urls = [route('initiatives.index'), route('initiatives.show', $initiative)];
    foreach (Walkthrough::activities() as $activity) {
        if ($activity['route'] !== null) {
            $urls[] = route($activity['route'], $initiative);
        }
    }

    visit($urls)->assertNoSmoke();
});

it('loads every built activity cleanly for a read-only viewer', function () {
    $initiative = larkspur();
    $this->actingAs(User::firstWhere('email', 'dana@'.LarkspurScenarioSeeder::EMAIL_DOMAIN));

    $urls = [route('initiatives.show', $initiative)];
    foreach (Walkthrough::activities() as $activity) {
        if ($activity['route'] !== null) {
            $urls[] = route($activity['route'], $initiative);
        }
    }

    visit($urls)->assertNoSmoke();
});

it('loads the admin pages cleanly', function () {
    $this->actingAs(User::factory()->admin()->create());

    visit([route('admin.users.index'), route('profile.edit')])->assertNoSmoke();
});

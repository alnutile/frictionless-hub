<?php

namespace Tests\Feature;

use App\Enums\Phase;
use App\Models\Initiative;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InitiativeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('initiatives.index'))->assertRedirect(route('login'));
    }

    public function test_viewers_can_list_initiatives_but_not_create_them(): void
    {
        $viewer = User::factory()->create();
        Initiative::factory()->create(['name' => 'Platform DevEx']);

        $this->actingAs($viewer)
            ->get(route('initiatives.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('initiatives/index')
                ->where('canCreate', false)
                ->where('initiatives.0.name', 'Platform DevEx'));

        $this->actingAs($viewer)
            ->post(route('initiatives.store'), ['name' => 'Nope'])
            ->assertForbidden();

        $this->assertDatabaseMissing('initiatives', ['name' => 'Nope']);
    }

    public function test_creating_an_initiative_seeds_default_stages_and_dimensions(): void
    {
        $facilitator = User::factory()->facilitator()->create();

        $response = $this->actingAs($facilitator)->post(route('initiatives.store'), [
            'name' => 'Mobile DevEx',
            'scope' => 'Mobile org',
        ]);

        $initiative = Initiative::firstWhere('name', 'Mobile DevEx');
        $response->assertRedirect(route('initiatives.show', $initiative));

        $this->assertSame($facilitator->id, $initiative->owner_id);
        $this->assertSame(Phase::Discover, $initiative->phase);
        $this->assertSame(config('frictionless.default_stages'), $initiative->stages()->pluck('name')->all());
        $this->assertSame(config('frictionless.default_dimensions'), $initiative->dimensions()->pluck('name')->all());
    }

    public function test_initiative_requires_a_name(): void
    {
        $this->actingAs(User::factory()->facilitator()->create())
            ->post(route('initiatives.store'), ['name' => ''])
            ->assertSessionHasErrors(['name' => 'The name field is required.']);
    }

    public function test_owner_can_change_phase_and_rejects_unknown_phase(): void
    {
        $initiative = Initiative::factory()->create();

        $this->actingAs($initiative->owner)
            ->patch(route('initiatives.update', $initiative), ['name' => $initiative->name, 'phase' => 'data'])
            ->assertSessionHasNoErrors();

        $this->assertSame(Phase::Data, $initiative->fresh()->phase);

        $this->actingAs($initiative->owner)
            ->patch(route('initiatives.update', $initiative), ['name' => $initiative->name, 'phase' => 'bogus'])
            ->assertSessionHasErrors('phase');
    }

    public function test_facilitator_cannot_edit_or_delete_someone_elses_initiative(): void
    {
        $initiative = Initiative::factory()->create(['name' => 'Original']);
        $other = User::factory()->facilitator()->create();

        $this->actingAs($other)
            ->patch(route('initiatives.update', $initiative), ['name' => 'Hijacked'])
            ->assertForbidden();

        $this->actingAs($other)->delete(route('initiatives.destroy', $initiative))->assertForbidden();

        $this->assertSame('Original', $initiative->fresh()->name);
    }

    public function test_owner_can_delete_initiative(): void
    {
        $initiative = Initiative::factory()->create();

        $this->actingAs($initiative->owner)
            ->delete(route('initiatives.destroy', $initiative))
            ->assertRedirect(route('initiatives.index'));

        $this->assertModelMissing($initiative);
    }
}

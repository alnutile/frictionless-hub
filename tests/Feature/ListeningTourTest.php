<?php

namespace Tests\Feature;

use App\Models\Initiative;
use App\Models\Participant;
use App\Models\SegmentValue;
use App\Models\User;
use App\Services\AssistantContext;
use Database\Seeders\LarkspurScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ListeningTourTest extends TestCase
{
    use RefreshDatabase;

    private Initiative $initiative;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LarkspurScenarioSeeder::class);
        $this->initiative = Initiative::firstWhere('name', LarkspurScenarioSeeder::INITIATIVE_NAME);
    }

    private function viewer(): User
    {
        return User::firstWhere('email', 'dana@'.LarkspurScenarioSeeder::EMAIL_DOMAIN);
    }

    private function value(string $name): SegmentValue
    {
        return $this->initiative->segmentValues()->where('segment_values.name', $name)->firstOrFail();
    }

    private function addParticipant(array $overrides = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->initiative->owner)->post(route('initiatives.participants.store', $this->initiative), [
            'name' => 'New Person',
            'status' => 'candidate',
            'area_value_id' => $this->value('Payments')->id,
            'value_ids' => [$this->value('Mid-level')->id],
            ...$overrides,
        ]);
    }

    public function test_owner_sees_participants_coverage_and_suggestions(): void
    {
        $this->actingAs($this->initiative->owner)
            ->get(route('initiatives.listening-tour', $this->initiative))
            ->assertInertia(fn (Assert $page) => $page
                ->component('initiatives/listening-tour')
                ->has('participants', 13)
                ->where('coverage.total', 12)
                ->where('coverage.remaining', 2)
                ->where('coverage.issues', [['area' => 'Mobile', 'category' => 'Time at Larkspur', 'missing' => 1]])
                ->where('coverage.rows', fn ($rows) => collect($rows)->last()['area'] === 'No area' && collect($rows)->sum('total') === 12)
                ->where('suggestions.has_data', true)
                ->where('suggestions.hot_stages.0.stage', 'Deployment')
                ->where('statusCounts.interviewed', 5)
                ->where('statusCounts.declined', 1));
    }

    public function test_viewers_see_coverage_but_not_who_the_participants_are(): void
    {
        $response = $this->actingAs($this->viewer())->get(route('initiatives.listening-tour', $this->initiative));

        $response->assertInertia(fn (Assert $page) => $page->where('participants', null)->where('coverage.total', 12));
        $this->assertStringNotContainsString('Riya Desai', $response->getContent());
    }

    public function test_viewers_cannot_change_the_tour(): void
    {
        $this->addParticipant(as: $this->viewer())->assertForbidden();

        $this->actingAs($this->viewer())
            ->post(route('initiatives.message-templates.store', $this->initiative), ['name' => 'x', 'channel' => 'email', 'body' => 'x'])
            ->assertForbidden();
    }

    public function test_adds_a_participant_with_an_area_and_group_values(): void
    {
        $this->addParticipant()->assertSessionHasNoErrors();

        $person = $this->initiative->participants()->firstWhere('name', 'New Person');
        $this->assertEqualsCanonicalizing(
            [$this->value('Payments')->id, $this->value('Mid-level')->id],
            $person->segmentValues()->pluck('segment_values.id')->all(),
        );
    }

    public function test_rejects_two_values_from_the_same_group(): void
    {
        $this->addParticipant(['value_ids' => [$this->value('Mid-level')->id, $this->value('Early career')->id]])
            ->assertSessionHasErrors(['value_ids.1' => 'Choose only one Experience value per person.']);
    }

    public function test_rejects_areas_and_values_from_another_initiative_or_the_wrong_group(): void
    {
        $other = Initiative::factory()->create();
        $foreignArea = $other->areaCategory()->values()->create(['name' => 'Elsewhere']);

        $this->addParticipant(['area_value_id' => $foreignArea->id])
            ->assertSessionHasErrors(['area_value_id' => "Choose one of this initiative's work areas."]);

        $this->addParticipant(['area_value_id' => $this->value('Senior / staff')->id])
            ->assertSessionHasErrors('area_value_id');

        $this->addParticipant(['value_ids' => [$foreignArea->id]])
            ->assertSessionHasErrors(['value_ids.0' => 'That group value does not belong to this initiative.']);

        $this->addParticipant(['value_ids' => [$this->value('Web')->id]])
            ->assertSessionHasErrors('value_ids.0');
    }

    public function test_participant_from_another_initiative_returns_not_found(): void
    {
        $foreign = Participant::factory()->create();

        $this->actingAs($this->initiative->owner)
            ->delete(route('initiatives.participants.destroy', [$this->initiative, $foreign]))
            ->assertNotFound();
    }

    public function test_toggles_a_checklist_item_and_rejects_cross_initiative_ids(): void
    {
        $person = $this->initiative->participants()->firstWhere('name', 'Kenji Mori');
        $item = $this->initiative->checklistItems()->first();

        $this->actingAs($this->initiative->owner)
            ->post(route('initiatives.participants.checklist', [$this->initiative, $person, $item]));
        $this->assertSame([$item->id], $person->completedChecklistItems()->pluck('checklist_items.id')->all());

        $this->actingAs($this->initiative->owner)
            ->post(route('initiatives.participants.checklist', [$this->initiative, $person, $item]));
        $this->assertSame(0, $person->completedChecklistItems()->count());

        $foreignPerson = Participant::factory()->create();
        $this->actingAs($this->initiative->owner)
            ->post(route('initiatives.participants.checklist', [$this->initiative, $foreignPerson, $item]))
            ->assertNotFound();

        $foreignItem = Initiative::factory()->create()->checklistItems()->create(['label' => 'Elsewhere']);
        $this->actingAs($this->initiative->owner)
            ->post(route('initiatives.participants.checklist', [$this->initiative, $person, $foreignItem]))
            ->assertNotFound();
    }

    public function test_groups_can_be_added_and_the_work_area_group_cannot_be_removed(): void
    {
        $owner = $this->initiative->owner;

        $this->actingAs($owner)
            ->post(route('initiatives.segment-categories.store', $this->initiative), ['name' => 'Experience'])
            ->assertSessionHasErrors(['name' => 'The name has already been taken.']);

        $this->actingAs($owner)->post(route('initiatives.segment-categories.store', $this->initiative), ['name' => 'Location']);
        $location = $this->initiative->segmentCategories()->firstWhere('name', 'Location');
        $this->actingAs($owner)->post(route('initiatives.segment-values.store', [$this->initiative, $location]), ['name' => 'Remote']);
        $this->assertSame(['Remote'], $location->values()->pluck('name')->all());

        $this->actingAs($owner)
            ->delete(route('initiatives.segment-categories.destroy', [$this->initiative, $this->initiative->areaCategory()]))
            ->assertStatus(422);
    }

    public function test_target_updates_and_shows_on_the_overview_ladder(): void
    {
        $this->actingAs($this->initiative->owner)
            ->put(route('initiatives.listening-tour.target', $this->initiative), ['interview_target' => 20])
            ->assertSessionHasNoErrors();

        $this->assertSame(20, $this->initiative->fresh()->interview_target);

        $this->actingAs($this->initiative->owner)
            ->get(route('initiatives.show', $this->initiative))
            ->assertInertia(fn (Assert $page) => $page
                ->where('progress.listening-tour.count', 12)
                ->where('progress.listening-tour.label', 'people on the tour · 5 interviewed'));
    }

    public function test_a_new_initiative_gets_a_work_area_group_and_a_teaching_empty_state(): void
    {
        $initiative = Initiative::factory()->create();
        $initiative->seedDefaults();

        $this->actingAs($initiative->owner)
            ->get(route('initiatives.listening-tour', $initiative))
            ->assertInertia(fn (Assert $page) => $page
                ->where('areas', [])
                ->where('coverage.total', 0)
                ->where('suggestions.has_data', false));

        $this->assertTrue($initiative->segmentCategories()->where('is_area', true)->exists());
    }

    public function test_assistant_context_gets_tour_counts_but_never_names(): void
    {
        $context = AssistantContext::build($this->initiative->owner, $this->initiative, 'listening-tour');

        $this->assertStringContainsString('### Listening tour (counts only; names are private)', $context);
        $this->assertStringContainsString('- Target: 14', $context);
        $this->assertStringContainsString('Interviewed 5', $context);
        $this->assertStringContainsString('Payments 1', $context);
        $this->assertStringNotContainsString('Riya Desai', $context);
        $this->assertStringNotContainsString('Kenji', $context);
    }
}

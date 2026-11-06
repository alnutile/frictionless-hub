<?php

namespace Tests\Feature;

use App\Enums\InterviewStatus;
use App\Enums\ParticipantStatus;
use App\Models\Code;
use App\Models\Initiative;
use App\Models\Interview;
use App\Models\InterviewNote;
use App\Models\Participant;
use App\Models\User;
use App\Services\AssistantContext;
use Database\Seeders\LarkspurScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InterviewTest extends TestCase
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

    private function participant(string $name): Participant
    {
        return $this->initiative->participants()->where('name', $name)->firstOrFail();
    }

    private function interviewOf(string $name): Interview
    {
        return $this->participant($name)->interview;
    }

    public function test_owner_sees_interviews_codes_and_who_is_ready(): void
    {
        $this->actingAs($this->initiative->owner)
            ->get(route('initiatives.interviews', $this->initiative))
            ->assertInertia(fn (Assert $page) => $page
                ->component('initiatives/interviews')
                ->has('interviews', 5)
                ->where('stats.complete', 5)
                ->where('stats.open_follow_ups', 2)
                ->where('codeFrequency.0', fn ($top) => $top['interviews'] >= 3)
                ->where('toInterview', fn ($people) => collect($people)->pluck('name')->contains('Marta Kowalski')
                    && ! collect($people)->pluck('name')->contains('Grace Lin')));
    }

    public function test_viewers_see_counts_and_codes_but_no_notes_or_names(): void
    {
        $response = $this->actingAs($this->viewer())->get(route('initiatives.interviews', $this->initiative));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('interviews', null)
            ->where('toInterview', null)
            ->where('openFollowUps', null)
            ->where('stats.complete', 5)
            ->has('codeFrequency', 10));
        $this->assertStringNotContainsString('Jo Haddad', $response->getContent());
        $this->assertStringNotContainsString('make coffee', $response->getContent());

        $this->actingAs($this->viewer())
            ->get(route('initiatives.interviews.show', [$this->initiative, $this->interviewOf('Jo Haddad')]))
            ->assertForbidden();
    }

    public function test_starting_notes_creates_one_interview_per_participant(): void
    {
        $marta = $this->participant('Marta Kowalski');

        $first = $this->actingAs($this->initiative->owner)->post(route('initiatives.interviews.store', $this->initiative), ['participant_id' => $marta->id]);
        $interview = $marta->fresh()->interview;
        $first->assertRedirect(route('initiatives.interviews.show', [$this->initiative, $interview]));

        $this->actingAs($this->initiative->owner)->post(route('initiatives.interviews.store', $this->initiative), ['participant_id' => $marta->id]);

        $this->assertSame(1, Interview::where('participant_id', $marta->id)->count());
        $this->assertSame(InterviewStatus::Draft, $interview->status);
    }

    public function test_cannot_start_notes_for_someone_on_another_initiatives_tour(): void
    {
        $foreign = Participant::factory()->create();

        $this->actingAs($this->initiative->owner)
            ->post(route('initiatives.interviews.store', $this->initiative), ['participant_id' => $foreign->id])
            ->assertSessionHasErrors('participant_id');
    }

    public function test_marking_complete_marks_the_participant_interviewed(): void
    {
        $marta = $this->participant('Marta Kowalski');
        $interview = $this->initiative->interviews()->create(['participant_id' => $marta->id, 'status' => 'draft']);

        $this->actingAs($this->initiative->owner)
            ->patch(route('initiatives.interviews.update', [$this->initiative, $interview]), ['status' => 'complete', 'tools' => 'VS Code'])
            ->assertSessionHasNoErrors();

        $this->assertSame(ParticipantStatus::Interviewed, $marta->fresh()->status);
        $this->assertSame('VS Code', $interview->fresh()->tools);
    }

    public function test_adds_a_coded_pain_point_with_a_stage(): void
    {
        $interview = $this->interviewOf('Sam Okafor');
        $stage = $this->initiative->stages()->firstWhere('name', 'Testing');
        $code = Code::firstWhere('name', 'flaky-tests');

        $this->actingAs($this->initiative->owner)
            ->post(route('initiatives.interview-notes.store', [$this->initiative, $interview]), [
                'kind' => 'pain_point',
                'body' => 'E2E suite is flaky on Mondays',
                'workflow_stage_id' => $stage->id,
                'code_ids' => [$code->id],
            ])
            ->assertSessionHasNoErrors();

        $note = InterviewNote::firstWhere('body', 'E2E suite is flaky on Mondays');
        $this->assertSame($stage->id, $note->workflow_stage_id);
        $this->assertSame([$code->id], $note->codes()->pluck('codes.id')->all());
    }

    public function test_rejects_stages_and_codes_from_another_initiative(): void
    {
        $other = Initiative::factory()->create();
        $other->seedDefaults();
        $foreignCode = $other->codes()->create(['name' => 'elsewhere']);

        $this->actingAs($this->initiative->owner)
            ->post(route('initiatives.interview-notes.store', [$this->initiative, $this->interviewOf('Sam Okafor')]), [
                'kind' => 'pain_point',
                'body' => 'x',
                'workflow_stage_id' => $other->stages()->first()->id,
                'code_ids' => [$foreignCode->id],
            ])
            ->assertSessionHasErrors(['workflow_stage_id', 'code_ids.0']);
    }

    public function test_a_note_from_another_interview_returns_not_found(): void
    {
        $samsNote = $this->interviewOf('Sam Okafor')->notes()->first();

        $this->actingAs($this->initiative->owner)
            ->delete(route('initiatives.interview-notes.destroy', [$this->initiative, $this->interviewOf('Jo Haddad'), $samsNote]))
            ->assertNotFound();

        $this->assertModelExists($samsNote);
    }

    public function test_adding_a_pain_point_to_the_friction_log_copies_it_without_the_name(): void
    {
        $note = $this->interviewOf('Sam Okafor')->notes()->where('body', 'like', 'Preview environments take a day%')->firstOrFail();
        $before = $this->initiative->frictionPoints()->count();

        $this->actingAs($this->initiative->owner)
            ->post(route('initiatives.interview-notes.promote', [$this->initiative, $note->interview, $note]))
            ->assertSessionHas('success', 'Added to the friction log. Set its frequency and pain there.');

        $point = $note->fresh()->frictionPoint;
        $this->assertSame($before + 1, $this->initiative->frictionPoints()->count());
        $this->assertSame('Web', $point->affected);
        $this->assertSame($note->workflow_stage_id, $point->workflow_stage_id);
        $this->assertStringNotContainsString('Sam', $point->title.$point->description);

        $this->actingAs($this->initiative->owner)->post(route('initiatives.interview-notes.promote', [$this->initiative, $note->interview, $note]));
        $this->assertSame($before + 1, $this->initiative->frictionPoints()->count(), 'Promoting twice must not duplicate.');
    }

    public function test_only_pain_points_can_go_to_the_friction_log(): void
    {
        $quote = $this->interviewOf('Jo Haddad')->notes()->where('kind', 'quote')->firstOrFail();

        $this->actingAs($this->initiative->owner)
            ->post(route('initiatives.interview-notes.promote', [$this->initiative, $quote->interview, $quote]))
            ->assertStatus(422);
    }

    public function test_follow_ups_toggle_done(): void
    {
        $followUp = $this->interviewOf('Ama Mensah')->notes()->where('kind', 'follow_up')->firstOrFail();

        $this->actingAs($this->initiative->owner)->post(route('initiatives.interview-notes.toggle', [$this->initiative, $followUp->interview, $followUp]));

        $this->assertTrue($followUp->fresh()->done);
    }

    public function test_creating_a_code_can_apply_it_to_a_note_in_one_step(): void
    {
        $note = $this->interviewOf('Riya Desai')->notes()->first();

        $this->actingAs($this->initiative->owner)
            ->post(route('initiatives.codes.store', $this->initiative), ['name' => '  mentoring ', 'note_id' => $note->id])
            ->assertSessionHasNoErrors();

        $this->assertTrue($note->codes()->where('name', 'mentoring')->exists());

        $this->actingAs($this->initiative->owner)
            ->post(route('initiatives.codes.store', $this->initiative), ['name' => 'mentoring'])
            ->assertSessionHasErrors(['name' => 'The name has already been taken.']);
    }

    public function test_cannot_apply_a_new_code_to_another_initiatives_note(): void
    {
        $other = Initiative::factory()->create();
        $foreignInterview = $other->interviews()->create(['participant_id' => Participant::factory()->for($other)->create()->id]);
        $foreignNote = $foreignInterview->notes()->create(['kind' => 'quote', 'body' => 'elsewhere']);

        $this->actingAs($this->initiative->owner)
            ->post(route('initiatives.codes.store', $this->initiative), ['name' => 'sneaky', 'note_id' => $foreignNote->id])
            ->assertNotFound();

        $this->assertSame(0, $foreignNote->codes()->count());
    }

    public function test_assistant_context_has_codes_for_everyone_and_note_text_only_for_managers(): void
    {
        $forOwner = AssistantContext::build($this->initiative->owner, $this->initiative, 'interviews');
        $forViewer = AssistantContext::build($this->viewer(), $this->initiative, 'interviews');

        foreach ([$forOwner, $forViewer] as $context) {
            $this->assertStringContainsString('### Interviews', $context);
            $this->assertStringContainsString('- Complete: 5, in progress: 0', $context);
            $this->assertStringContainsString('slow-ci 3', $context);
            $this->assertStringNotContainsString('Jo Haddad', $context);
        }

        $this->assertStringContainsString('[Hedgehog (legacy)] Quotes: Kick off CI, make coffee', $forOwner);
        $this->assertStringNotContainsString('make coffee', $forViewer);
    }

    public function test_seeded_pain_points_link_to_existing_friction_points(): void
    {
        $note = $this->interviewOf('Jo Haddad')->notes()->where('body', 'like', 'The Hedgehog CI suite%')->firstOrFail();

        $this->assertSame('Hedgehog CI suite takes ~38 minutes', $note->frictionPoint?->title);
    }

    public function test_list_reports_note_counts_and_tour_size_for_the_empty_state_copy(): void
    {
        $this->actingAs($this->initiative->owner)
            ->get(route('initiatives.interviews', $this->initiative))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tourSize', 13)
                ->where('interviews', fn ($rows) => collect($rows)->every(fn ($row) => $row['note_count'] > 0)));

        $empty = Initiative::factory()->create();
        $this->actingAs($empty->owner)
            ->get(route('initiatives.interviews', $empty))
            ->assertInertia(fn (Assert $page) => $page->where('tourSize', 0)->where('toInterview', []));
    }
}

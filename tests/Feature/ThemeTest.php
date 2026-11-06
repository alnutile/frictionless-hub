<?php

namespace Tests\Feature;

use App\Models\Code;
use App\Models\FrictionPoint;
use App\Models\Initiative;
use App\Models\Theme;
use App\Models\User;
use App\Services\AssistantContext;
use Database\Seeders\LarkspurScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ThemeTest extends TestCase
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

    private function theme(string $name): Theme
    {
        return $this->initiative->themes()->where('name', $name)->firstOrFail();
    }

    private function code(string $name): Code
    {
        return $this->initiative->codes()->where('name', $name)->firstOrFail();
    }

    public function test_themes_are_weighted_by_interviews_and_notes_under_their_codes(): void
    {
        $this->actingAs($this->initiative->owner)
            ->get(route('initiatives.themes', $this->initiative))
            ->assertInertia(fn (Assert $page) => $page
                ->component('initiatives/themes')
                ->where('themes.0.name', 'Waiting on builds and environments')
                ->where('themes.0.weight.interviews', 4)
                ->where('themes.0.weight.notes', 7)
                ->where('themes.0.weight.areas', fn ($areas) => collect($areas)->sum('interviews') === 4)
                ->where('themes.1.name', 'Reviews and releases stall on people')
                ->where('themes.1.weight.interviews', 3)
                ->where('themes.2.name', 'Know-how lives in people, not docs')
                ->where('themes.2.weight', ['notes' => 6, 'interviews' => 2, 'areas' => [
                    ['area' => 'Web', 'interviews' => 1],
                    ['area' => 'Hedgehog (legacy)', 'interviews' => 1],
                ]])
                ->where('codeCount', 10)
                ->where('unthemedCodes', fn ($codes) => collect($codes)->pluck('name')->sort()->values()->all() === ['ai-assistants', 'env-setup', 'on-call'])
                ->where('uncodedCount', 1)
                ->where('uncodedNotes.0.body', 'The Hedgehog team is close-knit and quick to help each other.'));
    }

    public function test_viewers_see_themes_and_weights_but_not_notes_or_names(): void
    {
        $response = $this->actingAs($this->viewer())->get(route('initiatives.themes', $this->initiative));

        $response->assertInertia(fn (Assert $page) => $page->has('themes', 3)->where('uncodedNotes', null)->where('uncodedCount', 1));
        $this->assertStringNotContainsString('Jo Haddad', $response->getContent());
        $this->assertStringNotContainsString('close-knit', $response->getContent());

        $this->actingAs($this->viewer())
            ->get(route('initiatives.themes.show', [$this->initiative, $this->theme('Waiting on builds and environments')]))
            ->assertForbidden();

        $this->actingAs($this->viewer())
            ->post(route('initiatives.themes.store', $this->initiative), ['name' => 'Nope'])
            ->assertForbidden();
    }

    public function test_evidence_groups_the_notes_behind_a_theme_by_interview(): void
    {
        $this->actingAs($this->initiative->owner)
            ->get(route('initiatives.themes.show', [$this->initiative, $this->theme('Know-how lives in people, not docs')]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('initiatives/theme')
                ->has('interviews', 2)
                ->where('interviews', fn ($rows) => collect($rows)->pluck('participant')->sort()->values()->all() === ['Jo Haddad', 'Riya Desai'])
                ->where('interviews', fn ($rows) => collect($rows)->sum(fn ($r) => count($r['notes'])) === 6));
    }

    public function test_a_code_belongs_to_one_theme_so_creating_a_theme_moves_it(): void
    {
        $this->actingAs($this->initiative->owner)
            ->post(route('initiatives.themes.store', $this->initiative), [
                'name' => 'Tooling gaps',
                'impact' => 'medium',
                'code_ids' => [$this->code('env-setup')->id, $this->code('slow-ci')->id],
            ])
            ->assertSessionHasNoErrors();

        $tooling = $this->theme('Tooling gaps');
        $this->assertSame($tooling->id, $this->code('slow-ci')->theme_id);
        $this->assertSame($tooling->id, $this->code('env-setup')->theme_id);
        $this->assertFalse($this->theme('Waiting on builds and environments')->codes()->where('name', 'slow-ci')->exists());
    }

    public function test_updating_a_themes_codes_releases_the_ones_left_out(): void
    {
        $theme = $this->theme('Reviews and releases stall on people');

        $this->actingAs($this->initiative->owner)
            ->patch(route('initiatives.themes.update', [$this->initiative, $theme]), [
                'name' => $theme->name,
                'code_ids' => [$this->code('review-wait')->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($this->code('release-process')->theme_id);
    }

    public function test_deleting_a_theme_returns_its_codes_to_the_unthemed_list(): void
    {
        $theme = $this->theme('Know-how lives in people, not docs');

        $this->actingAs($this->initiative->owner)->delete(route('initiatives.themes.destroy', [$this->initiative, $theme]));

        $this->assertNull($this->code('docs-gaps')->theme_id);
        $this->assertNull($this->code('onboarding')->theme_id);
    }

    public function test_rejects_codes_and_friction_points_from_another_initiative(): void
    {
        $other = Initiative::factory()->create();
        $foreignCode = $other->codes()->create(['name' => 'elsewhere']);
        $foreignPoint = FrictionPoint::factory()->for($other)->create();
        $theme = $this->theme('Know-how lives in people, not docs');

        $this->actingAs($this->initiative->owner)
            ->post(route('initiatives.themes.store', $this->initiative), ['name' => 'x', 'code_ids' => [$foreignCode->id]])
            ->assertSessionHasErrors('code_ids.0');

        $this->actingAs($this->initiative->owner)
            ->put(route('initiatives.themes.link', [$this->initiative, $theme]), ['friction_point_id' => $foreignPoint->id])
            ->assertSessionHasErrors('friction_point_id');

        $this->actingAs($this->initiative->owner)
            ->put(route('initiatives.codes.theme', [$this->initiative, $foreignCode]), ['theme_id' => $theme->id])
            ->assertNotFound();
    }

    public function test_adding_a_theme_to_the_friction_log_uses_its_common_stage_and_areas_without_names(): void
    {
        $theme = $this->theme('Know-how lives in people, not docs');
        $before = FrictionPoint::count();

        $this->actingAs($this->initiative->owner)
            ->post(route('initiatives.themes.promote', [$this->initiative, $theme]))
            ->assertSessionHas('success', 'Added to the friction log. Set its frequency and pain there.');

        $point = $theme->fresh()->frictionPoint;
        $this->assertSame('Know-how lives in people, not docs', $point->title);
        $this->assertSame('Web, Hedgehog (legacy)', $point->affected);
        $this->assertSame('Coding', $point->stage->name);
        $this->assertStringContainsString('(Theme from 2 interviews.)', $point->description);
        $this->assertStringNotContainsString('Riya', $point->title.$point->description.$point->affected);

        $this->actingAs($this->initiative->owner)->post(route('initiatives.themes.promote', [$this->initiative, $theme]));
        $this->assertSame($before + 1, FrictionPoint::count());
    }

    public function test_links_and_unlinks_an_existing_friction_point(): void
    {
        $theme = $this->theme('Know-how lives in people, not docs');
        $point = $this->initiative->frictionPoints()->where('title', 'like', 'Requirements for Hedgehog%')->firstOrFail();

        $this->actingAs($this->initiative->owner)->put(route('initiatives.themes.link', [$this->initiative, $theme]), ['friction_point_id' => $point->id]);
        $this->assertSame($point->id, $theme->fresh()->friction_point_id);

        $this->actingAs($this->initiative->owner)->put(route('initiatives.themes.link', [$this->initiative, $theme]), ['friction_point_id' => null]);
        $this->assertNull($theme->fresh()->friction_point_id);
    }

    public function test_assigns_a_leftover_code_to_a_theme(): void
    {
        $this->actingAs($this->initiative->owner)
            ->put(route('initiatives.codes.theme', [$this->initiative, $this->code('env-setup')]), ['theme_id' => $this->theme('Know-how lives in people, not docs')->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($this->theme('Know-how lives in people, not docs')->id, $this->code('env-setup')->theme_id);
    }

    public function test_an_initiative_without_codes_gets_the_teaching_empty_state(): void
    {
        $empty = Initiative::factory()->create();

        $this->actingAs($empty->owner)
            ->get(route('initiatives.themes', $empty))
            ->assertInertia(fn (Assert $page) => $page->where('codeCount', 0)->where('themes', []));
    }

    public function test_assistant_context_lists_themes_with_weights_for_every_role_without_names(): void
    {
        $context = AssistantContext::build($this->viewer(), $this->initiative, 'themes');

        $this->assertStringContainsString('### Themes (from interview codes)', $context);
        $this->assertStringContainsString('- Waiting on builds and environments: 4 interviews, 7 notes', $context);
        $this->assertStringContainsString('in friction log as "Hedgehog CI suite takes ~38 minutes"', $context);
        $this->assertStringContainsString('- Codes not in any theme: ai-assistants, env-setup, on-call', $context);
        $this->assertStringNotContainsString('Riya', $context);
    }
}

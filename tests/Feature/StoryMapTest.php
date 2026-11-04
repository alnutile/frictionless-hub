<?php

namespace Tests\Feature;

use App\Enums\StoryMapKind;
use App\Models\Initiative;
use App\Models\StoryMapItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StoryMapTest extends TestCase
{
    use RefreshDatabase;

    private Initiative $initiative;

    protected function setUp(): void
    {
        parent::setUp();

        $this->initiative = Initiative::factory()->create();
    }

    private function add(array $payload): void
    {
        $this->actingAs($this->initiative->owner)
            ->post(route('initiatives.story-map.store', $this->initiative), $payload)
            ->assertSessionHasNoErrors();
    }

    public function test_level_is_derived_from_the_parent(): void
    {
        $this->add(['title' => 'Ship it']);
        $activity = StoryMapItem::firstWhere('title', 'Ship it');

        $this->add(['title' => 'Release', 'parent_id' => $activity->id]);
        $task = StoryMapItem::firstWhere('title', 'Release');

        $this->add(['title' => 'Manual sign-offs', 'parent_id' => $task->id, 'kind' => 'friction']);
        $card = StoryMapItem::firstWhere('title', 'Manual sign-offs');

        $this->assertSame(StoryMapItem::LEVEL_ACTIVITY, $activity->level);
        $this->assertNull($activity->kind);
        $this->assertSame(StoryMapItem::LEVEL_TASK, $task->level);
        $this->assertSame(StoryMapItem::LEVEL_CARD, $card->level);
        $this->assertSame(StoryMapKind::Friction, $card->kind);
    }

    public function test_cards_default_to_the_story_layer(): void
    {
        $task = StoryMapItem::factory()->for($this->initiative)->create(['level' => StoryMapItem::LEVEL_TASK]);

        $this->add(['title' => 'Step', 'parent_id' => $task->id]);

        $this->assertSame(StoryMapKind::Story, StoryMapItem::firstWhere('title', 'Step')->kind);
    }

    public function test_cards_cannot_have_children(): void
    {
        $card = StoryMapItem::factory()->for($this->initiative)->create(['level' => StoryMapItem::LEVEL_CARD, 'kind' => 'story']);

        $this->actingAs($this->initiative->owner)
            ->post(route('initiatives.story-map.store', $this->initiative), ['title' => 'Too deep', 'parent_id' => $card->id])
            ->assertSessionHasErrors(['parent_id' => 'Cards cannot have children.']);
    }

    public function test_rejects_a_parent_from_another_initiative(): void
    {
        $foreign = StoryMapItem::factory()->create();

        $this->actingAs($this->initiative->owner)
            ->post(route('initiatives.story-map.store', $this->initiative), ['title' => 'Sneaky', 'parent_id' => $foreign->id])
            ->assertSessionHasErrors('parent_id');

        $this->actingAs($this->initiative->owner)
            ->delete(route('initiatives.story-map.destroy', [$this->initiative, $foreign]))
            ->assertNotFound();
    }

    public function test_viewer_cannot_edit_the_map(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('initiatives.story-map.store', $this->initiative), ['title' => 'Nope'])
            ->assertForbidden();
    }

    public function test_deleting_an_activity_removes_its_tasks_and_cards(): void
    {
        $activity = StoryMapItem::factory()->for($this->initiative)->create();
        $task = StoryMapItem::factory()->for($this->initiative)->create(['parent_id' => $activity->id, 'level' => StoryMapItem::LEVEL_TASK]);
        $card = StoryMapItem::factory()->for($this->initiative)->create(['parent_id' => $task->id, 'level' => StoryMapItem::LEVEL_CARD, 'kind' => 'gap']);

        $this->actingAs($this->initiative->owner)->delete(route('initiatives.story-map.destroy', [$this->initiative, $activity]));

        $this->assertModelMissing($task);
        $this->assertModelMissing($card);
    }

    public function test_moving_a_task_only_reorders_its_siblings(): void
    {
        $activity = StoryMapItem::factory()->for($this->initiative)->create();
        [$first, $second] = collect(['First', 'Second'])->map(fn (string $title, int $i) => StoryMapItem::factory()->for($this->initiative)->create([
            'parent_id' => $activity->id, 'level' => StoryMapItem::LEVEL_TASK, 'title' => $title, 'position' => $i,
        ]))->all();

        $this->actingAs($this->initiative->owner)
            ->post(route('initiatives.story-map.move', [$this->initiative, $second]), ['direction' => 'up']);

        $this->assertSame(['Second', 'First'], $activity->children()->pluck('title')->all());
    }

    public function test_page_returns_nested_structure(): void
    {
        $activity = StoryMapItem::factory()->for($this->initiative)->create(['title' => 'Build']);
        $task = StoryMapItem::factory()->for($this->initiative)->create(['parent_id' => $activity->id, 'level' => StoryMapItem::LEVEL_TASK, 'title' => 'Code']);
        StoryMapItem::factory()->for($this->initiative)->create(['parent_id' => $task->id, 'level' => StoryMapItem::LEVEL_CARD, 'kind' => 'tool', 'title' => 'Editor']);

        $this->actingAs(User::factory()->create())
            ->get(route('initiatives.story-map', $this->initiative))
            ->assertInertia(fn (Assert $page) => $page
                ->component('initiatives/story-map')
                ->where('activities.0.title', 'Build')
                ->where('activities.0.tasks.0.title', 'Code')
                ->where('activities.0.tasks.0.cards.0.kind', 'tool'));
    }
}

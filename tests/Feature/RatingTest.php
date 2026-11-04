<?php

namespace Tests\Feature;

use App\Models\Initiative;
use App\Models\RatingGroup;
use App\Models\RatingItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RatingTest extends TestCase
{
    use RefreshDatabase;

    private Initiative $initiative;

    private RatingItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->initiative = Initiative::factory()->create();
        $group = RatingGroup::factory()->for($this->initiative)->create();
        $this->item = RatingItem::factory()->for($group, 'group')->create();
    }

    private function rate(User $user, ?int $score, ?RatingItem $item = null)
    {
        return $this->actingAs($user)->put(
            route('initiatives.rating-items.rate', [$this->initiative, $item ?? $this->item]),
            ['score' => $score],
        );
    }

    public function test_any_signed_in_user_can_rate_and_re_rating_replaces_their_score(): void
    {
        $viewer = User::factory()->create();

        $this->rate($viewer, 2)->assertSessionHasNoErrors();
        $this->rate($viewer, 4);

        $this->assertSame([4], $this->item->ratings()->pluck('score')->all());
    }

    public function test_a_null_score_clears_the_rating(): void
    {
        $viewer = User::factory()->create();
        $this->rate($viewer, 3);

        $this->rate($viewer, null);

        $this->assertSame(0, $this->item->ratings()->count());
    }

    public function test_rejects_scores_outside_one_to_five(): void
    {
        $this->rate(User::factory()->create(), 0)
            ->assertSessionHasErrors(['score' => 'The score field must be between 1 and 5.']);
    }

    public function test_item_from_another_initiative_returns_not_found(): void
    {
        $foreign = RatingItem::factory()->create();

        $this->rate(User::factory()->create(), 3, $foreign)->assertNotFound();
    }

    public function test_viewers_cannot_manage_groups_or_items(): void
    {
        $viewer = User::factory()->create();

        $this->actingAs($viewer)
            ->post(route('initiatives.rating-groups.store', $this->initiative), ['name' => 'Nope'])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->delete(route('initiatives.rating-items.destroy', [$this->initiative, $this->item]))
            ->assertForbidden();
    }

    public function test_page_shows_average_and_the_viewers_own_score(): void
    {
        $me = User::factory()->create();
        $this->rate($me, 2);
        $this->rate(User::factory()->create(), 5);

        $this->actingAs($me)
            ->get(route('initiatives.ratings', $this->initiative))
            ->assertInertia(fn (Assert $page) => $page
                ->component('initiatives/ratings')
                ->where('raters', 2)
                ->where('groups.0.items.0.mine', 2)
                ->where('groups.0.items.0.average', 3.5)
                ->where('groups.0.items.0.spread', 3));
    }

    public function test_owner_can_add_group_and_item(): void
    {
        $this->actingAs($this->initiative->owner)
            ->post(route('initiatives.rating-groups.store', $this->initiative), ['name' => 'Shipping']);

        $group = $this->initiative->ratingGroups()->firstWhere('name', 'Shipping');

        $this->actingAs($this->initiative->owner)
            ->post(route('initiatives.rating-items.store', [$this->initiative, $group]), ['label' => 'CI reliability']);

        $this->assertSame(['CI reliability'], $group->items()->pluck('label')->all());
    }
}

<?php

namespace Tests\Feature;

use App\Models\GuideEntry;
use App\Models\Initiative;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_pages_share_the_guide_with_step_metadata(): void
    {
        $initiative = Initiative::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('initiatives.friction', $initiative))
            ->assertInertia(fn (Assert $page) => $page
                ->where('guide.key', 'friction')
                ->where('guide.title', 'Log friction')
                ->where('guide.initiativeId', $initiative->id)
                ->where('guide.canEdit', false)
                ->where('guide.markdown', null)
                ->where('guide.canEditNote', false)
                ->where('guide.activities.1.key', 'friction')
                ->where('guide.html', fn (string $html) => str_contains($html, '<strong>')));
    }

    public function test_pages_without_a_guide_share_null(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('guide', null));
    }

    public function test_admin_edit_overrides_the_default_and_strips_raw_html(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.guide.update', 'start'), ['body' => 'Our **own** words <script>alert(1)</script>'])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->get(route('initiatives.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('guide.customized', true)
                ->where('guide.html', fn (string $html) => str_contains($html, '<strong>own</strong>') && ! str_contains($html, '<script>')));
    }

    public function test_reset_restores_the_default_text(): void
    {
        $admin = User::factory()->admin()->create();
        GuideEntry::create(['key' => 'start', 'body' => 'Custom']);

        $this->actingAs($admin)->delete(route('admin.guide.destroy', 'start'));

        $this->assertDatabaseMissing('guide_entries', ['key' => 'start']);
    }

    public function test_non_admins_cannot_edit_the_guide(): void
    {
        $this->actingAs(User::factory()->facilitator()->create())
            ->put(route('admin.guide.update', 'start'), ['body' => 'Hijacked'])
            ->assertForbidden();

        $this->assertDatabaseMissing('guide_entries', ['key' => 'start']);
    }

    public function test_unknown_guide_key_returns_not_found(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.guide.update', 'not-a-step'), ['body' => 'x'])
            ->assertNotFound();
    }

    public function test_owner_can_save_activity_notes_and_viewer_cannot(): void
    {
        $initiative = Initiative::factory()->create();

        $this->actingAs($initiative->owner)
            ->put(route('initiatives.notes.update', [$initiative, 'workflow']), ['body' => 'Decided to merge Building and Deployment'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('activity_notes', [
            'initiative_id' => $initiative->id,
            'activity_key' => 'workflow',
            'body' => 'Decided to merge Building and Deployment',
        ]);

        $this->actingAs(User::factory()->create())
            ->put(route('initiatives.notes.update', [$initiative, 'workflow']), ['body' => 'Nope'])
            ->assertForbidden();
    }

    public function test_notes_are_scoped_to_their_initiative(): void
    {
        $initiative = Initiative::factory()->create();
        $other = Initiative::factory()->create();
        $initiative->activityNotes()->create(['activity_key' => 'friction', 'body' => 'Only here']);

        $this->actingAs($other->owner)
            ->get(route('initiatives.friction', $other))
            ->assertInertia(fn (Assert $page) => $page->where('guide.note', null));
    }
}

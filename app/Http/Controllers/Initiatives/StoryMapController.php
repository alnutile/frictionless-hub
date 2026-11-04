<?php

namespace App\Http\Controllers\Initiatives;

use App\Enums\StoryMapKind;
use App\Http\Controllers\Controller;
use App\Http\Controllers\InitiativeController;
use App\Models\Initiative;
use App\Models\StoryMapItem;
use App\Support\Reorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StoryMapController extends Controller
{
    public function index(Request $request, Initiative $initiative): Response
    {
        $this->authorize('view', $initiative);

        $activities = $initiative->storyMapItems()
            ->where('level', StoryMapItem::LEVEL_ACTIVITY)
            ->orderBy('position')
            ->with('children.children')
            ->get();

        $card = fn (StoryMapItem $item) => [
            'id' => $item->id,
            'title' => $item->title,
            'kind' => $item->kind?->value,
        ];

        return Inertia::render('initiatives/story-map', [
            'initiative' => InitiativeController::summary($initiative),
            'canManage' => $request->user()->can('update', $initiative),
            'kinds' => StoryMapKind::options(),
            'activities' => $activities->map(fn (StoryMapItem $activity) => [
                'id' => $activity->id,
                'title' => $activity->title,
                'tasks' => $activity->children->map(fn (StoryMapItem $task) => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'cards' => $task->children->map($card),
                ]),
            ]),
        ]);
    }

    /**
     * The level is derived from the parent: none → activity, activity → task, task → card.
     */
    public function store(Request $request, Initiative $initiative): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('story_map_items', 'id')->where('initiative_id', $initiative->id),
            ],
            'kind' => ['nullable', Rule::enum(StoryMapKind::class)],
        ]);

        $parent = isset($validated['parent_id']) ? StoryMapItem::find($validated['parent_id']) : null;
        $level = $parent ? (StoryMapItem::CHILD_LEVEL[$parent->level] ?? null) : StoryMapItem::LEVEL_ACTIVITY;

        if ($level === null) {
            throw ValidationException::withMessages(['parent_id' => 'Cards cannot have children.']);
        }

        $initiative->storyMapItems()->create([
            'parent_id' => $parent?->id,
            'level' => $level,
            'kind' => $level === StoryMapItem::LEVEL_CARD ? ($validated['kind'] ?? StoryMapKind::Story->value) : null,
            'title' => $validated['title'],
            'position' => (int) $initiative->storyMapItems()->where('parent_id', $parent?->id)->max('position') + 1,
        ]);

        return back();
    }

    public function update(Request $request, Initiative $initiative, StoryMapItem $storyMapItem): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'kind' => ['nullable', Rule::enum(StoryMapKind::class)],
        ]);

        $storyMapItem->title = $validated['title'];

        if ($storyMapItem->level === StoryMapItem::LEVEL_CARD && isset($validated['kind'])) {
            $storyMapItem->kind = $validated['kind'];
        }

        $storyMapItem->save();

        return back();
    }

    public function destroy(Initiative $initiative, StoryMapItem $storyMapItem): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $storyMapItem->delete();

        return back();
    }

    public function move(Request $request, Initiative $initiative, StoryMapItem $storyMapItem): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];

        $siblingIds = $initiative->storyMapItems()
            ->where('parent_id', $storyMapItem->parent_id)
            ->where('level', $storyMapItem->level)
            ->orderBy('position')
            ->pluck('id')
            ->all();

        Reorder::persist(StoryMapItem::class, Reorder::swap($siblingIds, $storyMapItem->id, $direction));

        return back();
    }
}

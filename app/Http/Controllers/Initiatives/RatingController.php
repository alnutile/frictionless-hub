<?php

namespace App\Http\Controllers\Initiatives;

use App\Http\Controllers\Controller;
use App\Http\Controllers\InitiativeController;
use App\Models\Initiative;
use App\Models\Rating;
use App\Models\RatingGroup;
use App\Models\RatingItem;
use App\Services\RatingSummary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RatingController extends Controller
{
    public function index(Request $request, Initiative $initiative): Response
    {
        $this->authorize('view', $initiative);

        $userId = $request->user()->id;
        $groups = $initiative->ratingGroups()->with('items.ratings')->get();

        return Inertia::render('initiatives/ratings', [
            'initiative' => InitiativeController::summary($initiative),
            'canManage' => $request->user()->can('update', $initiative),
            'raters' => Rating::whereIn('rating_item_id', $groups->flatMap->items->pluck('id'))->distinct()->count('user_id'),
            'groups' => $groups->map(fn (RatingGroup $group) => [
                'id' => $group->id,
                'name' => $group->name,
                'items' => $group->items->map(fn (RatingItem $item) => [
                    'id' => $item->id,
                    'label' => $item->label,
                    'mine' => $item->ratings->firstWhere('user_id', $userId)?->score,
                    ...RatingSummary::of($item->ratings->pluck('score')->all()),
                ]),
            ]),
        ]);
    }

    public function storeGroup(Request $request, Initiative $initiative): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $initiative->ratingGroups()->create([
            ...$validated,
            'position' => (int) $initiative->ratingGroups()->max('position') + 1,
        ]);

        return back();
    }

    public function updateGroup(Request $request, Initiative $initiative, RatingGroup $ratingGroup): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $ratingGroup->update($request->validate(['name' => ['required', 'string', 'max:255']]));

        return back();
    }

    public function destroyGroup(Initiative $initiative, RatingGroup $ratingGroup): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $ratingGroup->delete();

        return back();
    }

    public function storeItem(Request $request, Initiative $initiative, RatingGroup $ratingGroup): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $validated = $request->validate(['label' => ['required', 'string', 'max:255']]);

        $ratingGroup->items()->create([
            ...$validated,
            'position' => (int) $ratingGroup->items()->max('position') + 1,
        ]);

        return back();
    }

    public function destroyItem(Initiative $initiative, RatingItem $ratingItem): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $ratingItem->delete();

        return back();
    }

    /**
     * Any signed-in user records their own score; a null score clears it.
     */
    public function rate(Request $request, Initiative $initiative, RatingItem $ratingItem): RedirectResponse
    {
        $this->authorize('view', $initiative);

        $score = $request->validate(['score' => ['nullable', 'integer', 'between:1,5']])['score'] ?? null;

        if ($score === null) {
            $ratingItem->ratings()->where('user_id', $request->user()->id)->delete();
        } else {
            $ratingItem->ratings()->updateOrCreate(['user_id' => $request->user()->id], ['score' => $score]);
        }

        return back();
    }
}

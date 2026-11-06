<?php

namespace App\Http\Controllers\Initiatives;

use App\Http\Controllers\Controller;
use App\Models\ChecklistItem;
use App\Models\Initiative;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ChecklistItemController extends Controller
{
    public function store(Request $request, Initiative $initiative): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $validated = $request->validate(['label' => ['required', 'string', 'max:255']]);

        $initiative->checklistItems()->create([
            ...$validated,
            'position' => (int) $initiative->checklistItems()->max('position') + 1,
        ]);

        return back();
    }

    public function destroy(Initiative $initiative, ChecklistItem $checklistItem): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $checklistItem->delete();

        return back();
    }
}

<?php

namespace App\Http\Controllers\Initiatives;

use App\Http\Controllers\Controller;
use App\Http\Controllers\InitiativeController;
use App\Models\Initiative;
use App\Services\Guide;
use App\Support\Walkthrough;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A preview page for a walkthrough step that isn't built yet, so the ladder reads
 * as a roadmap: what the step will do and where it sits in the sequence.
 */
class PlannedActivityController extends Controller
{
    public function show(Initiative $initiative, string $key): Response|RedirectResponse
    {
        $this->authorize('view', $initiative);

        $activities = Walkthrough::forFrontend();
        $index = array_search($key, array_column($activities, 'key'), true);
        abort_if($index === false, 404);

        $activity = $activities[$index];

        if ($activity['route'] !== null) {
            return to_route($activity['route'], $initiative);
        }

        return Inertia::render('initiatives/planned', [
            'initiative' => InitiativeController::summary($initiative),
            'activity' => $activity,
            'html' => Guide::render(Guide::body($key)),
            'previous' => $activities[$index - 1] ?? null,
            'next' => $activities[$index + 1] ?? null,
            'builtBefore' => array_values(array_filter(
                array_slice($activities, 0, $index),
                fn (array $a) => $a['route'] !== null,
            )),
        ]);
    }
}

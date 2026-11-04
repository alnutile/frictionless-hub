<?php

namespace App\Http\Controllers\Initiatives;

use App\Http\Controllers\Controller;
use App\Http\Controllers\InitiativeController;
use App\Http\Requests\Initiatives\WorkflowStageRequest;
use App\Models\Initiative;
use App\Models\StageTool;
use App\Models\WorkflowStage;
use App\Support\Reorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowStageController extends Controller
{
    public function index(Request $request, Initiative $initiative): Response
    {
        $this->authorize('view', $initiative);

        $stages = $initiative->stages()
            ->with('tools')
            ->withCount('frictionPoints')
            ->withAvg('frictionPoints', 'pain')
            ->get()
            ->map(fn (WorkflowStage $stage) => [
                'id' => $stage->id,
                'name' => $stage->name,
                'notes' => $stage->notes,
                'ai_assisted' => $stage->ai_assisted,
                'friction_points_count' => $stage->friction_points_count,
                'avg_pain' => $stage->friction_points_avg_pain !== null
                    ? round((float) $stage->friction_points_avg_pain, 1)
                    : null,
                'tools' => $stage->tools->map(fn (StageTool $tool) => [
                    'id' => $tool->id,
                    'name' => $tool->name,
                    'is_ai' => $tool->is_ai,
                    'notes' => $tool->notes,
                ]),
            ]);

        return Inertia::render('initiatives/workflow', [
            'initiative' => InitiativeController::summary($initiative),
            'canManage' => $request->user()->can('update', $initiative),
            'stages' => $stages,
        ]);
    }

    public function store(WorkflowStageRequest $request, Initiative $initiative): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $initiative->stages()->create([
            ...$request->validated(),
            'position' => (int) $initiative->stages()->max('position') + 1,
        ]);

        return back()->with('success', 'Stage added.');
    }

    public function update(WorkflowStageRequest $request, Initiative $initiative, WorkflowStage $stage): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $stage->update($request->validated());

        return back()->with('success', 'Stage updated.');
    }

    public function destroy(Initiative $initiative, WorkflowStage $stage): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $stage->delete();

        return back()->with('success', 'Stage removed.');
    }

    /**
     * Swap a stage with its neighbour.
     */
    public function move(Request $request, Initiative $initiative, WorkflowStage $stage): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];

        $ids = $initiative->stages()->pluck('id')->all();
        Reorder::persist(WorkflowStage::class, Reorder::swap($ids, $stage->id, $direction));

        return back();
    }
}

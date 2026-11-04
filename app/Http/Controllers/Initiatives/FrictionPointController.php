<?php

namespace App\Http\Controllers\Initiatives;

use App\Enums\Frequency;
use App\Http\Controllers\Controller;
use App\Http\Controllers\InitiativeController;
use App\Http\Requests\Initiatives\FrictionPointRequest;
use App\Models\Dimension;
use App\Models\FrictionPoint;
use App\Models\Initiative;
use App\Models\WorkflowStage;
use App\Services\FrictionHeatmap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class FrictionPointController extends Controller
{
    public function index(Request $request, Initiative $initiative): Response
    {
        $this->authorize('view', $initiative);

        $stages = $initiative->stages()->get(['id', 'name']);
        $dimensions = $initiative->dimensions()->get(['id', 'name', 'description']);
        $points = $initiative->frictionPoints()
            ->with(['stage:id,name', 'dimensions:id'])
            ->orderByDesc('pain')
            ->latest()
            ->get();

        $heatmap = FrictionHeatmap::build(
            $stages->map(fn (WorkflowStage $s) => ['id' => $s->id, 'name' => $s->name])->all(),
            $dimensions->map(fn (Dimension $d) => ['id' => $d->id, 'name' => $d->name])->all(),
            $points->map(fn (FrictionPoint $p) => [
                'stage_id' => $p->workflow_stage_id,
                'pain' => $p->pain,
                'dimension_ids' => $p->dimensions->pluck('id')->all(),
            ])->all(),
        );

        return Inertia::render('initiatives/friction', [
            'initiative' => InitiativeController::summary($initiative),
            'canManage' => $request->user()->can('update', $initiative),
            'stages' => $stages,
            'dimensions' => $dimensions,
            'frequencies' => Frequency::options(),
            'heatmap' => $heatmap,
            'frictionPoints' => $points->map(fn (FrictionPoint $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'description' => $p->description,
                'workflow_stage_id' => $p->workflow_stage_id,
                'stage' => $p->stage?->name,
                'frequency' => $p->frequency?->value,
                'frequency_label' => $p->frequency?->label(),
                'pain' => $p->pain,
                'affected' => $p->affected,
                'dimension_ids' => $p->dimensions->pluck('id'),
            ]),
        ]);
    }

    public function store(FrictionPointRequest $request, Initiative $initiative): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $point = $initiative->frictionPoints()->make(Arr::except($request->validated(), 'dimension_ids'));
        $point->created_by = $request->user()->id;
        $point->save();
        $point->dimensions()->sync($request->validated('dimension_ids', []));

        return back()->with('success', 'Friction point logged.');
    }

    public function update(FrictionPointRequest $request, Initiative $initiative, FrictionPoint $frictionPoint): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $frictionPoint->update(Arr::except($request->validated(), 'dimension_ids'));
        $frictionPoint->dimensions()->sync($request->validated('dimension_ids', []));

        return back()->with('success', 'Friction point updated.');
    }

    public function destroy(Initiative $initiative, FrictionPoint $frictionPoint): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $frictionPoint->delete();

        return back()->with('success', 'Friction point removed.');
    }
}

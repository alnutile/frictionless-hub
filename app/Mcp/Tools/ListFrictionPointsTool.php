<?php

namespace App\Mcp\Tools;

use App\Models\Dimension;
use App\Models\FrictionPoint;
use App\Models\WorkflowStage;
use App\Services\FrictionHeatmap;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_friction_points')]
#[Description('Friction points (step 2) with stage, frequency, pain and dimensions, plus the heatmap row totals (including a "No stage" row).')]
#[IsReadOnly]
class ListFrictionPointsTool extends InitiativeTool
{
    public function handle(Request $request): Response
    {
        $initiative = $this->initiative($request);
        $points = $initiative->frictionPoints()->with(['stage:id,name', 'dimensions:id,name'])->orderByDesc('pain')->get();
        $heatmap = FrictionHeatmap::build(
            $initiative->stages()->get()->map(fn (WorkflowStage $s) => ['id' => $s->id, 'name' => $s->name])->all(),
            $initiative->dimensions()->get()->map(fn (Dimension $d) => ['id' => $d->id, 'name' => $d->name])->all(),
            $points->map(fn (FrictionPoint $p) => ['stage_id' => $p->workflow_stage_id, 'pain' => $p->pain, 'dimension_ids' => $p->dimensions->pluck('id')->all()])->all(),
        );

        return Response::json([
            'total' => $heatmap['total'],
            'no_stage' => $heatmap['unassigned'],
            'rows' => array_map(fn (array $r) => ['stage' => $r['stage'], 'total' => $r['total']], $heatmap['rows']),
            'friction_points' => $points->map(fn (FrictionPoint $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'stage' => $p->stage?->name,
                'frequency' => $p->frequency?->value,
                'pain' => $p->pain,
                'affected' => $p->affected,
                'dimensions' => $p->dimensions->pluck('name'),
            ]),
        ]);
    }
}

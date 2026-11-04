<?php

namespace App\Http\Controllers\Initiatives;

use App\Http\Controllers\Controller;
use App\Http\Requests\Initiatives\StageToolRequest;
use App\Models\Initiative;
use App\Models\StageTool;
use App\Models\WorkflowStage;
use Illuminate\Http\RedirectResponse;

class StageToolController extends Controller
{
    public function store(StageToolRequest $request, Initiative $initiative, WorkflowStage $stage): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $stage->tools()->create($request->validated());

        return back();
    }

    public function destroy(Initiative $initiative, WorkflowStage $stage, StageTool $tool): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $tool->delete();

        return back();
    }
}

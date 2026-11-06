<?php

namespace App\Mcp\Tools;

use App\Services\InitiativeProgress;
use App\Support\Walkthrough;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('initiative_overview')]
#[Description('One initiative: details, the 18-step walkthrough (built or planned) and the count/label shown for each built step.')]
#[IsReadOnly]
class InitiativeOverviewTool extends InitiativeTool
{
    public function handle(Request $request): Response
    {
        $initiative = $this->initiative($request);
        $progress = InitiativeProgress::for($initiative);

        return Response::json([
            'id' => $initiative->id,
            'name' => $initiative->name,
            'scope' => $initiative->scope,
            'description' => $initiative->description,
            'phase' => $initiative->phase->value,
            'owner' => $initiative->owner->name,
            'interview_target' => $initiative->interview_target,
            'steps' => array_map(fn (array $a) => [
                'step' => $a['step'],
                'key' => $a['key'],
                'title' => $a['title'],
                'phase' => $a['phase_label'],
                'built' => $a['route'] !== null,
                'progress' => $progress[$a['key']] ?? null,
            ], Walkthrough::forFrontend()),
        ]);
    }
}

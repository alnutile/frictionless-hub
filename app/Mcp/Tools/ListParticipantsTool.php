<?php

namespace App\Mcp\Tools;

use App\Models\Participant;
use App\Models\SegmentValue;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_participants')]
#[Description('Listening-tour participants (step 5) with status, work area, group values, checklist progress and whether they have interview notes.')]
#[IsReadOnly]
class ListParticipantsTool extends InitiativeTool
{
    public function handle(Request $request): Response
    {
        $initiative = $this->initiative($request);
        $checklistSize = $initiative->checklistItems()->count();

        return Response::json([
            'target' => $initiative->interview_target,
            'participants' => $initiative->participants()->with(['segmentValues.category', 'completedChecklistItems', 'interview'])->get()
                ->map(fn (Participant $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'role' => $p->role,
                    'status' => $p->status->value,
                    'scheduled_at' => $p->scheduled_at?->toIso8601String(),
                    'area' => $this->area($p),
                    'groups' => $p->segmentValues->reject(fn (SegmentValue $v) => $v->category->is_area)
                        ->mapWithKeys(fn (SegmentValue $v) => [$v->category->name => $v->name]),
                    'checklist' => $p->completedChecklistItems->count().'/'.$checklistSize,
                    'interview_id' => $p->interview?->id,
                ]),
        ]);
    }
}

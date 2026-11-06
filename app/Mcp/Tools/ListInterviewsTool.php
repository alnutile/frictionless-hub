<?php

namespace App\Mcp\Tools;

use App\Enums\NoteKind;
use App\Models\Interview;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_interviews')]
#[Description('Interviews (step 6): participant, status, date, note counts by kind, open follow-ups and codes used. Use get_interview for the notes themselves.')]
#[IsReadOnly]
class ListInterviewsTool extends InitiativeTool
{
    public function handle(Request $request): Response
    {
        $initiative = $this->initiative($request);

        return Response::json($initiative->interviews()->with(['participant.segmentValues.category', 'notes.codes'])->get()->map(fn (Interview $i) => [
            'id' => $i->id,
            'participant' => $i->participant->name,
            'area' => $this->area($i->participant),
            'status' => $i->status->value,
            'conducted_at' => $i->conducted_at?->toIso8601String(),
            'notes' => collect(NoteKind::cases())->mapWithKeys(fn (NoteKind $k) => [$k->value => $i->notes->where('kind', $k)->count()]),
            'open_follow_ups' => $i->notes->where('kind', NoteKind::FollowUp)->where('done', false)->count(),
            'codes' => $i->notes->flatMap->codes->pluck('name')->unique()->sort()->values(),
        ]));
    }
}

<?php

namespace App\Mcp\Tools;

use App\Models\Interview;
use App\Models\InterviewNote;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_interview')]
#[Description('One interview with every note: kind, text, stage, codes, follow-up owner/done, and the linked friction point if any.')]
#[IsReadOnly]
class GetInterviewTool extends InitiativeTool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(['interview_id' => ['required', 'integer', 'exists:interviews,id']]);
        $interview = Interview::with(['participant.segmentValues.category', 'notes.codes', 'notes.stage', 'notes.frictionPoint', 'interviewer'])
            ->findOrFail($validated['interview_id']);

        return Response::json([
            'id' => $interview->id,
            'initiative_id' => $interview->initiative_id,
            'participant' => $interview->participant->name,
            'role' => $interview->participant->role,
            'area' => $this->area($interview->participant),
            'status' => $interview->status->value,
            'conducted_at' => $interview->conducted_at?->toIso8601String(),
            'interviewer' => $interview->interviewer?->name,
            'responsibilities' => $interview->responsibilities,
            'tools' => $interview->tools,
            'has_transcript' => filled($interview->transcript),
            'notes' => $interview->notes->map(fn (InterviewNote $n) => [
                'id' => $n->id,
                'kind' => $n->kind->value,
                'body' => $n->body,
                'stage' => $n->stage?->name,
                'codes' => $n->codes->pluck('name'),
                'owner' => $n->owner,
                'done' => $n->done,
                'friction_point' => $n->frictionPoint?->title,
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'interview_id' => $schema->integer()->description('The interview id (from list_interviews).')->required(),
        ];
    }
}

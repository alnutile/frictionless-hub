<?php

namespace App\Mcp\Tools;

use App\Models\Code;
use App\Models\InterviewNote;
use App\Services\CodeFrequency;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_codes')]
#[Description('Interview codes (step 6) with how many interviews and notes use each, and which theme (step 7) they belong to.')]
#[IsReadOnly]
class ListCodesTool extends InitiativeTool
{
    public function handle(Request $request): Response
    {
        $initiative = $this->initiative($request);
        $codes = $initiative->codes()->with('theme:id,name')->get();
        $notes = $initiative->interviewNotes()->with('codes')->get();
        $themes = $codes->mapWithKeys(fn (Code $c) => [$c->id => $c->theme?->name]);

        return Response::json(array_map(fn (array $row) => [...$row, 'theme' => $themes[$row['id']]], CodeFrequency::build(
            $codes->map(fn (Code $c) => ['id' => $c->id, 'name' => $c->name])->all(),
            $notes->map(fn (InterviewNote $n) => ['interview_id' => $n->interview_id, 'code_ids' => $n->codes->pluck('id')->all()])->all(),
        )));
    }
}

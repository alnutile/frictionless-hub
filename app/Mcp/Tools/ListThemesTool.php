<?php

namespace App\Mcp\Tools;

use App\Enums\NoteKind;
use App\Models\Interview;
use App\Models\InterviewNote;
use App\Models\Theme;
use App\Services\ThemeWeights;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_themes')]
#[Description('Themes (step 7) with weight (interviews and notes under their codes, by work area), codes, impact/effort, friction-log link, plus unthemed codes and the count of uncoded notes.')]
#[IsReadOnly]
class ListThemesTool extends InitiativeTool
{
    public function handle(Request $request): Response
    {
        $initiative = $this->initiative($request);
        $themes = $initiative->themes()->with(['codes', 'frictionPoint:id,title'])->get();
        $notes = $initiative->interviews()->with(['notes.codes', 'participant.segmentValues.category'])->get()
            ->flatMap(fn (Interview $i) => $i->notes->map(fn (InterviewNote $n) => [
                'id' => $n->id,
                'interview_id' => $i->id,
                'area' => $this->area($i->participant),
                'kind' => $n->kind,
                'code_ids' => $n->codes->pluck('id')->all(),
            ]));
        $weights = ThemeWeights::build(
            $themes->map(fn (Theme $t) => ['id' => $t->id, 'code_ids' => $t->codes->pluck('id')->all()])->all(),
            $notes->map(fn (array $n) => collect($n)->except('kind')->all())->values()->all(),
        );

        return Response::json([
            'themes' => $themes->map(fn (Theme $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'summary' => $t->summary,
                'impact' => $t->impact?->value,
                'effort' => $t->effort?->value,
                'codes' => $t->codes->pluck('name'),
                'weight' => $weights[$t->id],
                'friction_point' => $t->frictionPoint?->title,
            ]),
            'unthemed_codes' => $initiative->codes()->whereNull('theme_id')->pluck('name'),
            'uncoded_notes' => $notes->filter(fn (array $n) => $n['code_ids'] === [] && $n['kind'] !== NoteKind::FollowUp)->count(),
        ]);
    }
}

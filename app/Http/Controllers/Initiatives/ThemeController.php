<?php

namespace App\Http\Controllers\Initiatives;

use App\Enums\Level;
use App\Enums\NoteKind;
use App\Http\Controllers\Controller;
use App\Http\Controllers\InitiativeController;
use App\Http\Requests\Initiatives\ThemeRequest;
use App\Models\Code;
use App\Models\Initiative;
use App\Models\InterviewNote;
use App\Models\SegmentValue;
use App\Models\Theme;
use App\Services\CodeFrequency;
use App\Services\ThemeWeights;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ThemeController extends Controller
{
    public function index(Request $request, Initiative $initiative): Response
    {
        $this->authorize('view', $initiative);

        $canManage = $request->user()->can('update', $initiative);
        $themes = $initiative->themes()->with(['codes', 'frictionPoint:id,title'])->get();
        $codes = $initiative->codes()->get();
        $notes = $this->notesWithArea($initiative);
        $weights = $this->weights($themes, $notes);
        $frequency = CodeFrequency::build(
            $codes->map(fn (Code $c) => ['id' => $c->id, 'name' => $c->name])->all(),
            $notes->map(fn (array $n) => Arr::only($n, ['interview_id', 'code_ids']))->all(),
        );
        $uncoded = $notes->filter(fn (array $n) => $n['code_ids'] === [] && $n['kind'] !== NoteKind::FollowUp->value)->values();

        return Inertia::render('initiatives/themes', [
            'initiative' => InitiativeController::summary($initiative),
            'canManage' => $canManage,
            'themes' => $themes
                ->map(fn (Theme $t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'summary' => $t->summary,
                    'impact' => $t->impact?->value,
                    'effort' => $t->effort?->value,
                    'solutions' => $t->solutions,
                    'codes' => $t->codes->map(fn (Code $c) => ['id' => $c->id, 'name' => $c->name])->values(),
                    'weight' => $weights[$t->id],
                    'friction_point' => $t->frictionPoint ? ['id' => $t->frictionPoint->id, 'title' => $t->frictionPoint->title] : null,
                ])
                ->sortByDesc(fn (array $t) => [$t['weight']['interviews'], $t['weight']['notes']])
                ->values(),
            'unthemedCodes' => collect($frequency)->filter(fn (array $c) => $codes->firstWhere('id', $c['id'])->theme_id === null)->values(),
            'codeCount' => $codes->count(),
            'uncodedCount' => $uncoded->count(),
            // Note text and who said it identify people: only for those who run the initiative.
            'uncodedNotes' => $canManage ? $uncoded->take(20)->map(fn (array $n) => Arr::only($n, ['id', 'kind', 'body', 'interview_id', 'participant']))->values() : null,
            'allCodes' => $codes->map(fn (Code $c) => ['id' => $c->id, 'name' => $c->name, 'theme_id' => $c->theme_id]),
            'frictionPoints' => $canManage ? $initiative->frictionPoints()->orderBy('title')->get(['id', 'title']) : [],
            'levels' => Level::options(),
        ]);
    }

    /**
     * The evidence behind a theme: every note carrying one of its codes, grouped by interview.
     */
    public function show(Request $request, Initiative $initiative, Theme $theme): Response
    {
        $this->authorize('update', $initiative);

        $codeIds = $theme->codes()->pluck('id')->all();
        $notes = $this->notesWithArea($initiative)->filter(fn (array $n) => array_intersect($codeIds, $n['code_ids']) !== []);
        $codeNames = $initiative->codes()->pluck('name', 'id');

        return Inertia::render('initiatives/theme', [
            'initiative' => InitiativeController::summary($initiative),
            'theme' => [
                'id' => $theme->id,
                'name' => $theme->name,
                'summary' => $theme->summary,
                'codes' => $theme->codes()->pluck('name'),
                'weight' => $this->weights(collect([$theme->load('codes')]), $notes)[$theme->id],
            ],
            'interviews' => $notes->groupBy('interview_id')->map(fn (Collection $group) => [
                'interview_id' => $group->first()['interview_id'],
                'participant' => $group->first()['participant'],
                'area' => $group->first()['area'],
                'notes' => $group->map(fn (array $n) => [
                    'id' => $n['id'],
                    'kind' => $n['kind'],
                    'body' => $n['body'],
                    'codes' => collect($n['code_ids'])->map(fn (int $id) => $codeNames[$id])->sort()->values(),
                ])->values(),
            ])->values(),
            'kinds' => NoteKind::options(),
        ]);
    }

    public function store(ThemeRequest $request, Initiative $initiative): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $theme = $initiative->themes()->create([
            ...Arr::except($request->validated(), 'code_ids'),
            'position' => (int) $initiative->themes()->max('position') + 1,
        ]);
        $this->assignCodes($initiative, $theme, $request->validated('code_ids', []));

        return back()->with('success', 'Theme created.');
    }

    public function update(ThemeRequest $request, Initiative $initiative, Theme $theme): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $theme->update(Arr::except($request->validated(), 'code_ids'));

        if ($request->has('code_ids')) {
            $this->assignCodes($initiative, $theme, $request->validated('code_ids', []));
        }

        return back()->with('success', 'Theme saved.');
    }

    public function destroy(Initiative $initiative, Theme $theme): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $theme->delete();

        return back()->with('success', 'Theme deleted. Its codes are back in the unthemed list.');
    }

    /**
     * Create a friction point from the theme (step 2), using the stage most of its pain points share.
     */
    public function promote(Request $request, Initiative $initiative, Theme $theme): RedirectResponse
    {
        $this->authorize('update', $initiative);

        if ($theme->friction_point_id !== null) {
            return back()->with('success', 'This theme is already linked to the friction log.');
        }

        $codeIds = $theme->codes()->pluck('id')->all();
        $notes = $this->notesWithArea($initiative)->filter(fn (array $n) => array_intersect($codeIds, $n['code_ids']) !== []);
        $weight = $this->weights(collect([$theme->load('codes')]), $notes)[$theme->id];
        $stageId = $notes->where('kind', NoteKind::PainPoint->value)->pluck('stage_id')->filter()->countBy()->sortDesc()->keys()->first();

        $point = $initiative->frictionPoints()->make([
            'title' => $theme->name,
            'description' => trim(($theme->summary ?? '')."\n\n(Theme from {$weight['interviews']} interviews.)"),
            'workflow_stage_id' => $stageId,
            'affected' => collect($weight['areas'])->pluck('area')->implode(', ') ?: null,
        ]);
        $point->created_by = $request->user()->id;
        $point->save();

        $theme->forceFill(['friction_point_id' => $point->id])->save();

        return back()->with('success', 'Added to the friction log. Set its frequency and pain there.');
    }

    /**
     * Link the theme to a friction point that already exists (or unlink with null).
     */
    public function link(Request $request, Initiative $initiative, Theme $theme): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $validated = $request->validate([
            'friction_point_id' => ['nullable', 'integer', Rule::exists('friction_points', 'id')->where('initiative_id', $initiative->id)],
        ]);

        $theme->forceFill(['friction_point_id' => $validated['friction_point_id'] ?? null])->save();

        return back()->with('success', $theme->friction_point_id ? 'Linked to the friction log.' : 'Unlinked from the friction log.');
    }

    /**
     * Put a single code into a theme (or take it out with null).
     */
    public function assignCode(Request $request, Initiative $initiative, Code $code): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $validated = $request->validate([
            'theme_id' => ['nullable', 'integer', Rule::exists('themes', 'id')->where('initiative_id', $initiative->id)],
        ]);

        $code->forceFill(['theme_id' => $validated['theme_id'] ?? null])->save();

        return back();
    }

    /**
     * @param  array<int, int>  $codeIds
     */
    private function assignCodes(Initiative $initiative, Theme $theme, array $codeIds): void
    {
        $initiative->codes()->where('theme_id', $theme->id)->whereNotIn('id', $codeIds)->update(['theme_id' => null]);
        $initiative->codes()->whereIn('id', $codeIds)->update(['theme_id' => $theme->id]);
    }

    /**
     * Every interview note in the initiative as plain data, with the participant's work area.
     *
     * @return Collection<int, array{id: int, interview_id: int, participant: string, area: string|null, kind: string, body: string, stage_id: int|null, code_ids: array<int, int>}>
     */
    private function notesWithArea(Initiative $initiative): Collection
    {
        $interviews = $initiative->interviews()->with(['notes.codes', 'participant.segmentValues.category'])->get();

        return $interviews->flatMap(function ($interview) {
            $area = $interview->participant->segmentValues->first(fn (SegmentValue $v) => $v->category->is_area)?->name;

            return $interview->notes->map(fn (InterviewNote $n) => [
                'id' => $n->id,
                'interview_id' => $interview->id,
                'participant' => $interview->participant->name,
                'area' => $area,
                'kind' => $n->kind->value,
                'body' => $n->body,
                'stage_id' => $n->workflow_stage_id,
                'code_ids' => $n->codes->pluck('id')->all(),
            ]);
        })->values();
    }

    /**
     * @param  Collection<int, Theme>  $themes
     * @param  Collection<int, array<string, mixed>>  $notes
     * @return array<int, array{notes: int, interviews: int, areas: array<int, array{area: string, interviews: int}>}>
     */
    private function weights(Collection $themes, Collection $notes): array
    {
        return ThemeWeights::build(
            $themes->map(fn (Theme $t) => ['id' => $t->id, 'code_ids' => $t->codes->pluck('id')->all()])->all(),
            $notes->map(fn (array $n) => Arr::only($n, ['id', 'interview_id', 'area', 'code_ids']))->values()->all(),
        );
    }
}

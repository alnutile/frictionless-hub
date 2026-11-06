<?php

namespace App\Http\Controllers\Initiatives;

use App\Enums\InterviewStatus;
use App\Enums\NoteKind;
use App\Enums\ParticipantStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\InitiativeController;
use App\Models\Code;
use App\Models\Initiative;
use App\Models\Interview;
use App\Models\InterviewNote;
use App\Models\Participant;
use App\Models\SegmentValue;
use App\Models\WorkflowStage;
use App\Services\CodeFrequency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InterviewController extends Controller
{
    public function index(Request $request, Initiative $initiative): Response
    {
        $this->authorize('view', $initiative);

        $canManage = $request->user()->can('update', $initiative);
        $interviews = $initiative->interviews()->with(['participant.segmentValues.category', 'notes.codes'])->get();
        $notes = $interviews->flatMap->notes;

        return Inertia::render('initiatives/interviews', [
            'initiative' => InitiativeController::summary($initiative),
            'canManage' => $canManage,
            'codeFrequency' => $this->codeFrequency($initiative, $notes),
            'stats' => [
                'complete' => $interviews->where('status', InterviewStatus::Complete)->count(),
                'draft' => $interviews->where('status', InterviewStatus::Draft)->count(),
                'by_kind' => collect(NoteKind::cases())->mapWithKeys(fn (NoteKind $k) => [$k->value => $notes->where('kind', $k)->count()]),
                'open_follow_ups' => $notes->where('kind', NoteKind::FollowUp)->where('done', false)->count(),
                'uncoded' => $notes->filter(fn (InterviewNote $n) => $n->kind !== NoteKind::FollowUp && $n->codes->isEmpty())->count(),
            ],
            'kinds' => NoteKind::options(),
            // Who said what identifies people, so only those who run the initiative see it.
            'interviews' => $canManage ? $interviews->sortByDesc('conducted_at')->values()->map(fn (Interview $i) => [
                'id' => $i->id,
                'participant' => $i->participant->name,
                'role' => $i->participant->role,
                'area' => $this->area($i->participant),
                'conducted_at' => $i->conducted_at?->toDateString(),
                'status' => $i->status->value,
                'counts' => collect(NoteKind::cases())->mapWithKeys(fn (NoteKind $k) => [$k->value => $i->notes->where('kind', $k)->count()]),
                'codes' => $i->notes->flatMap->codes->pluck('name')->unique()->sort()->values(),
            ]) : null,
            'toInterview' => $canManage ? $initiative->participants()
                ->whereDoesntHave('interview')
                ->where('status', '!=', ParticipantStatus::Declined)
                ->get()
                ->map(fn (Participant $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'status' => $p->status->label(),
                    'scheduled_at' => $p->scheduled_at?->format('Y-m-d H:i'),
                ]) : null,
            'openFollowUps' => $canManage ? $notes->where('kind', NoteKind::FollowUp)->where('done', false)->values()
                ->map(fn (InterviewNote $n) => [
                    'id' => $n->id,
                    'body' => $n->body,
                    'owner' => $n->owner,
                    'interview_id' => $n->interview_id,
                    'participant' => $interviews->firstWhere('id', $n->interview_id)?->participant->name,
                ]) : null,
        ]);
    }

    /**
     * Start (or reopen) the notes for a participant on the tour.
     */
    public function store(Request $request, Initiative $initiative): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $validated = $request->validate([
            'participant_id' => ['required', 'integer', Rule::exists('participants', 'id')->where('initiative_id', $initiative->id)],
        ]);

        $interview = $initiative->interviews()->firstOrCreate(
            ['participant_id' => $validated['participant_id']],
            ['interviewer_id' => $request->user()->id, 'conducted_at' => now(), 'status' => InterviewStatus::Draft],
        );

        return to_route('initiatives.interviews.show', [$initiative, $interview]);
    }

    public function show(Request $request, Initiative $initiative, Interview $interview): Response
    {
        $this->authorize('update', $initiative);

        $interview->load(['participant.segmentValues.category', 'notes.codes', 'notes.stage', 'interviewer']);
        $siblings = $initiative->interviews()->join('participants', 'participants.id', '=', 'interviews.participant_id')
            ->orderBy('participants.name')->pluck('interviews.id')->all();
        $position = array_search($interview->id, $siblings, true);

        return Inertia::render('initiatives/interview', [
            'initiative' => InitiativeController::summary($initiative),
            'interview' => [
                'id' => $interview->id,
                'status' => $interview->status->value,
                'conducted_at' => $interview->conducted_at?->format('Y-m-d\TH:i'),
                'interviewer' => $interview->interviewer?->name,
                'responsibilities' => $interview->responsibilities,
                'tools' => $interview->tools,
                'transcript' => $interview->transcript,
            ],
            'participant' => [
                'name' => $interview->participant->name,
                'role' => $interview->participant->role,
                'area' => $this->area($interview->participant),
                'groups' => $interview->participant->segmentValues
                    ->reject(fn (SegmentValue $v) => $v->category->is_area)
                    ->map(fn (SegmentValue $v) => "{$v->category->name}: {$v->name}")->values(),
            ],
            'notes' => $interview->notes->map(fn (InterviewNote $n) => [
                'id' => $n->id,
                'kind' => $n->kind->value,
                'body' => $n->body,
                'workflow_stage_id' => $n->workflow_stage_id,
                'stage' => $n->stage?->name,
                'friction_point_id' => $n->friction_point_id,
                'owner' => $n->owner,
                'done' => $n->done,
                'code_ids' => $n->codes->pluck('id'),
            ]),
            'kinds' => NoteKind::options(),
            'stages' => $initiative->stages()->get()->map(fn (WorkflowStage $s) => ['id' => $s->id, 'name' => $s->name]),
            'codes' => $initiative->codes()->get()->map(fn (Code $c) => ['id' => $c->id, 'name' => $c->name]),
            'previousId' => $position !== false && $position > 0 ? $siblings[$position - 1] : null,
            'nextId' => $position !== false && $position < count($siblings) - 1 ? $siblings[$position + 1] : null,
        ]);
    }

    /**
     * Completing an interview also marks the participant as interviewed on the tour.
     */
    public function update(Request $request, Initiative $initiative, Interview $interview): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $validated = $request->validate([
            'conducted_at' => ['nullable', 'date'],
            'status' => ['required', Rule::enum(InterviewStatus::class)],
            'responsibilities' => ['nullable', 'string', 'max:5000'],
            'tools' => ['nullable', 'string', 'max:5000'],
            'transcript' => ['nullable', 'string', 'max:200000'],
        ]);

        $interview->update($validated);

        if ($interview->status === InterviewStatus::Complete) {
            $interview->participant->update(['status' => ParticipantStatus::Interviewed]);
        }

        return back()->with('success', $interview->status === InterviewStatus::Complete ? 'Interview saved and marked complete.' : 'Interview saved.');
    }

    public function destroy(Initiative $initiative, Interview $interview): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $interview->delete();

        return to_route('initiatives.interviews', $initiative)->with('success', 'Interview notes deleted.');
    }

    /**
     * @param  iterable<InterviewNote>  $notes
     * @return array<int, array{id: int, name: string, notes: int, interviews: int}>
     */
    private function codeFrequency(Initiative $initiative, iterable $notes): array
    {
        return CodeFrequency::build(
            $initiative->codes()->get()->map(fn (Code $c) => ['id' => $c->id, 'name' => $c->name])->all(),
            collect($notes)->map(fn (InterviewNote $n) => ['interview_id' => $n->interview_id, 'code_ids' => $n->codes->pluck('id')->all()])->all(),
        );
    }

    private function area(Participant $participant): ?string
    {
        return $participant->segmentValues->first(fn (SegmentValue $v) => $v->category->is_area)?->name;
    }
}

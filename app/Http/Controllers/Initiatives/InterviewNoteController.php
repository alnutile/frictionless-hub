<?php

namespace App\Http\Controllers\Initiatives;

use App\Enums\NoteKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Initiatives\InterviewNoteRequest;
use App\Models\Initiative;
use App\Models\Interview;
use App\Models\InterviewNote;
use App\Models\SegmentValue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class InterviewNoteController extends Controller
{
    public function store(InterviewNoteRequest $request, Initiative $initiative, Interview $interview): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $note = $interview->notes()->create([
            ...Arr::except($request->validated(), 'code_ids'),
            'position' => (int) $interview->notes()->max('position') + 1,
        ]);
        $note->codes()->sync($request->validated('code_ids', []));

        return back();
    }

    public function update(InterviewNoteRequest $request, Initiative $initiative, Interview $interview, InterviewNote $interviewNote): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $interviewNote->update(Arr::except($request->validated(), ['code_ids', 'kind']));

        if ($request->has('code_ids')) {
            $interviewNote->codes()->sync($request->validated('code_ids', []));
        }

        return back();
    }

    /**
     * Toggle a follow-up's done state without resending the whole note.
     */
    public function toggleDone(Initiative $initiative, Interview $interview, InterviewNote $interviewNote): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $interviewNote->update(['done' => ! $interviewNote->done]);

        return back();
    }

    public function destroy(Initiative $initiative, Interview $interview, InterviewNote $interviewNote): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $interviewNote->delete();

        return back();
    }

    /**
     * Copy a pain point into the friction log (step 2), keeping a link back to the note.
     * The participant's name is never copied; their work area is used for "who it affects".
     */
    public function promote(Request $request, Initiative $initiative, Interview $interview, InterviewNote $interviewNote): RedirectResponse
    {
        $this->authorize('update', $initiative);
        abort_unless($interviewNote->kind === NoteKind::PainPoint, 422, 'Only pain points can be added to the friction log.');

        if ($interviewNote->friction_point_id !== null) {
            return back()->with('success', 'Already in the friction log.');
        }

        $area = $interview->participant->segmentValues()->with('category')->get()
            ->first(fn (SegmentValue $v) => $v->category->is_area)?->name;

        $point = $initiative->frictionPoints()->make([
            'title' => Str::limit($interviewNote->body, 120),
            'description' => $interviewNote->body."\n\n(From a listening-tour interview.)",
            'workflow_stage_id' => $interviewNote->workflow_stage_id,
            'affected' => $area,
        ]);
        $point->created_by = $request->user()->id;
        $point->save();

        $interviewNote->forceFill(['friction_point_id' => $point->id])->save();

        return back()->with('success', 'Added to the friction log. Set its frequency and pain there.');
    }
}

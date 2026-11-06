<?php

namespace App\Http\Controllers\Initiatives;

use App\Http\Controllers\Controller;
use App\Http\Requests\Initiatives\ParticipantRequest;
use App\Models\ChecklistItem;
use App\Models\Initiative;
use App\Models\Participant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;

class ParticipantController extends Controller
{
    public function store(ParticipantRequest $request, Initiative $initiative): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $participant = $initiative->participants()->create(Arr::except($request->validated(), ['area_value_id', 'value_ids']));
        $participant->segmentValues()->sync($this->segmentIds($request));

        return back()->with('success', 'Participant added.');
    }

    public function update(ParticipantRequest $request, Initiative $initiative, Participant $participant): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $participant->update(Arr::except($request->validated(), ['area_value_id', 'value_ids']));
        $participant->segmentValues()->sync($this->segmentIds($request));

        return back()->with('success', 'Participant updated.');
    }

    public function destroy(Initiative $initiative, Participant $participant): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $participant->delete();

        return back()->with('success', 'Participant removed.');
    }

    /**
     * Check or uncheck one pre-interview checklist item for a participant.
     */
    public function toggleChecklistItem(Initiative $initiative, Participant $participant, ChecklistItem $checklistItem): RedirectResponse
    {
        $this->authorize('update', $initiative);
        // This route can't use scoped bindings (the item belongs to the initiative, not the participant), so check both by hand.
        abort_unless($participant->initiative_id === $initiative->id && $checklistItem->initiative_id === $initiative->id, 404);

        $participant->completedChecklistItems()->toggle([$checklistItem->id]);

        return back();
    }

    /**
     * @return array<int, int>
     */
    private function segmentIds(ParticipantRequest $request): array
    {
        return array_values(array_filter([
            $request->integer('area_value_id') ?: null,
            ...array_map('intval', $request->validated('value_ids', [])),
        ]));
    }
}

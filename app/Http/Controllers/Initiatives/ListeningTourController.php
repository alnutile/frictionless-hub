<?php

namespace App\Http\Controllers\Initiatives;

use App\Enums\ParticipantStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\InitiativeController;
use App\Models\ChecklistItem;
use App\Models\FrictionPoint;
use App\Models\Initiative;
use App\Models\MessageTemplate;
use App\Models\Participant;
use App\Models\RatingItem;
use App\Models\SegmentCategory;
use App\Models\SegmentValue;
use App\Services\RatingSummary;
use App\Services\TourCoverage;
use App\Services\TourSuggestions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ListeningTourController extends Controller
{
    public function index(Request $request, Initiative $initiative): Response
    {
        $this->authorize('view', $initiative);

        $canManage = $request->user()->can('update', $initiative);
        $areaCategory = $initiative->areaCategory();
        $categories = $initiative->segmentCategories()->with('values')->get();
        $groups = $categories->where('is_area', false)->values();
        $participants = $initiative->participants()->with(['segmentValues', 'completedChecklistItems'])->get();

        $valueShape = fn (SegmentValue $v) => ['id' => $v->id, 'name' => $v->name];
        $areas = $areaCategory->values->map($valueShape)->all();
        $groupShapes = $groups->map(fn (SegmentCategory $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'values' => $c->values->map($valueShape)->all(),
        ])->all();

        $areaValueIds = $areaCategory->values->pluck('id');
        $counted = $participants->filter(fn (Participant $p) => $p->status->countsTowardCoverage());

        return Inertia::render('initiatives/listening-tour', [
            'initiative' => InitiativeController::summary($initiative),
            'canManage' => $canManage,
            'target' => $initiative->interview_target,
            'areaCategoryId' => $areaCategory->id,
            'areas' => $areas,
            'groups' => $groupShapes,
            'coverage' => TourCoverage::build(
                $areas,
                $groupShapes,
                $counted->map(fn (Participant $p) => [
                    'area_value_id' => $p->segmentValues->first(fn (SegmentValue $v) => $areaValueIds->contains($v->id))?->id,
                    'value_ids' => $p->segmentValues->pluck('id')->all(),
                ])->values()->all(),
                $initiative->interview_target,
            ),
            'statusCounts' => collect(ParticipantStatus::cases())
                ->mapWithKeys(fn (ParticipantStatus $s) => [$s->value => $participants->where('status', $s)->count()]),
            'suggestions' => $this->suggestions($initiative),
            // Names and notes identify people, so only those who run the initiative see them.
            'participants' => $canManage ? $participants->map(fn (Participant $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'role' => $p->role,
                'status' => $p->status->value,
                'scheduled_at' => $p->scheduled_at?->format('Y-m-d\TH:i'),
                'invite_reason' => $p->invite_reason,
                'notes' => $p->notes,
                'area_value_id' => $p->segmentValues->first(fn (SegmentValue $v) => $areaValueIds->contains($v->id))?->id,
                'value_ids' => $p->segmentValues->reject(fn (SegmentValue $v) => $areaValueIds->contains($v->id))->pluck('id')->values(),
                'checked_item_ids' => $p->completedChecklistItems->pluck('id'),
            ]) : null,
            'checklist' => $initiative->checklistItems()->get()->map(fn (ChecklistItem $i) => ['id' => $i->id, 'label' => $i->label]),
            'templates' => $initiative->messageTemplates()->where('purpose', MessageTemplate::PURPOSE_INVITE)->get()
                ->map(fn (MessageTemplate $t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'channel' => $t->channel,
                    'subject' => $t->subject,
                    'body' => $t->body,
                ]),
            'statuses' => ParticipantStatus::options(),
            'mergeFields' => MessageTemplate::MERGE_FIELDS,
            'facilitator' => $request->user()->name,
        ]);
    }

    public function updateTarget(Request $request, Initiative $initiative): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $initiative->update($request->validate(['interview_target' => ['nullable', 'integer', 'min:1', 'max:500']]));

        return back()->with('success', 'Target updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function suggestions(Initiative $initiative): array
    {
        $points = $initiative->frictionPoints()->with('stage:id,name')->get()->map(fn (FrictionPoint $p) => [
            'title' => $p->title,
            'stage' => $p->stage?->name,
            'pain' => $p->pain,
            'affected' => $p->affected,
        ])->all();

        $items = $initiative->ratingItems()->with('ratings')->get()->map(fn (RatingItem $i) => [
            'label' => $i->label,
            ...array_intersect_key(RatingSummary::of($i->ratings->pluck('score')->all()), array_flip(['average', 'count', 'spread'])),
        ])->all();

        return TourSuggestions::build($points, $items);
    }
}

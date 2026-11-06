<?php

namespace App\Services;

use App\Enums\InterviewStatus;
use App\Enums\ParticipantStatus;
use App\Models\Code;
use App\Models\Dimension;
use App\Models\FrictionPoint;
use App\Models\Initiative;
use App\Models\InterviewNote;
use App\Models\Participant;
use App\Models\RatingGroup;
use App\Models\RatingItem;
use App\Models\SegmentValue;
use App\Models\StageTool;
use App\Models\StoryMapItem;
use App\Models\Theme;
use App\Models\User;
use App\Models\WorkflowStage;
use App\Support\Walkthrough;
use Illuminate\Support\Str;

/**
 * Builds the context block the assistant sees: who is asking, where they are in the
 * walkthrough, the current guide text, and a readable snapshot of the initiative's data.
 */
class AssistantContext
{
    /**
     * Upper bound on context size so a large initiative can't blow up the prompt.
     */
    public const MAX_CHARACTERS = 24_000;

    public static function build(User $user, ?Initiative $initiative, string $activityKey): string
    {
        $sections = [
            self::person($user, $initiative),
            self::walkthrough($activityKey, $initiative),
            self::currentGuide($activityKey),
        ];

        if ($initiative) {
            $sections[] = self::initiative($initiative);
            $sections[] = self::interviews($initiative, $user->can('update', $initiative));
            $sections[] = self::themes($initiative);
            $sections[] = self::notes($initiative);
        }

        return Str::limit(implode("\n\n", array_filter($sections)), self::MAX_CHARACTERS, "\n\n[Context truncated]");
    }

    private static function person(User $user, ?Initiative $initiative): string
    {
        $canEdit = $initiative ? $user->can('update', $initiative) : $user->canFacilitate();

        return "## Person asking\n- Name: {$user->name}\n- Role: {$user->role->label()}\n- Can edit this initiative: ".($canEdit ? 'yes' : 'no');
    }

    private static function walkthrough(string $activityKey, ?Initiative $initiative): string
    {
        $lines = ['## Walkthrough (in order)'];

        foreach (Walkthrough::forFrontend() as $activity) {
            $marker = $activity['key'] === $activityKey ? ' ← current screen' : '';
            $status = $activity['route'] ? '' : ' (not built yet)';
            $lines[] = "{$activity['step']}. [{$activity['phase_label']}] {$activity['title']}{$status}{$marker}";
        }

        if ($initiative) {
            $lines[] = "\nThe initiative's current phase is: {$initiative->phase->label()}.";
        }

        return implode("\n", $lines);
    }

    private static function currentGuide(string $activityKey): string
    {
        return '## Current screen: '.Walkthrough::titleFor($activityKey)."\nGuide text shown to the person:\n\n".trim(Guide::body($activityKey));
    }

    private static function initiative(Initiative $initiative): string
    {
        $initiative->loadMissing([
            'owner',
            'stages.tools',
            'dimensions',
            'frictionPoints.stage',
            'frictionPoints.dimensions',
            'ratingGroups.items.ratings',
        ]);

        $parts = [
            "## Initiative: {$initiative->name}\n- Scope: ".($initiative->scope ?: 'not set')."\n- Owner: {$initiative->owner->name}"
                .($initiative->description ? "\n- Description: {$initiative->description}" : ''),
            self::workflow($initiative),
            self::friction($initiative),
            self::storyMap($initiative),
            self::ratings($initiative),
            self::listeningTour($initiative),
        ];

        return implode("\n\n", array_filter($parts));
    }

    private static function workflow(Initiative $initiative): string
    {
        $lines = ['### Workflow stages and tools'];

        foreach ($initiative->stages as $stage) {
            /** @var WorkflowStage $stage */
            $tools = $stage->tools->map(fn (StageTool $t) => $t->name.($t->is_ai ? ' (AI)' : ''))->implode(', ');
            $lines[] = "- {$stage->name}".($stage->ai_assisted ? ' [AI-assisted]' : '').': '.($tools ?: 'no tools listed')
                .($stage->notes ? " — Notes: {$stage->notes}" : '');
        }

        return count($lines) > 1 ? implode("\n", $lines) : '';
    }

    private static function friction(Initiative $initiative): string
    {
        if ($initiative->frictionPoints->isEmpty()) {
            return "### Friction points\nNone logged yet.";
        }

        $lines = ['### Friction points (title | stage | how often | pain 1–5 | who | dimensions)'];

        foreach ($initiative->frictionPoints->sortByDesc('pain') as $point) {
            /** @var FrictionPoint $point */
            $dimensions = $point->dimensions->map(fn (Dimension $d) => $d->name)->implode(', ');
            $lines[] = '- '.implode(' | ', [
                $point->title,
                $point->stage?->name ?? 'no stage',
                $point->frequency?->label() ?? 'unknown',
                $point->pain ?? '?',
                $point->affected ?? 'unknown',
                $dimensions ?: 'none',
            ]).($point->description ? " — {$point->description}" : '');
        }

        return implode("\n", $lines);
    }

    private static function storyMap(Initiative $initiative): string
    {
        $activities = $initiative->storyMapItems()
            ->where('level', StoryMapItem::LEVEL_ACTIVITY)
            ->orderBy('position')
            ->with('children.children')
            ->get();

        if ($activities->isEmpty()) {
            return '';
        }

        $lines = ['### Story map'];

        foreach ($activities as $activity) {
            $lines[] = "- {$activity->title}";

            foreach ($activity->children as $task) {
                $cards = $task->children->map(fn (StoryMapItem $c) => "{$c->title} ({$c->kind?->value})")->implode('; ');
                $lines[] = "  - {$task->title}: {$cards}";
            }
        }

        return implode("\n", $lines);
    }

    private static function ratings(Initiative $initiative): string
    {
        if ($initiative->ratingGroups->isEmpty()) {
            return '';
        }

        $lines = ['### Workflow ratings (1 = excellent, 5 = severe problems)'];

        foreach ($initiative->ratingGroups as $group) {
            /** @var RatingGroup $group */
            $lines[] = "- {$group->name}";

            foreach ($group->items as $item) {
                /** @var RatingItem $item */
                $summary = RatingSummary::of($item->ratings->pluck('score')->all());
                $lines[] = $summary['count'] === 0
                    ? "  - {$item->label}: no ratings"
                    : "  - {$item->label}: average {$summary['average']} from {$summary['count']} people (spread {$summary['spread']})";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Aggregates only: participant names and notes are never sent to the model.
     */
    private static function listeningTour(Initiative $initiative): string
    {
        $participants = $initiative->participants()->with('segmentValues')->get();
        $areaCategory = $initiative->segmentCategories()->where('is_area', true)->with('values')->first();

        if ($participants->isEmpty() && ($areaCategory?->values->isEmpty() ?? true)) {
            return '';
        }

        $lines = ['### Listening tour (counts only; names are private)'];
        $lines[] = '- Target: '.($initiative->interview_target ?? 'not set');
        $lines[] = '- By status: '.collect(ParticipantStatus::cases())
            ->map(fn (ParticipantStatus $s) => $s->label().' '.$participants->where('status', $s)->count())
            ->implode(', ');

        if ($areaCategory) {
            $counted = $participants->filter(fn (Participant $p) => $p->status->countsTowardCoverage());
            $perArea = $areaCategory->values->map(fn (SegmentValue $area) => $area->name.' '.$counted->filter(
                fn (Participant $p) => $p->segmentValues->contains('id', $area->id),
            )->count());
            $lines[] = '- People planned per work area: '.$perArea->implode(', ');
        }

        return implode("\n", $lines);
    }

    /**
     * Code counts for everyone. Note text only for people who run the initiative,
     * labelled by work area and never by name.
     */
    private static function interviews(Initiative $initiative, bool $canManage): string
    {
        $interviews = $initiative->interviews()->with(['notes.codes', 'participant.segmentValues.category'])->get();

        if ($interviews->isEmpty()) {
            return '';
        }

        $notes = $interviews->flatMap->notes;
        $lines = ['### Interviews'];
        $lines[] = '- Complete: '.$interviews->where('status', InterviewStatus::Complete)->count().', in progress: '.$interviews->where('status', InterviewStatus::Draft)->count();

        $frequency = CodeFrequency::build(
            $initiative->codes()->get()->map(fn (Code $c) => ['id' => $c->id, 'name' => $c->name])->all(),
            $notes->map(fn (InterviewNote $n) => ['interview_id' => $n->interview_id, 'code_ids' => $n->codes->pluck('id')->all()])->all(),
        );
        $lines[] = '- Codes (mentioned in N interviews): '.(collect($frequency)
            ->filter(fn (array $c) => $c['interviews'] > 0)
            ->map(fn (array $c) => "{$c['name']} {$c['interviews']}")
            ->implode(', ') ?: 'none yet');

        if (! $canManage) {
            return implode("\n", $lines);
        }

        $lines[] = '- Notes (by work area; names withheld):';
        foreach ($interviews as $interview) {
            $area = $interview->participant->segmentValues->first(fn (SegmentValue $v) => $v->category->is_area)?->name ?? 'unknown area';

            foreach ($interview->notes as $note) {
                $codes = $note->codes->pluck('name')->implode(', ');
                $lines[] = "  - [{$area}] {$note->kind->label()}: {$note->body}".($codes ? " (codes: {$codes})" : '');
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Themes with their weight and where they were heard. Aggregates only, so safe for every role.
     */
    private static function themes(Initiative $initiative): string
    {
        $themes = $initiative->themes()->with(['codes', 'frictionPoint'])->get();
        $codes = $initiative->codes()->get();

        if ($themes->isEmpty() && $codes->isEmpty()) {
            return '';
        }

        $notes = $initiative->interviews()->with(['notes.codes', 'participant.segmentValues.category'])->get()
            ->flatMap(fn ($interview) => $interview->notes->map(fn (InterviewNote $n) => [
                'id' => $n->id,
                'interview_id' => $interview->id,
                'area' => $interview->participant->segmentValues->first(fn (SegmentValue $v) => $v->category->is_area)?->name,
                'code_ids' => $n->codes->pluck('id')->all(),
            ]))->values()->all();

        $weights = ThemeWeights::build(
            $themes->map(fn (Theme $t) => ['id' => $t->id, 'code_ids' => $t->codes->pluck('id')->all()])->all(),
            $notes,
        );

        $lines = ['### Themes (from interview codes)'];
        foreach ($themes as $theme) {
            $weight = $weights[$theme->id];
            $areas = collect($weight['areas'])->map(fn (array $a) => "{$a['area']} {$a['interviews']}")->implode(', ');
            $lines[] = "- {$theme->name}: {$weight['interviews']} interviews, {$weight['notes']} notes"
                .' | codes: '.($theme->codes->pluck('name')->implode(', ') ?: 'none')
                .($areas ? " | heard from: {$areas}" : '')
                .($theme->impact ? " | impact {$theme->impact->value}" : '')
                .($theme->effort ? " | effort {$theme->effort->value}" : '')
                .($theme->frictionPoint ? " | in friction log as \"{$theme->frictionPoint->title}\"" : ' | not in friction log')
                .($theme->summary ? " — {$theme->summary}" : '');
        }

        $unthemed = $codes->whereNull('theme_id')->pluck('name');
        $lines[] = '- Codes not in any theme: '.($unthemed->implode(', ') ?: 'none');

        return implode("\n", $lines);
    }

    private static function notes(Initiative $initiative): string
    {
        $notes = $initiative->activityNotes()->whereNotNull('body')->get();

        if ($notes->isEmpty()) {
            return '';
        }

        $lines = ["## The team's notes"];

        foreach ($notes as $note) {
            $lines[] = '- '.Walkthrough::titleFor($note->activity_key).": {$note->body}";
        }

        return implode("\n", $lines);
    }
}

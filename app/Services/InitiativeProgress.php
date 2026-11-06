<?php

namespace App\Services;

use App\Enums\InterviewStatus;
use App\Enums\ParticipantStatus;
use App\Models\Initiative;
use App\Models\Rating;
use App\Models\StageTool;

/**
 * The "how much is captured" count and label per walkthrough activity, shared by the
 * overview ladder and the MCP server so both report the same numbers.
 */
class InitiativeProgress
{
    /**
     * @return array<string, array{count: int, label: string}>
     */
    public static function for(Initiative $initiative): array
    {
        $tools = StageTool::whereIn('workflow_stage_id', $initiative->stages()->select('id'))->count();
        $ratings = Rating::whereIn('rating_item_id', $initiative->ratingItems()->select('rating_items.id'))->count();
        $codes = $initiative->codes()->count();

        return [
            'workflow' => ['count' => $initiative->stages()->count(), 'label' => "stages · {$tools} tools"],
            'friction' => ['count' => $initiative->frictionPoints()->count(), 'label' => 'friction points'],
            'story-map' => ['count' => $initiative->storyMapItems()->count(), 'label' => 'story map cards'],
            'quick-rating' => ['count' => $ratings, 'label' => 'ratings'],
            'listening-tour' => [
                'count' => $initiative->participants()->where('status', '!=', ParticipantStatus::Declined)->count(),
                'label' => 'people on the tour · '.$initiative->participants()->where('status', ParticipantStatus::Interviewed)->count().' interviewed',
            ],
            'interviews' => [
                'count' => $initiative->interviews()->where('status', InterviewStatus::Complete)->count(),
                'label' => "complete · {$codes} codes",
            ],
            'themes' => [
                'count' => $initiative->themes()->count(),
                'label' => 'themes · '.$initiative->codes()->whereNotNull('theme_id')->count()." of {$codes} codes grouped",
            ],
        ];
    }
}

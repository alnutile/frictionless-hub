<?php

namespace App\Support;

use App\Enums\Phase;

/**
 * The ordered list of activities an initiative works through, following
 * the step order of the companion workbook. Titles and guide text are our own.
 *
 * An activity with a null route is planned but not built yet.
 */
class Walkthrough
{
    /**
     * @return array<int, array{key: string, phase: Phase, title: string, route: string|null}>
     */
    public static function activities(): array
    {
        return [
            ['key' => 'workflow', 'phase' => Phase::Discover, 'title' => 'Map the workflow', 'route' => 'initiatives.workflow'],
            ['key' => 'friction', 'phase' => Phase::Discover, 'title' => 'Log friction', 'route' => 'initiatives.friction'],
            ['key' => 'story-map', 'phase' => Phase::Discover, 'title' => 'Story map', 'route' => 'initiatives.story-map'],
            ['key' => 'quick-rating', 'phase' => Phase::Discover, 'title' => 'Rate the workflow', 'route' => 'initiatives.ratings'],
            ['key' => 'listening-tour', 'phase' => Phase::Discover, 'title' => 'Plan the listening tour', 'route' => 'initiatives.listening-tour'],
            ['key' => 'interviews', 'phase' => Phase::Discover, 'title' => 'Interviews', 'route' => 'initiatives.interviews'],
            ['key' => 'themes', 'phase' => Phase::Discover, 'title' => 'Synthesize themes', 'route' => 'initiatives.themes'],
            ['key' => 'stakeholders', 'phase' => Phase::Discover, 'title' => 'Stakeholders', 'route' => null],
            ['key' => 'quick-wins', 'phase' => Phase::QuickWins, 'title' => 'Score quick wins', 'route' => null],
            ['key' => 'metrics', 'phase' => Phase::Data, 'title' => 'Data availability', 'route' => null],
            ['key' => 'surveys', 'phase' => Phase::Data, 'title' => 'Surveys', 'route' => null],
            ['key' => 'data-mapping', 'phase' => Phase::Data, 'title' => 'Data coverage', 'route' => null],
            ['key' => 'prioritize', 'phase' => Phase::Prioritize, 'title' => 'Prioritize projects', 'route' => null],
            ['key' => 'audiences', 'phase' => Phase::Sell, 'title' => 'Audiences', 'route' => null],
            ['key' => 'comms-plan', 'phase' => Phase::Sell, 'title' => 'Communications plan', 'route' => null],
            ['key' => 'value', 'phase' => Phase::Implement, 'title' => 'Value & ROI', 'route' => null],
            ['key' => 'org-model', 'phase' => Phase::Implement, 'title' => 'Org model', 'route' => null],
            ['key' => 'resistance', 'phase' => Phase::Implement, 'title' => 'Manage resistance', 'route' => null],
        ];
    }

    /**
     * Guide keys that are not walkthrough activities.
     *
     * @return array<string, string>
     */
    public static function extraGuides(): array
    {
        return [
            'start' => 'How Frictionless Hub works',
            'overview' => 'Initiative overview',
        ];
    }

    /**
     * Resolve which guide key belongs to a route name.
     */
    public static function keyForRoute(?string $routeName, ?string $plannedKey = null): ?string
    {
        if ($routeName === null) {
            return null;
        }

        if ($routeName === 'initiatives.planned') {
            return $plannedKey !== null && self::isKnownKey($plannedKey) ? $plannedKey : null;
        }

        foreach (self::activities() as $activity) {
            if ($activity['route'] === $routeName) {
                return $activity['key'];
            }
        }

        return match ($routeName) {
            'initiatives.index' => 'start',
            'initiatives.show' => 'overview',
            'initiatives.interviews.show' => 'interviews',
            'initiatives.themes.show' => 'themes',
            default => null,
        };
    }

    public static function isKnownKey(string $key): bool
    {
        return array_key_exists($key, self::extraGuides())
            || in_array($key, array_column(self::activities(), 'key'), true);
    }

    public static function titleFor(string $key): string
    {
        foreach (self::activities() as $activity) {
            if ($activity['key'] === $key) {
                return $activity['title'];
            }
        }

        return self::extraGuides()[$key] ?? $key;
    }

    /**
     * Activities serialized for the frontend, with step numbers.
     *
     * @return array<int, array{key: string, step: int, phase: string, phase_label: string, title: string, route: string|null}>
     */
    public static function forFrontend(): array
    {
        return array_map(fn (array $activity, int $index) => [
            'key' => $activity['key'],
            'step' => $index + 1,
            'phase' => $activity['phase']->value,
            'phase_label' => $activity['phase']->label(),
            'title' => $activity['title'],
            'route' => $activity['route'],
        ], self::activities(), array_keys(self::activities()));
    }
}

<?php

namespace App\Enums;

/**
 * The layer a story map card belongs to.
 */
enum StoryMapKind: string
{
    case Story = 'story';
    case Friction = 'friction';
    case Tool = 'tool';
    case Gap = 'gap';
    case Team = 'team';

    public function label(): string
    {
        return match ($this) {
            self::Story => 'Story',
            self::Friction => 'Friction',
            self::Tool => 'Tool',
            self::Gap => 'Gap',
            self::Team => 'Team',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $k) => ['value' => $k->value, 'label' => $k->label()], self::cases());
    }
}

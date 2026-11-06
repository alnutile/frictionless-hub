<?php

namespace App\Enums;

/**
 * The sections of the interview notes form, in display order.
 */
enum NoteKind: string
{
    case PainPoint = 'pain_point';
    case BrightSpot = 'bright_spot';
    case Quote = 'quote';
    case Observation = 'observation';
    case FollowUp = 'follow_up';

    public function label(): string
    {
        return match ($this) {
            self::PainPoint => 'Pain points',
            self::BrightSpot => 'Bright spots',
            self::Quote => 'Quotes',
            self::Observation => 'Observations',
            self::FollowUp => 'Follow-ups',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $k) => ['value' => $k->value, 'label' => $k->label()], self::cases());
    }
}

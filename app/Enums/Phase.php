<?php

namespace App\Enums;

enum Phase: string
{
    case Discover = 'discover';
    case QuickWins = 'quick_wins';
    case Data = 'data';
    case Prioritize = 'prioritize';
    case Sell = 'sell';
    case Strategy = 'strategy';
    case Implement = 'implement';

    public function label(): string
    {
        return match ($this) {
            self::Discover => 'Discover',
            self::QuickWins => 'Quick wins',
            self::Data => 'Data',
            self::Prioritize => 'Prioritize',
            self::Sell => 'Sell your strategy',
            self::Strategy => 'Strategy',
            self::Implement => 'Implement & report',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $p) => ['value' => $p->value, 'label' => $p->label()], self::cases());
    }
}

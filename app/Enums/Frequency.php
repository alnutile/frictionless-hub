<?php

namespace App\Enums;

enum Frequency: string
{
    case Rarely = 'rarely';
    case Monthly = 'monthly';
    case Weekly = 'weekly';
    case Daily = 'daily';
    case MultipleDaily = 'multiple_daily';

    public function label(): string
    {
        return match ($this) {
            self::Rarely => 'Rarely',
            self::Monthly => 'Monthly',
            self::Weekly => 'Weekly',
            self::Daily => 'Daily',
            self::MultipleDaily => 'Multiple times a day',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $f) => ['value' => $f->value, 'label' => $f->label()], self::cases());
    }
}

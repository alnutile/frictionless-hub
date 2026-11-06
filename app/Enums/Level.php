<?php

namespace App\Enums;

enum Level: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $l) => ['value' => $l->value, 'label' => $l->label()], self::cases());
    }
}

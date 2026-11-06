<?php

namespace App\Enums;

enum ParticipantStatus: string
{
    case Candidate = 'candidate';
    case Invited = 'invited';
    case Scheduled = 'scheduled';
    case Interviewed = 'interviewed';
    case Declined = 'declined';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Declined people stay on the list but don't count toward coverage.
     */
    public function countsTowardCoverage(): bool
    {
        return $this !== self::Declined;
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}

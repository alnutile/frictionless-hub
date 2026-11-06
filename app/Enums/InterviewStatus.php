<?php

namespace App\Enums;

enum InterviewStatus: string
{
    case Draft = 'draft';
    case Complete = 'complete';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}

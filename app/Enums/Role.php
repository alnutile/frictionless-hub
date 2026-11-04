<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Facilitator = 'facilitator';
    case Viewer = 'viewer';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}

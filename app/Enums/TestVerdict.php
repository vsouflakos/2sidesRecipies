<?php

namespace App\Enums;

enum TestVerdict: string
{
    case Worked = 'worked';
    case DidntWork = 'didnt_work';
    case Inconclusive = 'inconclusive';

    public function label(): string
    {
        return match ($this) {
            self::Worked => 'Worked',
            self::DidntWork => "Didn't work",
            self::Inconclusive => 'Inconclusive',
        };
    }
}

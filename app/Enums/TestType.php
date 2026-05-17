<?php

namespace App\Enums;

enum TestType: string
{
    case Trial = 'trial';
    case Experiment = 'experiment';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Trial Run',
            self::Experiment => 'Experiment',
        };
    }
}

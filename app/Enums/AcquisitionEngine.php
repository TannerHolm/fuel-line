<?php

namespace App\Enums;

enum AcquisitionEngine: string
{
    case Direct = 'direct';
    case Seeded = 'seeded';

    public function label(): string
    {
        return match ($this) {
            self::Direct => 'Direct outbound',
            self::Seeded => 'Demand-seeded',
        };
    }
}

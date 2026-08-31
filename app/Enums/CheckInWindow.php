<?php

namespace App\Enums;

enum CheckInWindow: string
{
    case Day7 = '7';
    case Day14 = '14';
    case Day30 = '30';
    case AdHoc = 'adhoc';

    public function label(): string
    {
        return match ($this) {
            self::Day7 => 'Day 7',
            self::Day14 => 'Day 14',
            self::Day30 => 'Day 30',
            self::AdHoc => 'Ad hoc',
        };
    }
}

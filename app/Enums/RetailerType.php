<?php

namespace App\Enums;

enum RetailerType: string
{
    case Service = 'service';
    case Performance = 'performance';
    case Convenience = 'convenience';

    public function label(): string
    {
        return match ($this) {
            self::Service => 'Service',
            self::Performance => 'Performance',
            self::Convenience => 'Convenience',
        };
    }
}
